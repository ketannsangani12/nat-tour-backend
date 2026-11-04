<?php

namespace App\Repositories\Report;

use App\Repositories\Repository;
use App\Services\Booking\RemainingPayment\UnpaidPaxService;
use App\Utils\BokunUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Query\Builder;
use Exception;

class PaymentBreakdownRepository extends Repository
{
    /**
     * Get comprehensive payment breakdown data with tenant isolation
     *
     * @param array $filters
     * @return Collection
     */
    public function getPaymentBreakdownData(array $filters): Collection
    {
        try {
            // Add error handling for unpaid booking queries
            try {
                $rawData = $this->buildMainQuery($filters)->get();
            } catch (Exception $queryException) {
                Log::error('PaymentBreakdownRepository::buildMainQuery Error: ' . $queryException->getMessage(), [
                    'filters' => $filters,
                    'query_type' => $this->determineQueryType($filters)
                ]);
                
                // Fallback to paid bookings only if unpaid query fails
                if ($this->determineQueryType($filters) !== 'paid_only') {
                    Log::warning('Falling back to paid bookings only due to query error');
                    $fallbackFilters = $filters;
                    $fallbackFilters['paymentTypes'] = array_diff($filters['paymentTypes'] ?? [], ['Unpaid']);
                    $rawData = $this->buildPaidBookingsQuery($fallbackFilters)->get();
                } else {
                    throw $queryException;
                }
            }

            // Aggregate data per booking like Transaction Summary Report
            $aggregatedData = $this->aggregateTransactionData($rawData, $filters);

            return collect($aggregatedData);
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getPaymentBreakdownData Error: ' . $exception->getMessage(), [
                'filters' => $filters
            ]);
            throw $exception;
        }
    }

    /**
     * Determine the query type based on filters
     *
     * @param array $filters
     * @return string
     */
    private function determineQueryType(array $filters): string
    {
        $paymentTypes = $filters['paymentTypes'] ?? [];
        $includeUnpaid = in_array('Unpaid', $paymentTypes);
        $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));
        
        if ($includeUnpaid && !$includeOtherTypes) {
            return 'unpaid_only';
        } elseif ($includeUnpaid && $includeOtherTypes) {
            return 'mixed';
        } else {
            return 'paid_only';
        }
    }

    /**
     * Map a raw transactions.charge_type value to the Charge Type filter's bucket.
     *
     * @param string|null $chargeType
     * @return string 'tours'|'custom'|'addons'
     */
    private function chargeTypeBucket(?string $chargeType): string
    {
        return match ($chargeType) {
            'custom_charge' => 'custom',
            'addons' => 'addons',
            default => 'tours', // NULL (base tour payment), 'discount', 'tip'
        };
    }

    /**
     * Classify a transaction's `via` value into the report's purchase_type bucket,
     * applying the Invoice/Billable/Paid split for 'Invoiced' transactions
     * (exactly like Transaction Summary Report).
     *
     * Extracted so it can be re-applied after aggregation to whichever `via`
     * turns out to be the booking's dominant payment method (see
     * aggregateTransactionData()'s dominant-via resolution pass), not just
     * whichever transaction happened to be seen first.
     *
     * @return array{0: string, 1: string|null} [$purchaseType, $billableInvoicedStatus]
     */
    private function classifyPurchaseType(string $via, ?int $invoiceId, ?string $invoiceStatus, ?string $bookingStatus): array
    {
        if ($via !== 'Invoiced') {
            return [$via, null];
        }

        if ($bookingStatus === 'Booked') {
            // For Booked bookings, use full classification logic
            if (!empty($invoiceId)) {
                // Invoice exists - check if paid or not
                return $invoiceStatus === 'paid' ? ['Invoiced', 'Paid'] : ['Invoiced', 'Invoiced'];
            }

            // No invoice exists - it's billable
            return ['Billable', 'Billable'];
        }

        if ($bookingStatus === 'Cancelled') {
            // For Cancelled bookings, classify based on invoice existence
            // billable_invoiced_status stays NULL for cancelled bookings
            return !empty($invoiceId) ? ['Invoiced', null] : ['Billable', null];
        }

        return ['Invoiced', null];
    }

    /**
     * Aggregate transaction data per booking (exactly like Transaction Summary Report logic)
     *
     * @param Collection $rawData
     * @param array $filters
     * @return array
     */
    private function aggregateTransactionData(Collection $rawData, array $filters = []): array
    {
        $sortedDataArray = [];

        // Charge Type filter (Tours/Custom/Add-ons) - empty means no scoping (current/default behavior).
        $chargeTypeFilter = $filters['chargeTypes'] ?? [];
        $chargeTypeFilterActive = !empty($chargeTypeFilter);
        $chargeTypeIncludesTours = in_array('tours', $chargeTypeFilter, true);

        foreach ($rawData as $value) {
            $bookingId = $value->tour_booking_id;
            $matchesChargeType = !$chargeTypeFilterActive || in_array($this->chargeTypeBucket($value->charge_type), $chargeTypeFilter, true);

            if (isset($sortedDataArray[$bookingId])) {
                // Booking already exists, aggregate amounts exactly like Transaction Summary Report
                if ($value->status == "Completed") {
                    // Track if any transaction has custom_charge (informational, independent of the Charge Type filter)
                    if ($value->charge_type === 'custom_charge') {
                        $sortedDataArray[$bookingId]['has_custom_charge'] = true;
                    }

                    if ($matchesChargeType) {
                        $sortedDataArray[$bookingId]['amount'] = ($sortedDataArray[$bookingId]['amount'] + $value->amount);
                        $sortedDataArray[$bookingId]['fee_amount'] = ($sortedDataArray[$bookingId]['fee_amount'] + $value->fee_amount);
                        $sortedDataArray[$bookingId]['has_matching_charge'] = true;
                        // Track completed amount per payment method so a booking that mixes
                        // methods (e.g. an OTA payment plus a later cash/card custom charge)
                        // can be labeled by whichever one actually contributed the most money.
                        $sortedDataArray[$bookingId]['via_totals'][$value->via] =
                            ($sortedDataArray[$bookingId]['via_totals'][$value->via] ?? 0) + $value->amount;
                    }
                }

                if ($value->status == "Refund" && $matchesChargeType) {
                    $sortedDataArray[$bookingId]['refund'] = ($sortedDataArray[$bookingId]['refund'] + $value->amount);
                    $sortedDataArray[$bookingId]['refund_fee_amount'] = ($sortedDataArray[$bookingId]['refund_fee_amount'] + $value->fee_amount);
                    $sortedDataArray[$bookingId]['refund_remove_fee'] = $sortedDataArray[$bookingId]['refund'] - $sortedDataArray[$bookingId]['refund_fee_amount'];
                    $sortedDataArray[$bookingId]['has_matching_charge'] = true;
                    // Net refunds back out of the same via's total (e.g. a custom charge
                    // that was charged then fully refunded shouldn't count towards that
                    // via's dominance - only money actually retained should). A "Don't
                    // Refund" removal stores a $0 Refund row here, so it's a no-op and the
                    // charge correctly stays counted as retained.
                    $sortedDataArray[$bookingId]['via_totals'][$value->via] =
                        ($sortedDataArray[$bookingId]['via_totals'][$value->via] ?? 0) - $value->amount;
                }

                $sortedDataArray[$bookingId]['net_total'] = number_format(
                    (float)$sortedDataArray[$bookingId]['amount'] - $sortedDataArray[$bookingId]['refund_remove_fee'] - $sortedDataArray[$bookingId]['fee_amount'],
                    2, '.', ''
                );
                $sortedDataArray[$bookingId]['net_total'] = round($sortedDataArray[$bookingId]['net_total'], 2);
            } else {
                // New booking, initialize data exactly like Transaction Summary Report
                $waiverStatus = $this->determineWaiverStatus($value->liability_waiver_signing);

                // Determine Invoice/Billable status (exactly like Transaction Summary Report)
                [$purchaseType, $billableInvoicedStatus] = $this->classifyPurchaseType(
                    $value->via,
                    $value->invoice_id ?? null,
                    $value->invoice_status ?? null,
                    $value->booking_status
                );

                $newFilteredData = [
                    'booking_id' => $value->tour_booking_id,
                    'discount' => $value->discount ?? 0,
                    'customer_name' => $value->customer_name,
                    'tourists' => (int)($value->tourists ?? 0) + (int)($value->infants ?? 0),
                    'purchase_type' => $purchaseType,
                    'charge_type' => $value->charge_type,
                    'has_custom_charge' => ($value->charge_type === 'custom_charge'),
                    'has_matching_charge' => false,
                    'booking_status' => $value->booking_status,
                    'tour_date' => date('m/d/Y', strtotime($value->tour_date)),
                    'created_date' => date('m/d/Y', strtotime($value->created_date)),
                    'purchase_date' => $value->purchase_date ? date('m/d/Y H:i:s', strtotime($value->purchase_date)) : null,
                    'time_from' => $value->time_from,
                    'is_reallocated' => $value->is_reallocated,

                    // Third party platform determination
                    'third_party_platform' => $this->determineThirdPartyPlatform($value),

                    // Store billable_invoiced_status for reference
                    'billable_invoiced_status' => $billableInvoicedStatus,
                    'invoice_id' => $value->invoice_id ?? null,
                    'invoice_status' => $value->invoice_status ?? null,

                    // Initialize all other fields from first transaction
                    'email' => $value->email,
                    'phone_number' => $value->phone_number,
                    'comments' => $value->comments,
                    'service_commission' => $value->service_commission,
                    'is_reserved_for_affiliate' => $value->is_reserved_for_affiliate,
                    'viator_bookingRef' => $value->viator_bookingRef,
                    'get_your_guide_reference_booking' => $value->get_your_guide_reference_booking,
                    'ota_reference_booking' => $value->ota_reference_booking ?? null,
                    'liability_waiver_signing' => $value->liability_waiver_signing,
                    'waiver_status' => $waiverStatus,
                    'customer_is_affiliate' => $value->customer_is_affiliate,
                    'customer_id' => $value->customer_id,
                    'affiliate_name' => $value->affiliate_name,
                    'time_to' => $value->time_to,
                    'package_name' => $value->package_name,
                    'check_in_status' => $value->check_in_status,
                ];

                // Handle unpaid bookings differently
                if ($value->via === 'Unpaid' && $value->status === 'Unpaid') {
                    // For unpaid bookings, amounts are 0 but we track booking and passenger counts
                    $newFilteredData['amount'] = 0;
                    $newFilteredData['fee_amount'] = 0;
                    $newFilteredData['refund'] = 0;
                    $newFilteredData['refund_fee_amount'] = 0;
                    $newFilteredData['refund_remove_fee'] = 0;
                    $newFilteredData['net_total'] = 0;
                } else {
                    // Initialize amounts exactly like Transaction Summary Report for paid bookings,
                    // scoped to the selected Charge Type(s) when that filter is active
                    if ($value->status == "Completed" && $matchesChargeType) {
                        $newFilteredData['amount'] = $value->amount;
                        $newFilteredData['fee_amount'] = $value->fee_amount;
                        $newFilteredData['has_matching_charge'] = true;
                        $newFilteredData['via_totals'] = [$value->via => $value->amount];
                    } else {
                        $newFilteredData['amount'] = 0;
                        $newFilteredData['fee_amount'] = 0;
                        $newFilteredData['via_totals'] = [];
                    }

                    if ($value->status == "Refund" && $matchesChargeType) {
                        $newFilteredData['refund'] = $value->amount;
                        $newFilteredData['refund_fee_amount'] = $value->fee_amount;
                        $newFilteredData['refund_remove_fee'] = $newFilteredData['refund'] - $newFilteredData['refund_fee_amount'];
                        $newFilteredData['has_matching_charge'] = true;
                        // See the matching comment in the "booking already exists" branch above -
                        // nets the refund back out of its own via's total (a $0 "Don't Refund" row
                        // is a no-op here too).
                        $newFilteredData['via_totals'][$value->via] =
                            ($newFilteredData['via_totals'][$value->via] ?? 0) - $value->amount;
                    } else {
                        $newFilteredData['refund'] = 0;
                        $newFilteredData['refund_fee_amount'] = 0;
                        $newFilteredData['refund_remove_fee'] = 0;
                    }

                    $newFilteredData['net_total'] = number_format(
                        (float)$newFilteredData['amount'] - $newFilteredData['refund_remove_fee'] - $newFilteredData['fee_amount'], 
                        2, '.', ''
                    );
                    $newFilteredData['net_total'] = round($newFilteredData['net_total'], 2);
                }

                $sortedDataArray[$bookingId] = $newFilteredData;
            }
        }

        // Dominant-via resolution: a booking can carry transactions from more than one
        // payment method (e.g. an OTA payment plus a later cash/card custom charge). The
        // loop above labels every booking with whichever transaction it happened to see
        // first, which is arbitrary when two transactions tie on sort order - and, when a
        // Charge Type filter is active, may not even be a transaction whose amount counts
        // towards what's displayed (e.g. the original tour payment when only "Custom" is
        // selected). Whenever the booking has ANY charge-type-scoped completed money
        // (via_totals non-empty), re-label it with whichever `via` contributed the most of
        // THAT scoped money, rather than the unscoped first-seen transaction. Only booking
        // rows with zero scoped money at all (nothing here to prefer over the original
        // label) are left untouched.
        foreach ($sortedDataArray as $bookingId => &$booking) {
            $viaTotals = $booking['via_totals'] ?? [];
            unset($booking['via_totals']);

            if (empty($viaTotals)) {
                continue;
            }

            arsort($viaTotals);
            $dominantVia = array_key_first($viaTotals);

            [$booking['purchase_type'], $booking['billable_invoiced_status']] = $this->classifyPurchaseType(
                $dominantVia,
                $booking['invoice_id'] ?? null,
                $booking['invoice_status'] ?? null,
                $booking['booking_status'] ?? null
            );
        }
        unset($booking);

        // Charge Type exclusion: when Custom and/or Add-ons are selected without Tours,
        // a booking must have at least one matching transaction to appear at all. When Tours
        // is selected (alone or combined), no booking is ever dropped - every booking always
        // has a tours-bucket row (a real payment or the synthetic $0 unpaid placeholder), only
        // its amount is scoped above.
        if ($chargeTypeFilterActive && !$chargeTypeIncludesTours) {
            $sortedDataArray = array_filter($sortedDataArray, fn($booking) => !empty($booking['has_matching_charge']));
        }

        // Fetch rate components for all bookings in a single query
        $bookingIds = array_keys($sortedDataArray);

        $rateComponents = $this->fetchRateComponentsForBookings($bookingIds);

        // Append rate components to each booking
        foreach ($sortedDataArray as $bookingId => &$booking) {
            if (isset($rateComponents[$bookingId])) {
                // Store the array of rate breakdowns
                $booking['rate_breakdown'] = $rateComponents[$bookingId];

                // Also calculate totals for backward compatibility
                $totalRate = 0;
                $totalTax = 0;
                $totalPermitFee = 0;
                $rateForList = [];

                // For per-person display: collect unique rate values
                $uniqueRates = [];
                $uniqueTaxes = [];
                $uniquePermitFees = [];

                foreach ($rateComponents[$bookingId] as $rateData) {
                    // Skip rate groups with 0 passengers
                    if ($rateData['pax'] <= 0) {
                        continue;
                    }

                    // The rate, tax, permit_fee in the data are ALREADY per-person values
                    // So we need to multiply by pax to get totals
                    $perPersonRate = (float) $rateData['rate'];
                    $perPersonTax = (float) $rateData['tax'];
                    $perPersonPermitFee = (float) $rateData['permit_fee'];
                    $pax = (int) $rateData['pax'];

                    $totalRate += ($perPersonRate * $pax);
                    $totalTax += ($perPersonTax * $pax);
                    $totalPermitFee += ($perPersonPermitFee * $pax);
                    $rateForList[] = $rateData['rate_for'];

                    // Collect unique per-person values
                    $uniqueRates[] = round($perPersonRate, 2);
                    $uniqueTaxes[] = round($perPersonTax, 2);
                    $uniquePermitFees[] = round($perPersonPermitFee, 2);
                }

                $booking['rate'] = round($totalRate, 2);
                $booking['tax'] = round($totalTax, 2);
                $booking['permit_fee'] = round($totalPermitFee, 2);
                $booking['rate_for'] = implode(', ', array_unique($rateForList));

                // Add per-person display values
                // If all rates are the same, show single value; otherwise show range or list
                $uniqueRates = array_unique($uniqueRates);
                $uniqueTaxes = array_unique($uniqueTaxes);
                $uniquePermitFees = array_unique($uniquePermitFees);

                // FILTER-AWARE DISPLAY: If a rate/tax/permitFee filter is active, prioritize showing that value
                $filteredRate = isset($filters['rate']) ? round((float)$filters['rate'], 2) : null;
                $filteredTax = isset($filters['tax']) ? round((float)$filters['tax'], 2) : null;
                $filteredPermitFee = isset($filters['permitFee']) ? round((float)$filters['permitFee'], 2) : null;

                // Rate display logic
                if (empty($uniqueRates)) {
                    $booking['pax_rate'] = 0;
                } elseif ($filteredRate !== null && in_array($filteredRate, $uniqueRates)) {
                    // Show the filtered rate value (the one that matched the filter)
                    $booking['pax_rate'] = $filteredRate;
                } elseif (count($uniqueRates) === 1) {
                    $booking['pax_rate'] = reset($uniqueRates);
                } else {
                    // Show highest rate for clear data
                    $booking['pax_rate'] = max($uniqueRates);
                }

                // Tax display logic
                if (empty($uniqueTaxes)) {
                    $booking['pax_tax'] = 0;
                } elseif ($filteredTax !== null && in_array($filteredTax, $uniqueTaxes)) {
                    // Show the filtered tax value (the one that matched the filter)
                    $booking['pax_tax'] = $filteredTax;
                } elseif (count($uniqueTaxes) === 1) {
                    $booking['pax_tax'] = reset($uniqueTaxes);
                } else {
                    // Show highest tax for clear data
                    $booking['pax_tax'] = max($uniqueTaxes);
                }

                // Permit fee display logic
                if (empty($uniquePermitFees)) {
                    $booking['pax_permit_fee'] = 0;
                } elseif ($filteredPermitFee !== null && in_array($filteredPermitFee, $uniquePermitFees)) {
                    // Show the filtered permit fee value (the one that matched the filter)
                    $booking['pax_permit_fee'] = $filteredPermitFee;
                } elseif (count($uniquePermitFees) === 1) {
                    $booking['pax_permit_fee'] = reset($uniquePermitFees);
                } else {
                    // Show highest permit fee for clear data
                    $booking['pax_permit_fee'] = max($uniquePermitFees);
                }
            } else {
                // Default values if no rate data found
                $booking['rate_breakdown'] = [];
                $booking['rate'] = 0;
                $booking['tax'] = 0;
                $booking['permit_fee'] = 0;
                $booking['rate_for'] = 'Unknown';
                $booking['pax_rate'] = 0;
                $booking['pax_tax'] = 0;
                $booking['pax_permit_fee'] = 0;
            }
        }
        unset($booking); // Break the reference

        // Convert to objects for consistency with the service layer
        $result = [];
        foreach ($sortedDataArray as $bookingData) {
            $result[] = (object) $bookingData;
        }

        return $result;
    }

    /**
     * Fetch rate components for each booking individually
     *
     * @param array $bookingIds
     * @return array
     */
    private function fetchRateComponentsForBookings(array $bookingIds): array
    {
        if (empty($bookingIds)) {
            return [];
        }

        // Use chunking to avoid "too many placeholders" error
        $chunkSize = 500;
        $result = [];

        foreach (array_chunk($bookingIds, $chunkSize) as $chunkIndex => $bookingIdChunk) {
            try {
                // Build [transact_id => unpaid_count] for pax added via Change People
                // whose payment link is still Unpaid — exclude from rate-group totals.
                $unpaidPaxByTransactId = UnpaidPaxService::getUnpaidPaxByTransactId($bookingIdChunk);

                // Fetch transaction records for this chunk of bookings
                // Each row represents one rate group with multiple passengers
                $transactions = DB::table('tour_bookings_transact as tbt')
                    ->leftJoin('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                    ->whereIn('tbt.tour_booking_id', $bookingIdChunk)
                    ->where('tbt.deleted_by', 0)
                    ->select(
                        'tbt.id',
                        'tbt.tour_booking_id',
                        'tbt.tour_package_rate_id',
                        'tpr.rate_for',
                        'tpr.mapping_key',
                        'tbt.rate',
                        'tbt.additional_charge as tax',
                        'tbt.permit_fee',
                        'tbt.tourists',
                        'tbt.infants'
                    )
                    ->get();

                // Group transactions by booking_id
                foreach ($transactions as $transaction) {
                    $bookingId = $transaction->tour_booking_id;

                    if (!isset($result[$bookingId])) {
                        $result[$bookingId] = [];
                    }

                    // Subtract pax that are still unpaid via a pending change_people payment link.
                    $unpaidPax = $unpaidPaxByTransactId[(int)$transaction->id] ?? 0;
                    // Calculate total pax for this rate group (tourists + infants), excluding unpaid pax
                    $paxCount = max(0, (int)$transaction->tourists + (int)$transaction->infants - $unpaidPax);

                    $result[$bookingId][] = [
                        'rate_id' => $transaction->tour_package_rate_id,
                        'rate_for' => $transaction->rate_for ?? 'Unknown',
                        'mapping_key' => $transaction->mapping_key ?? 0,
                        'rate' => (float) $transaction->rate,
                        'tax' => (float) $transaction->tax,
                        'permit_fee' => (float) $transaction->permit_fee,
                        'pax' => $paxCount // Total passengers for this rate group
                    ];
                }
            } catch (Exception $chunkException) {
                Log::warning("PaymentBreakdownRepository::fetchRateComponentsForBookings Chunk {$chunkIndex} Error: " . $chunkException->getMessage());
                continue;
            }
        }

        return $result;
    }

    /**
     * Get financial summary totals (matching Transaction Summary Report)
     *
     * @param array $filters
     * @return array
     */
    public function getFinancialSummaryTotals(array $filters): array
    {
        try {
            // Add error handling for unpaid booking queries
            try {
                $rawData = $this->buildMainQuery($filters)->get();
            } catch (Exception $queryException) {
                Log::error('PaymentBreakdownRepository::getFinancialSummaryTotals Query Error: ' . $queryException->getMessage(), [
                    'filters' => $filters,
                    'query_type' => $this->determineQueryType($filters)
                ]);
                
                // Fallback to paid bookings only if unpaid query fails
                if ($this->determineQueryType($filters) !== 'paid_only') {
                    Log::warning('Falling back to paid bookings only for financial totals due to query error');
                    $fallbackFilters = $filters;
                    $fallbackFilters['paymentTypes'] = array_diff($filters['paymentTypes'] ?? [], ['Unpaid']);
                    $rawData = $this->buildPaidBookingsQuery($fallbackFilters)->get();
                } else {
                    throw $queryException;
                }
            }
            
            $aggregatedData = $this->aggregateTransactionData($rawData, $filters);
            
            $totals = [
                'total_bookings' => count($aggregatedData),
                'total_tourists' => 0,
                'total_payment' => 0,
                'total_refund' => 0,
                'total_fees' => 0,
                'total_discount' => 0,
                'total_net' => 0
            ];
            
            foreach ($aggregatedData as $booking) {
                $totals['total_tourists'] += $booking->tourists;
                $totals['total_payment'] += $booking->amount;
                $totals['total_refund'] += $booking->refund;
                $totals['total_fees'] += $booking->fee_amount;
                $totals['total_discount'] += $booking->discount;
                $totals['total_net'] += $booking->net_total;
            }
            
            return $totals;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getFinancialSummaryTotals Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get payment type totals
     *
     * @param array $filters
     * @return array
     */
    public function getPaymentTypeTotals(array $filters): array
    {
        try {
            // Check if unpaid bookings are requested
            $paymentTypes = $filters['paymentTypes'] ?? [];
            $includeUnpaid = in_array('Unpaid', $paymentTypes);
            $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));
            
            $totals = [];
            
            // Get paid booking totals if requested
            if ($includeOtherTypes || empty($paymentTypes)) {
                try {
                    $paidQuery = $this->buildPaidBookingsQuery($filters);
                    
                    $paidTotals = $paidQuery->selectRaw('
                        t.via as payment_type,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        SUM(CASE WHEN t.status = "Completed" THEN t.amount ELSE 0 END) as total_amount,
                        SUM(CASE WHEN t.status = "Refund" THEN t.amount ELSE 0 END) as refund_amount,
                        SUM(CASE WHEN t.status = "Completed" THEN t.amount ELSE 0 END) - 
                        SUM(CASE WHEN t.status = "Refund" THEN t.amount ELSE 0 END) as net_amount
                    ')
                    ->groupBy('t.via')
                    ->get()
                    ->keyBy('payment_type')
                    ->toArray();
                    
                    $totals = array_merge($totals, $paidTotals);
                } catch (Exception $paidQueryException) {
                    Log::error('PaymentBreakdownRepository::getPaymentTypeTotals Paid Query Error: ' . $paidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue with unpaid bookings if paid query fails
                }
            }
            
            // Get unpaid booking totals if requested
            if ($includeUnpaid) {
                try {
                    $unpaidQuery = $this->buildUnpaidBookingsQuery($filters);
                    
                    $unpaidTotals = $unpaidQuery->selectRaw('
                        "Unpaid" as payment_type,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        0 as total_amount,
                        0 as refund_amount,
                        0 as net_amount
                    ')
                    ->first();
                    
                    if ($unpaidTotals && $unpaidTotals->booking_count > 0) {
                        $totals['Unpaid'] = (array) $unpaidTotals;
                    }
                } catch (Exception $unpaidQueryException) {
                    Log::error('PaymentBreakdownRepository::getPaymentTypeTotals Unpaid Query Error: ' . $unpaidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue without unpaid bookings if unpaid query fails
                }
            }

            return $totals;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getPaymentTypeTotals Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get affiliate breakdown data
     *
     * @param array $filters
     * @return Collection
     */
    public function getAffiliateBreakdown(array $filters): Collection
    {
        try {
            // Check if unpaid bookings are requested
            $paymentTypes = $filters['paymentTypes'] ?? [];
            $includeUnpaid = in_array('Unpaid', $paymentTypes);
            $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));
            
            $results = collect();
            
            // Get paid booking affiliate data if requested
            if ($includeOtherTypes || empty($paymentTypes)) {
                try {
                    $paidQuery = $this->buildPaidBookingsQuery($filters);
                    
                    $paidAffiliateData = $paidQuery->selectRaw('
                        c.name as affiliate_name,
                        c.id as affiliate_id,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        SUM(CASE WHEN t.status = "Completed" THEN t.amount ELSE 0 END) as total_amount,
                        AVG(tbm.service_commission) as avg_commission_rate,
                        SUM(CASE WHEN t.status = "Completed" THEN t.amount ELSE 0 END * (tbm.service_commission / 100)) as total_commission
                    ')
                    ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                    ->where('c.is_affiliate', 'Yes')
                    ->groupBy('c.id', 'c.name')
                    ->get();
                    
                    $results = $results->merge($paidAffiliateData);
                } catch (Exception $paidQueryException) {
                    Log::error('PaymentBreakdownRepository::getAffiliateBreakdown Paid Query Error: ' . $paidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue with unpaid bookings if paid query fails
                }
            }
            
            // Get unpaid booking affiliate data if requested
            if ($includeUnpaid) {
                try {
                    $unpaidQuery = $this->buildUnpaidBookingsQuery($filters);
                    
                    $unpaidAffiliateData = $unpaidQuery->selectRaw('
                        c.name as affiliate_name,
                        c.id as affiliate_id,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        0 as total_amount,
                        AVG(tbm.service_commission) as avg_commission_rate,
                        0 as total_commission
                    ')
                    ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                    ->where('c.is_affiliate', 'Yes')
                    ->groupBy('c.id', 'c.name')
                    ->get();
                    
                    // Merge unpaid data with paid data by affiliate
                    foreach ($unpaidAffiliateData as $unpaidAffiliate) {
                        $existingAffiliate = $results->firstWhere('affiliate_id', $unpaidAffiliate->affiliate_id);
                        if ($existingAffiliate) {
                            // Merge with existing affiliate data
                            $existingAffiliate->booking_count += $unpaidAffiliate->booking_count;
                            $existingAffiliate->total_pax += $unpaidAffiliate->total_pax;
                            // total_amount and total_commission remain the same (unpaid = 0)
                        } else {
                            // Add new unpaid affiliate
                            $results->push($unpaidAffiliate);
                        }
                    }
                } catch (Exception $unpaidQueryException) {
                    Log::error('PaymentBreakdownRepository::getAffiliateBreakdown Unpaid Query Error: ' . $unpaidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue without unpaid bookings if unpaid query fails
                }
            }
            
            return $results;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getAffiliateBreakdown Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get third-party platform bookings
     *
     * @param array $filters
     * @return Collection
     */
    public function getThirdPartyBookings(array $filters): Collection
    {
        try {
            // Check if unpaid bookings are requested
            $paymentTypes = $filters['paymentTypes'] ?? [];
            $includeUnpaid = in_array('Unpaid', $paymentTypes);
            $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));

            $results = collect();

            // Dynamic platform CASE, sourced from config('constants.otas') — reused verbatim
            // for both selectRaw and groupByRaw calls below (paid and unpaid) so the grouped
            // expression text matches under MySQL's ONLY_FULL_GROUP_BY mode.
            [$otaCaseSql, $otaCaseBindings] = $this->buildOtaPlatformCaseSql();

            // Get paid booking third-party data if requested
            if ($includeOtherTypes || empty($paymentTypes)) {
                try {
                    $paidQuery = $this->buildPaidBookingsQuery($filters);

                    $paidThirdPartyData = $paidQuery->selectRaw("
                        {$otaCaseSql} as platform,
                        tbm.viator_bookingRef,
                        tbm.get_your_guide_reference_booking,
                        tbm.ota_reference_booking,
                        tbm.comments as fareharbor_reference,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        SUM(CASE WHEN t.status = \"Completed\" THEN t.amount ELSE 0 END) as total_amount,
                        0 as avg_commission_rate
                    ", $otaCaseBindings)
                    ->whereRaw('(
                        tbm.ota_reference_booking IS NOT NULL OR
                        tbm.viator_bookingRef IS NOT NULL OR
                        tbm.get_your_guide_reference_booking IS NOT NULL OR
                        tbm.comments LIKE "#%"
                    )')
                    ->groupByRaw($otaCaseSql, $otaCaseBindings)
                    ->get();
                    
                    $results = $results->merge($paidThirdPartyData);
                } catch (Exception $paidQueryException) {
                    Log::error('PaymentBreakdownRepository::getThirdPartyBookings Paid Query Error: ' . $paidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue with unpaid bookings if paid query fails
                }
            }
            
            // Get unpaid booking third-party data if requested
            if ($includeUnpaid) {
                try {
                    $unpaidQuery = $this->buildUnpaidBookingsQuery($filters);
                    
                    $unpaidThirdPartyData = $unpaidQuery->selectRaw("
                        {$otaCaseSql} as platform,
                        tbm.viator_bookingRef,
                        tbm.get_your_guide_reference_booking,
                        tbm.ota_reference_booking,
                        tbm.comments as fareharbor_reference,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax,
                        0 as total_amount,
                        0 as avg_commission_rate
                    ", $otaCaseBindings)
                    ->whereRaw('(
                        tbm.ota_reference_booking IS NOT NULL OR
                        tbm.viator_bookingRef IS NOT NULL OR
                        tbm.get_your_guide_reference_booking IS NOT NULL OR
                        tbm.comments LIKE "#%"
                    )')
                    ->groupByRaw($otaCaseSql, $otaCaseBindings)
                    ->get();
                    
                    // Merge unpaid data with paid data by platform
                    foreach ($unpaidThirdPartyData as $unpaidPlatform) {
                        $existingPlatform = $results->firstWhere('platform', $unpaidPlatform->platform);
                        if ($existingPlatform) {
                            // Merge with existing platform data
                            $existingPlatform->booking_count += $unpaidPlatform->booking_count;
                            $existingPlatform->total_pax += $unpaidPlatform->total_pax;
                            // total_amount remains the same (unpaid = 0)
                        } else {
                            // Add new unpaid platform
                            $results->push($unpaidPlatform);
                        }
                    }
                } catch (Exception $unpaidQueryException) {
                    Log::error('PaymentBreakdownRepository::getThirdPartyBookings Unpaid Query Error: ' . $unpaidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue without unpaid bookings if unpaid query fails
                }
            }
            
            return $results;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getThirdPartyBookings Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get check-in status data for all bookings (single query, multiple uses)
     * Returns both aggregated status counts and individual booking data
     *
     * @param array $filters
     * @param Collection|null $aggregatedData Optional pre-fetched aggregated data to avoid re-querying
     * @return array ['statusCounts' => array, 'individualData' => array]
     */
    public function getCheckInStatusDataCombined(array $filters, $aggregatedData = null): array
    {
        try {
            // Use provided aggregated data or fetch it
            if ($aggregatedData === null) {
                $rawData = $this->buildMainQuery($filters)->get();
                $aggregatedData = $this->aggregateTransactionData($rawData, $filters);
            } else {
                // Convert to array if it's a collection
                $aggregatedData = is_array($aggregatedData) ? $aggregatedData : $aggregatedData->toArray();
            }

            if (empty($aggregatedData)) {
                return [
                    'statusCounts' => [
                        'Not Check-In' => ['count' => 0, 'pax' => 0],
                        '1st Check-in' => ['count' => 0, 'pax' => 0],
                        '2nd Check-in' => ['count' => 0, 'pax' => 0],
                        'Absent' => ['count' => 0, 'pax' => 0]
                    ],
                    'individualData' => []
                ];
            }

            // Extract booking IDs from aggregated data
            $bookingIds = [];
            foreach ($aggregatedData as $booking) {
                $bookingObj = is_object($booking) ? $booking : (object) $booking;
                $bookingIds[] = $bookingObj->booking_id;
            }

            // SINGLE QUERY: Fetch check-in data once for all bookings
            $chunkSize = 500;
            $checkInDataMap = [];

            foreach (array_chunk($bookingIds, $chunkSize) as $chunkIndex => $bookingIdChunk) {
                try {
                    // Single query to get check-in data for this chunk, keyed by rate group
                    $checkInQuery = DB::table('tour_bookings_transact as tbt')
                        ->whereIn('tbt.tour_booking_id', $bookingIdChunk)
                        ->where(function($q) {
                            $q->whereNull('tbt.deleted_by')
                              ->orWhere('tbt.deleted_by', 0);
                        })
                        ->whereNotNull('tbt.check_in_status')
                        ->where('tbt.check_in_status', '!=', '')
                        ->select([
                            'tbt.tour_booking_id as booking_id',
                            'tbt.tour_package_rate_id as rate_id',
                            'tbt.check_in_status'
                        ])
                        ->get();

                    // Map check-in data by booking ID → rate ID → persons array
                    foreach ($checkInQuery as $row) {
                        $bookingId = $row->booking_id;
                        $rateId = $row->rate_id;

                        if (!empty($row->check_in_status)) {
                            if (!isset($checkInDataMap[$bookingId])) {
                                $checkInDataMap[$bookingId] = [];
                            }

                            $checkInArray = json_decode($row->check_in_status, true);

                            if (is_array($checkInArray)) {
                                if (!isset($checkInDataMap[$bookingId][$rateId])) {
                                    $checkInDataMap[$bookingId][$rateId] = [];
                                }
                                $checkInDataMap[$bookingId][$rateId] = array_merge(
                                    $checkInDataMap[$bookingId][$rateId],
                                    $checkInArray
                                );
                            }
                        }
                    }
                } catch (Exception $chunkException) {
                    Log::warning("PaymentBreakdownRepository::getCheckInStatusDataCombined Chunk {$chunkIndex} Error: " . $chunkException->getMessage());
                    continue;
                }
            }

            // Status mapping
            $statuses = [
                "1" => '1st Check-in',
                "2" => '2nd Check-in',
                "N" => 'Not Check-In',
                "A" => 'Absent'
            ];

            // Initialize counters for aggregated status counts
            $statusCounts = [
                'Not Check-In' => ['count' => 0, 'pax' => 0],
                '1st Check-in' => ['count' => 0, 'pax' => 0],
                '2nd Check-in' => ['count' => 0, 'pax' => 0],
                'Absent' => ['count' => 0, 'pax' => 0]
            ];

            // Initialize individual booking data
            $individualData = [];

            // Process each aggregated booking ONCE to populate both results
            foreach ($aggregatedData as $aggregatedBooking) {
                $bookingObj = is_object($aggregatedBooking) ? $aggregatedBooking : (object) $aggregatedBooking;
                $bookingId = $bookingObj->booking_id;
                $totalPax = (int) $bookingObj->tourists;

                // Skip if no pax
                if ($totalPax <= 0) {
                    continue;
                }

                // Get check-in status for this booking
                $checkInStatus = $checkInDataMap[$bookingId] ?? null;

                // Initialize individual booking data
                $overallStatus = 'Not Check-In';
                $checkedInCount = 0;
                $statusBreakdown = [];
                $rateStatusBreakdown = [];

                if (!empty($checkInStatus) && is_array($checkInStatus)) {
                    // checkInStatus is [$rateId => [...persons...]]
                    $bookingStatusCounts = [
                        '1st Check-in' => 0,
                        '2nd Check-in' => 0,
                        'Not Check-In' => 0,
                        'Absent' => 0
                    ];

                    foreach ($checkInStatus as $rateId => $persons) {
                        $rateBreakdown = [
                            '1st Check-in' => 0,
                            '2nd Check-in' => 0,
                            'Not Check-In' => 0,
                            'Absent' => 0
                        ];

                        foreach ($persons as $person) {
                            if (isset($person['status'])) {
                                $status = $statuses[$person['status']] ?? 'Not Check-In';
                                $bookingStatusCounts[$status]++;
                                $rateBreakdown[$status]++;
                                $checkedInCount++;
                                $statusCounts[$status]['pax']++;
                            } else {
                                $bookingStatusCounts['Not Check-In']++;
                                $rateBreakdown['Not Check-In']++;
                                $statusCounts['Not Check-In']['pax']++;
                            }
                        }

                        $rateStatusBreakdown[$rateId] = $rateBreakdown;
                    }

                    if (!empty($bookingStatusCounts['1st Check-in'] + $bookingStatusCounts['2nd Check-in'] + $bookingStatusCounts['Not Check-In'] + $bookingStatusCounts['Absent'])) {
                        // Handle case where JSON has fewer entries than total pax
                        if ($checkedInCount < $totalPax) {
                            $additionalPax = $totalPax - $checkedInCount;
                            $bookingStatusCounts['Not Check-In'] += $additionalPax;
                            $statusCounts['Not Check-In']['pax'] += $additionalPax;
                        }

                        // Determine overall status for individual booking
                        if ($bookingStatusCounts['2nd Check-in'] > 0) {
                            $overallStatus = '2nd Check-in';
                        } elseif ($bookingStatusCounts['1st Check-in'] > 0) {
                            $overallStatus = '1st Check-in';
                        } elseif ($bookingStatusCounts['Absent'] > 0) {
                            $overallStatus = 'Absent';
                        } else {
                            $overallStatus = 'Not Check-In';
                        }

                        $statusCounts[$overallStatus]['count']++;
                        $statusBreakdown = $bookingStatusCounts;
                    } else {
                        $statusCounts['Not Check-In']['count']++;
                        $statusCounts['Not Check-In']['pax'] += $totalPax;
                    }
                } else {
                    // No check-in data
                    $statusCounts['Not Check-In']['count']++;
                    $statusCounts['Not Check-In']['pax'] += $totalPax;
                }

                // Store individual booking data
                $individualData[$bookingId] = [
                    'overall_status' => $overallStatus,
                    'total_pax' => $totalPax,
                    'checked_in_count' => $checkedInCount,
                    'status_breakdown' => $statusBreakdown,
                    'rate_status_breakdown' => $rateStatusBreakdown
                ];
            }

            return [
                'statusCounts' => $statusCounts,
                'individualData' => $individualData
            ];
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getCheckInStatusDataCombined Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get waiver status data
     *
     * @param array $filters
     * @return array
     */
    public function getWaiverStatusData(array $filters): array
    {
        try {
            // Check if unpaid bookings are requested
            $paymentTypes = $filters['paymentTypes'] ?? [];
            $includeUnpaid = in_array('Unpaid', $paymentTypes);
            $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));
            
            $result = [
                'Signed' => ['count' => 0, 'pax' => 0],
                'Not Signed' => ['count' => 0, 'pax' => 0]
            ];
            
            // Get paid booking waiver data if requested
            if ($includeOtherTypes || empty($paymentTypes)) {
                try {
                    $paidQuery = $this->buildPaidBookingsQuery($filters);
                    
                    $paidWaiverData = $paidQuery->selectRaw('
                        CASE 
                            WHEN tbm.liability_waiver_signing IS NOT NULL AND tbm.liability_waiver_signing != "" THEN "Signed"
                            ELSE "Not Signed"
                        END as waiver_status,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax
                    ')
                    ->groupByRaw('
                        CASE 
                            WHEN tbm.liability_waiver_signing IS NOT NULL AND tbm.liability_waiver_signing != "" THEN "Signed"
                            ELSE "Not Signed"
                        END
                    ')
                    ->get()
                    ->keyBy('waiver_status');
                    
                    foreach ($paidWaiverData as $status => $data) {
                        if (isset($result[$status])) {
                            $result[$status]['count'] += $data->booking_count;
                            $result[$status]['pax'] += $data->total_pax;
                        }
                    }
                } catch (Exception $paidQueryException) {
                    Log::error('PaymentBreakdownRepository::getWaiverStatusData Paid Query Error: ' . $paidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue with unpaid bookings if paid query fails
                }
            }
            
            // Get unpaid booking waiver data if requested
            if ($includeUnpaid) {
                try {
                    $unpaidQuery = $this->buildUnpaidBookingsQuery($filters);
                    
                    $unpaidWaiverData = $unpaidQuery->selectRaw('
                        CASE 
                            WHEN tbm.liability_waiver_signing IS NOT NULL AND tbm.liability_waiver_signing != "" THEN "Signed"
                            ELSE "Not Signed"
                        END as waiver_status,
                        COUNT(DISTINCT tbm.id) as booking_count,
                        SUM(tbm.tourists + COALESCE(tbm.infants, 0)) as total_pax
                    ')
                    ->groupByRaw('
                        CASE 
                            WHEN tbm.liability_waiver_signing IS NOT NULL AND tbm.liability_waiver_signing != "" THEN "Signed"
                            ELSE "Not Signed"
                        END
                    ')
                    ->get()
                    ->keyBy('waiver_status');
                    
                    foreach ($unpaidWaiverData as $status => $data) {
                        if (isset($result[$status])) {
                            $result[$status]['count'] += $data->booking_count;
                            $result[$status]['pax'] += $data->total_pax;
                        }
                    }
                } catch (Exception $unpaidQueryException) {
                    Log::error('PaymentBreakdownRepository::getWaiverStatusData Unpaid Query Error: ' . $unpaidQueryException->getMessage(), [
                        'filters' => $filters
                    ]);
                    // Continue without unpaid bookings if unpaid query fails
                }
            }

            return $result;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getWaiverStatusData Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get tour packages for filter dropdown with tenant isolation
     *
     * @return Collection
     */
    public function getTourPackages(): Collection
    {
        try {
            $query = DB::table('tour_packages')
                ->select('id', 'name')
                ->where('deleted_by', 0);

            return $query->orderBy('name')->get();
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getTourPackages Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Get affiliates for filter dropdown with tenant isolation
     *
     * @return Collection
     */
    public function getAffiliates(): Collection
    {
        try {
            $query = DB::table('customers as c')
                ->select('c.id', 'c.name')
                ->join('tour_bookings_master as tbm', 'tbm.customer_id', '=', 'c.id')
                ->where('c.is_affiliate', 'Yes')
                ->where('c.deleted_by', 0);

            return $query->distinct()
                        ->orderBy('c.name')
                        ->get();
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getAffiliates Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Validate data consistency across tables
     *
     * @param array $processedData
     * @return array
     */
    public function validateDataConsistency(array $processedData): array
    {
        try {
            $validationResults = [
                'pax_count_consistency' => true,
                'payment_amount_consistency' => true,
                'missing_data_flags' => [],
                'calculation_errors' => []
            ];

            // Validate pax count consistency
            if (isset($processedData['bookingDetails'])) {
                foreach ($processedData['bookingDetails'] as $booking) {
                    $expectedPax = (int) ($booking['tourists'] ?? 0) + (int) ($booking['infants'] ?? 0);
                    if ($expectedPax <= 0) {
                        $validationResults['pax_count_consistency'] = false;
                        $validationResults['calculation_errors'][] = "Invalid pax count for booking {$booking['booking_id']}";
                    }
                }
            }

            // Validate payment amount consistency
            if (isset($processedData['paymentTypes'])) {
                foreach ($processedData['paymentTypes'] as $paymentType => $typeData) {
                    if ($typeData['total_amount'] < 0) {
                        $validationResults['payment_amount_consistency'] = false;
                        $validationResults['calculation_errors'][] = "Negative amount for payment type {$paymentType}";
                    }
                }
            }

            return $validationResults;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::validateDataConsistency Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Build the main query for payment breakdown data (matching Transaction Summary Report exactly)
     *
     * @param array $filters
     * @return Builder
     */
    private function buildMainQuery(array $filters)
    {
        // Check if unpaid bookings are requested
        $paymentTypes = $filters['paymentTypes'] ?? [];
        $includeUnpaid = in_array('Unpaid', $paymentTypes);
        $includeOtherTypes = !empty(array_diff($paymentTypes, ['Unpaid']));
        
        // If only unpaid bookings are requested
        if ($includeUnpaid && !$includeOtherTypes) {
            return $this->buildUnpaidBookingsQuery($filters);
        }
        
        // If both paid and unpaid bookings are requested
        if ($includeUnpaid && $includeOtherTypes) {
            return $this->combineQueriesWithUnion($filters);
        }
        
        // Default: only paid bookings (existing logic)
        return $this->buildPaidBookingsQuery($filters);
    }

    /**
     * Build query for paid bookings (existing logic)
     *
     * @param array $filters
     * @return Builder
     */
    private function buildPaidBookingsQuery(array $filters)
    {
        $query = DB::table('tour_bookings_master as tbm')
            ->join('transactions as t', function($join) {
                $join->on('t.tour_booking_id', '=', 'tbm.id')
                     ->where('t.status', '!=', 'Failed');
            })
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->leftJoin('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->select([
                // Core transaction fields (like Transaction Summary Report)
                't.amount',
                't.fee_amount',
                't.via',
                't.status',
                't.charge_type',
                't.tour_booking_id',
                't.is_reallocated',
                't.created_at as purchase_date',

                // Core booking fields
                'tbm.tour_package_id',
                'tbm.discount2_value as discount',
                'tbm.name as customer_name',
                'tbm.infants',
                'tbm.tourists',
                'tbm.status as booking_status',
                'tbm.tour_slot_id',
                'tbm.email',
                'tbm.phone_number',
                'tbm.created_at as created_date',
                'tbm.comments',
                'tbm.service_commission',
                'tbm.is_reserved_for_affiliate',
                'tbm.viator_bookingRef',
                'tbm.get_your_guide_reference_booking',
                'tbm.ota_reference_booking',
                'tbm.liability_waiver_signing',

                // Customer details
                'tbm.customer_id',
                'c.is_affiliate as customer_is_affiliate',
                'c.name as affiliate_name',

                // Tour details
                'tst.date as tour_date',
                'tst.time_from',
                'tst.time_to',
                'tp.name as package_name',

                // Invoice details — correlated subqueries use invoice_bookings.tour_booking_id index.
                // A leftJoinSub here materialised ~661K invoice_bookings rows (worse).
                DB::raw('(SELECT MAX(invoice_id) FROM invoice_bookings WHERE tour_booking_id = tbm.id) AS invoice_id'),
                DB::raw('(SELECT status FROM invoices WHERE id = (SELECT MAX(invoice_id) FROM invoice_bookings WHERE tour_booking_id = tbm.id)) AS invoice_status'),

                // Default values for fields that would come from tour_bookings_transact (retrieved separately)
                DB::raw('NULL as check_in_status'),
                DB::raw('NULL as rate'),
                DB::raw('NULL as tax'),
                DB::raw('NULL as permit_fee'),
            ]);

        // Apply filters (excluding payment type filter for paid bookings)
        $this->applyFilters($query, $filters, 'paid');

        return $query->orderBy('tst.date', 'DESC')
                    ->orderBy('tbm.created_at', 'DESC');
    }

    /**
     * Build query for unpaid bookings (bookings without transactions)
     *
     * @param array $filters
     * @return Builder
     */
    private function buildUnpaidBookingsQuery(array $filters)
    {
        $query = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->leftJoin('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->leftJoin('customers as c', 'c.id', '=', 'tbm.customer_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('tour_booking_id', 'tbm.id')
                    ->where('status', '!=', 'Failed');
            })
            ->select([
                // Core transaction fields (set to defaults for unpaid)
                DB::raw('0 as amount'),
                DB::raw('0 as fee_amount'), 
                DB::raw('"Unpaid" as via'), 
                DB::raw('"Unpaid" as status'), 
                DB::raw('NULL as charge_type'), 
                'tbm.id as tour_booking_id',
                DB::raw('0 as is_reallocated'), // Unpaid bookings are never reallocated - MUST be in same position as paid query
                DB::raw('NULL as purchase_date'),
                
                // Core booking fields
                'tbm.tour_package_id', 
                'tbm.discount2_value as discount', 
                'tbm.name as customer_name',
                'tbm.infants', 
                'tbm.tourists', 
                'tbm.status as booking_status', 
                'tbm.tour_slot_id',
                'tbm.email',
                'tbm.phone_number',
                'tbm.created_at as created_date',
                'tbm.comments',
                'tbm.service_commission',
                'tbm.is_reserved_for_affiliate',
                'tbm.viator_bookingRef',
                'tbm.get_your_guide_reference_booking',
                'tbm.ota_reference_booking',
                'tbm.liability_waiver_signing',
                
                // Customer details
                'tbm.customer_id',
                'c.is_affiliate as customer_is_affiliate',
                'c.name as affiliate_name',
                
                // Tour details
                'tst.date as tour_date',
                'tst.time_from',
                'tst.time_to',
                'tp.name as package_name',
                
                // Invoice details (NULL for unpaid bookings to match paid query structure)
                DB::raw('NULL as invoice_id'),
                DB::raw('NULL as invoice_status'),
                
                // Default values for fields that would come from tour_bookings_transact (retrieved separately)
                DB::raw('NULL as check_in_status'),
                DB::raw('NULL as rate'),
                DB::raw('NULL as tax'),
                DB::raw('NULL as permit_fee'),
            ]);

        // Apply filters (excluding payment type filter for unpaid bookings)
        $this->applyFilters($query, $filters, 'unpaid');

        return $query->orderBy('tst.date', 'DESC')
                    ->orderBy('tbm.created_at', 'DESC');
    }

    /**
     * Combine paid and unpaid booking queries using UNION
     *
     * @param array $filters
     * @return Builder
     */
    private function combineQueriesWithUnion(array $filters)
    {
        // Performance optimization: Build queries separately to ensure proper indexing
        $paidQuery = $this->buildPaidBookingsQuery($filters);
        $unpaidQuery = $this->buildUnpaidBookingsQuery($filters);
        
        // Use UNION ALL for better performance (no duplicate elimination needed)
        // since paid and unpaid bookings are mutually exclusive
        return $paidQuery->unionAll($unpaidQuery)
                        ->orderBy('tour_date', 'DESC')
                        ->orderBy('created_date', 'DESC');
    }

    /**
     * Determine the third party platform for a booking
     *
     * @param object $booking
     * @return string
     */
    private function determineThirdPartyPlatform($booking): string
    {
        // Check OTA reference booking prefix first (dynamic, driven by config('constants.otas'))
        if (!empty($booking->ota_reference_booking)) {
            $otaTitle = BokunUtils::getTitleFromOtaBookingCode($booking->ota_reference_booking);
            if ($otaTitle) {
                return $otaTitle;
            }
        }

        // Check viator booking reference (TripAdvisor)
        if (!empty($booking->viator_bookingRef)) {
            return 'TripAdvisor';
        }

        // Check GetYourGuide reference
        if (!empty($booking->get_your_guide_reference_booking)) {
            return 'GetYourGuide';
        }

        // Check FareHarbor (comments starting with #)
        if (!empty($booking->comments) && str_starts_with($booking->comments, '#')) {
            return 'FareHarbor';
        }

        return 'Direct';
    }

    /**
     * Build a MySQL CASE expression (with bindings) that maps ota_reference_booking's
     * prefix to its platform title, dynamically sourced from config('constants.otas'),
     * followed by the legacy viator/GetYourGuide/FareHarbor/Direct fallbacks. Must be
     * reused verbatim for both the selectRaw and the matching groupByRaw call so the
     * grouped expression text stays identical under MySQL's ONLY_FULL_GROUP_BY mode.
     *
     * @return array{0: string, 1: array}
     */
    private function buildOtaPlatformCaseSql(): array
    {
        $sql = 'CASE ';
        $bindings = [];

        foreach (config('constants.otas', []) as $ota) {
            if (empty($ota['prefix']) || empty($ota['title'])) {
                continue;
            }
            $sql .= 'WHEN tbm.ota_reference_booking LIKE ? THEN ? ';
            $bindings[] = $ota['prefix'] . '-%';
            $bindings[] = $ota['title'];
        }

        $sql .= 'WHEN tbm.viator_bookingRef IS NOT NULL THEN ?
            WHEN tbm.get_your_guide_reference_booking IS NOT NULL THEN ?
            WHEN tbm.comments LIKE "#%" THEN "FareHarbor"
            ELSE "Direct" END';
        $bindings[] = 'TripAdvisor';
        $bindings[] = 'GetYourGuide';

        return [$sql, $bindings];
    }

    /**
     * Apply filters to the query
     *
     * @param Builder $query
     * @param array $filters
     * @param string $queryType - 'paid', 'unpaid', or 'mixed'
     */
    private function applyFilters(Builder $query, array $filters, string $queryType = 'paid'): void
    {
        // Date range filter
        if (isset($filters['dateFrom']) && isset($filters['dateTo'])) {
            $viewBy = $filters['viewBy'] ?? 'tourdate';

            if ($viewBy === 'createddate') {
                // Created Date: always use booking creation date from tour_bookings_master
                $dateField = 'tbm.created_at';
            } elseif ($queryType === 'unpaid') {
                // For unpaid bookings, use booking creation date or tour date
                $dateField = ($viewBy === 'purchasedate') ? 'tbm.created_at' : 'tst.date';
            } else {
                // For paid bookings, use transaction date or tour date (matching Transaction Summary Report)
                $dateField = ($viewBy === 'purchasedate') ? 't.created_at' : 'tst.date';
            }

            $dateFrom = $filters['dateFrom'] . ' 00:00:00';
            $dateTo = $filters['dateTo'] . ' 23:59:59';

            if ($dateField === 'tst.date') {
                // Push the date range onto tbm.tour_slot_id so MySQL uses the tour_slot_id
                // index to find only in-range bookings before expanding the transactions join.
                // The INNER JOIN on t.tour_booking_id = tbm.id already scopes transactions
                // to the filtered booking set — no second subquery on transactions is needed.
                $slotSubquery = DB::table('tour_slot_times')
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->select('id');
                $query->whereIn('tbm.tour_slot_id', $slotSubquery);
            } else {
                $query->whereBetween($dateField, [$dateFrom, $dateTo]);
            }
        }

        // Tour package filter - support both single and multiple packages
        if (isset($filters['tourPackageIds']) && !empty($filters['tourPackageIds'])) {
            // Multi-select: array of package IDs
            $query->whereIn('tbm.tour_package_id', $filters['tourPackageIds']);
        } elseif (isset($filters['tourPackageId']) && $filters['tourPackageId']) {
            // Backward compatibility: single package ID
            $query->where('tbm.tour_package_id', $filters['tourPackageId']);
        }

        // Affiliate filter - support both single and multiple affiliates
        if (isset($filters['excludeAllAffiliates']) && $filters['excludeAllAffiliates']) {
            // Exclude all affiliates: only show bookings where customer is NOT an affiliate
            $query->leftJoin('customers as c_exclude', 'c_exclude.id', '=', 'tbm.customer_id')
                  ->where(function($q) {
                      $q->where('c_exclude.is_affiliate', '!=', 'Yes')
                        ->orWhereNull('c_exclude.is_affiliate');
                  });
        } elseif (isset($filters['affiliateIds']) && !empty($filters['affiliateIds'])) {
            // Multi-select: use c already LEFT JOINed in base query — no extra join needed
            $query->whereIn('tbm.customer_id', $filters['affiliateIds'])
                  ->where('c.is_affiliate', 'Yes');
        } elseif (isset($filters['affiliateId']) && $filters['affiliateId']) {
            // Backward compatibility: single affiliate ID
            $query->where('tbm.customer_id', $filters['affiliateId'])
                  ->where('c.is_affiliate', 'Yes');
        }

        // Payment type filter - only apply to paid bookings
        // Note: Third-party platforms (TripAdvisor, Headout, GetYourGuide, FareHarbor) are now handled
        // by the separate thirdPartyPlatforms filter, but we need to include them when no filter is active
        if (isset($filters['paymentTypes']) && !empty($filters['paymentTypes']) && $queryType === 'paid') {
            $paymentTypes = $filters['paymentTypes'];
            
            // Remove 'Unpaid' from payment types for paid bookings query
            $paidTypes = array_diff($paymentTypes, ['Unpaid']);
            
            // Check if third-party filter is active
            $thirdPartyPlatforms = $filters['thirdPartyPlatforms'] ?? [];
            $excludeAllThirdParty = isset($filters['excludeAllThirdParty']) && $filters['excludeAllThirdParty'];
            $hasThirdPartyFilter = !empty($thirdPartyPlatforms) || $excludeAllThirdParty;
            
            // Remove third-party platforms from payment types (they're handled separately now)
            $paidTypes = array_diff($paidTypes, array_merge(BokunUtils::getReportableOtaTitles(), ['FareHarbor']));
            
            // Handle Billable and Invoiced filters specially
            $hasBillable = in_array('Billable', $paidTypes);
            $hasInvoiced = in_array('Invoiced', $paidTypes);
            $otherTypes = array_diff($paidTypes, ['Billable', 'Invoiced']);
            
            // When no third-party filter is active, include all third-party platforms in the query
            // This ensures third-party bookings show up by default
            $includeThirdPartyInPaymentTypes = !$hasThirdPartyFilter;
            
            if (!empty($paidTypes) || $includeThirdPartyInPaymentTypes) {
                $query->where(function($mainQ) use ($hasBillable, $hasInvoiced, $otherTypes, $includeThirdPartyInPaymentTypes, $thirdPartyPlatforms, $excludeAllThirdParty, $hasThirdPartyFilter) {
                    // Handle regular payment types
                    if ($hasBillable || $hasInvoiced || !empty($otherTypes)) {
                        $mainQ->where(function($q) use ($hasBillable, $hasInvoiced, $otherTypes) {
                            if ($hasBillable && !$hasInvoiced) {
                                $q->where(function($subQ) {
                                    $subQ->where('t.via', 'Invoiced')
                                         ->whereNotExists(function($ex) {
                                             $ex->select(DB::raw(1))
                                                ->from('invoice_bookings')
                                                ->join('invoices', 'invoices.id', '=', 'invoice_bookings.invoice_id')
                                                ->whereColumn('invoice_bookings.tour_booking_id', 'tbm.id');
                                         });
                                });
                            } elseif ($hasInvoiced && !$hasBillable) {
                                $q->where(function($subQ) {
                                    $subQ->where('t.via', 'Invoiced')
                                         ->whereExists(function($ex) {
                                             $ex->select(DB::raw(1))
                                                ->from('invoice_bookings')
                                                ->join('invoices', 'invoices.id', '=', 'invoice_bookings.invoice_id')
                                                ->whereColumn('invoice_bookings.tour_booking_id', 'tbm.id');
                                         });
                                });
                            } elseif ($hasBillable && $hasInvoiced) {
                                $q->where('t.via', 'Invoiced');
                            }

                            if (!empty($otherTypes)) {
                                if ($hasBillable || $hasInvoiced) {
                                    $q->orWhereIn('t.via', $otherTypes);
                                } else {
                                    $q->whereIn('t.via', $otherTypes);
                                }
                            }
                        });
                    }
                    
                    // Include third-party platforms when no third-party filter is active
                    // Bokun-sourced transactions write the OTA's title directly into t.via
                    // (see BokunRepository), so this is dynamic across all configured OTAs,
                    // not just the two that historically supported this path.
                    $otaViaTitles = BokunUtils::getReportableOtaTitles();

                    if ($includeThirdPartyInPaymentTypes) {
                        // Include all third-party platforms via t.via
                        $mainQ->orWhereIn('t.via', $otaViaTitles);
                        // FareHarbor is identified by a reference field, not t.via
                        // It will be included via the third-party filter section below
                    } elseif ($hasThirdPartyFilter && !$excludeAllThirdParty && !empty($thirdPartyPlatforms)) {
                        // Include only selected third-party platforms via t.via
                        $thirdPartyByVia = array_intersect($thirdPartyPlatforms, $otaViaTitles);
                        if (!empty($thirdPartyByVia)) {
                            $mainQ->orWhereIn('t.via', $thirdPartyByVia);
                        }
                    }
                });
            }
        }

        // Booking status filter
        if (isset($filters['bookingStatus']) && $filters['bookingStatus']) {
            $query->where('tbm.status', $filters['bookingStatus']);
        }

        // Check-in status filter - Apply as a subquery filter - support both single and multiple
        $checkInStatusesToFilter = [];
        if (isset($filters['checkInStatuses']) && !empty($filters['checkInStatuses'])) {
            // Multi-select: array of check-in statuses
            $checkInStatusesToFilter = $filters['checkInStatuses'];
        } elseif (isset($filters['checkInStatus']) && $filters['checkInStatus']) {
            // Backward compatibility: single check-in status
            $checkInStatusesToFilter = [$filters['checkInStatus']];
        }

        if (!empty($checkInStatusesToFilter)) {
            // Map frontend values to database values
            $statusMapping = [
                'Not Check-In' => 'N',
                '1st Check-in' => '1', 
                '2nd Check-in' => '2',
                'Absent' => 'A'
            ];
            
            $dbStatuses = [];
            foreach ($checkInStatusesToFilter as $status) {
                if (isset($statusMapping[$status])) {
                    $dbStatuses[] = $statusMapping[$status];
                }
            }

            if (!empty($dbStatuses)) {
                $query->where(function($mainQuery) use ($dbStatuses) {
                    foreach ($dbStatuses as $dbStatus) {
                        $mainQuery->orWhere(function($q) use ($dbStatus) {
                            // Special handling for "Not Check-In" status
                            if ($dbStatus === 'N') {
                                // For "Not Check-In", include bookings where:
                                // 1. At least one person has status "N" in check_in_status JSON
                                // 2. OR check_in_status is NULL or empty (no check-in data at all)
                                $q->where(function($q2) use ($dbStatus) {
                                    // Case 1: Has check-in data with at least one "N" status
                                    $q2->whereExists(function($subQuery) use ($dbStatus) {
                                        $subQuery->select(DB::raw(1))
                                                ->from('tour_bookings_transact as tbt_filter')
                                                ->whereRaw('tbt_filter.tour_booking_id = tbm.id')
                                                ->where(function($q3) {
                                                    $q3->whereNull('tbt_filter.deleted_by')
                                                       ->orWhere('tbt_filter.deleted_by', 0);
                                                })
                                                ->whereNotNull('tbt_filter.check_in_status')
                                                ->where('tbt_filter.check_in_status', '!=', '')
                                                ->whereRaw("JSON_EXTRACT(tbt_filter.check_in_status, '$[*].status') LIKE ?", ["%\"$dbStatus\"%"]);
                                    });
                                })
                                // Case 2: Has no check-in data at all (NULL or empty)
                                ->orWhereNotExists(function($subQuery) {
                                    $subQuery->select(DB::raw(1))
                                            ->from('tour_bookings_transact as tbt_filter2')
                                            ->whereRaw('tbt_filter2.tour_booking_id = tbm.id')
                                            ->where(function($q2) {
                                                $q2->whereNull('tbt_filter2.deleted_by')
                                                   ->orWhere('tbt_filter2.deleted_by', 0);
                                            })
                                            ->whereNotNull('tbt_filter2.check_in_status')
                                            ->where('tbt_filter2.check_in_status', '!=', '');
                                });
                            } else {
                                // For other statuses (1st Check-in, 2nd Check-in, Absent), use standard logic
                                $q->whereExists(function($subQuery) use ($dbStatus) {
                                    $subQuery->select(DB::raw(1))
                                            ->from('tour_bookings_transact as tbt_filter')
                                            ->whereRaw('tbt_filter.tour_booking_id = tbm.id')
                                            ->where(function($q2) {
                                                $q2->whereNull('tbt_filter.deleted_by')
                                                  ->orWhere('tbt_filter.deleted_by', 0);
                                            })
                                            // CRITICAL: Filter out NULL and empty strings before JSON_EXTRACT to prevent MySQL errors
                                            ->whereNotNull('tbt_filter.check_in_status')
                                            ->where('tbt_filter.check_in_status', '!=', '')
                                            ->whereRaw("JSON_EXTRACT(tbt_filter.check_in_status, '$[*].status') LIKE ?", ["%\"$dbStatus\"%"]);
                                });
                            }
                        });
                    }
                });
            }
        }
        
        // Waiver status filter
        if (isset($filters['waiverStatus']) && $filters['waiverStatus']) {
            if ($filters['waiverStatus'] === 'Signed') {
                $query->whereNotNull('tbm.liability_waiver_signing')
                      ->where('tbm.liability_waiver_signing', '!=', '');
            } else {
                $query->where(function($q) {
                    $q->whereNull('tbm.liability_waiver_signing')
                      ->orWhere('tbm.liability_waiver_signing', '=', '');
                });
            }
        }

        // Third-party platform filter
        // Supports multi-select with "Exclude All" option (similar to Affiliate filter)
        $thirdPartyPlatforms = $filters['thirdPartyPlatforms'] ?? [];
        $excludeAllThirdParty = isset($filters['excludeAllThirdParty']) && $filters['excludeAllThirdParty'];
        
        // Backward compatibility with old single-select format
        if (empty($thirdPartyPlatforms) && isset($filters['thirdPartyPlatform']) && $filters['thirdPartyPlatform']) {
            if ($filters['thirdPartyPlatform'] === 'Direct') {
                $excludeAllThirdParty = true;
            } else {
                $thirdPartyPlatforms = [$filters['thirdPartyPlatform']];
            }
        }
        
        if ($excludeAllThirdParty) {
            // Exclude ALL third-party platforms (Direct bookings only)
            $query->where(function($q) {
                // Exclude by reference fields (catches bookings with OTA references)
                $q->whereNull('tbm.ota_reference_booking')
                  ->whereNull('tbm.viator_bookingRef')
                  ->whereNull('tbm.get_your_guide_reference_booking')
                  ->where(function($subQ) {
                      $subQ->whereNull('tbm.comments')
                           ->orWhere('tbm.comments', 'NOT LIKE', '#%');
                  });

                // ALSO exclude by payment type (catches bookings where only payment type is set)
                $q->whereNotExists(function($exists) {
                    $exists->select(DB::raw(1))
                        ->from('tour_booking_history as tbh_platform')
                        ->whereColumn('tbh_platform.tour_booking_id', 'tbm.id')
                        ->whereIn('tbh_platform.payment_type', array_merge(BokunUtils::getReportableOtaTitles(), ['FareHarbor']));
                });
            });
        } elseif (!empty($thirdPartyPlatforms)) {
            // Filter to show ONLY selected third-party platforms
            $query->where(function($mainQ) use ($thirdPartyPlatforms) {
                // prefix keyed by title, e.g. 'CHD' => 'CHD', 'TripAdvisor' => 'TRIP'
                $otaPrefixesByTitle = collect(config('constants.otas', []))
                    ->filter(fn($ota) => !empty($ota['prefix']) && !empty($ota['title']))
                    ->pluck('prefix', 'title');

                foreach ($thirdPartyPlatforms as $platform) {
                    if ($platform === 'TripAdvisor') {
                        $mainQ->orWhereExists(function($exists) {
                            $exists->select(DB::raw(1))
                                ->from('tour_booking_history as tbh_ta')
                                ->whereColumn('tbh_ta.tour_booking_id', 'tbm.id')
                                ->where('tbh_ta.payment_type', 'TripAdvisor');
                        });
                    } elseif ($platform === 'Headout') {
                        $mainQ->orWhereExists(function($exists) {
                            $exists->select(DB::raw(1))
                                ->from('tour_booking_history as tbh_ho')
                                ->whereColumn('tbh_ho.tour_booking_id', 'tbm.id')
                                ->where('tbh_ho.payment_type', 'Headout');
                        });
                    } elseif ($platform === 'GetYourGuide') {
                        $mainQ->orWhere(function($subQ) {
                            $subQ->whereNotNull('tbm.get_your_guide_reference_booking')
                                 ->where('tbm.get_your_guide_reference_booking', '!=', '');
                        });
                    } elseif ($platform === 'FareHarbor') {
                        $mainQ->orWhere(function($subQ) {
                            $subQ->whereNotNull('tbm.comments')
                                 ->where('tbm.comments', 'LIKE', '#%');
                        });
                    }

                    // Dynamic: match by the OTA's ota_reference_booking prefix, sourced from
                    // config('constants.otas'). Additive for the legacy platforms above (catches
                    // bookings only identified via the unified field), and the only match path
                    // for platforms with no dedicated legacy column (e.g. CHD, Lagunacreek).
                    $prefix = $otaPrefixesByTitle->get($platform);
                    if ($prefix) {
                        $mainQ->orWhere('tbm.ota_reference_booking', 'LIKE', $prefix . '-%');
                    }
                }
            });
        }
        // If no third party platforms selected and not excluding all, show all bookings (no filter applied)

        // Amount range filters - only apply to paid bookings
        if ($queryType === 'paid') {
            if (isset($filters['minAmount']) && $filters['minAmount'] > 0) {
                $query->where('t.amount', '>=', $filters['minAmount']);
            }

            if (isset($filters['maxAmount']) && $filters['maxAmount'] > 0) {
                $query->where('t.amount', '<=', $filters['maxAmount']);
            }
        }

        // Rate, Permit Fee, and Tax filters - apply to BOTH paid and unpaid bookings
        // These filters check the tour_bookings_transact table which exists for both types

        // Rate filter - filter bookings where at least one ACTIVE rate group (with tourists > 0) has this exact rate value.
        // whereExists (correlated) lets MySQL use idx_bookings_transact_booking_deleted(tour_booking_id, deleted_by)
        // per tbm.id instead of materialising the whole table into an IN-list.
        if (isset($filters['rate']) && $filters['rate'] > 0) {
            $rate = round($filters['rate'], 2);
            $query->whereExists(function($q) use ($rate) {
                $q->select(DB::raw(1))
                    ->from('tour_bookings_transact as tbt_rate')
                    ->whereColumn('tbt_rate.tour_booking_id', 'tbm.id')
                    ->where(function($q2) {
                        $q2->whereNull('tbt_rate.deleted_by')
                           ->orWhere('tbt_rate.deleted_by', 0);
                    })
                    ->whereRaw('ROUND(tbt_rate.rate, 2) = ?', [$rate])
                    ->where('tbt_rate.tourists', '>', 0);
            });
        }

        // Permit Fee filter - correlated EXISTS so MySQL probes via tour_booking_id index.
        if (isset($filters['permitFee']) && $filters['permitFee'] > 0) {
            $permitFee = round($filters['permitFee'], 2);
            $query->whereExists(function($q) use ($permitFee) {
                $q->select(DB::raw(1))
                    ->from('tour_bookings_transact as tbt_permit')
                    ->whereColumn('tbt_permit.tour_booking_id', 'tbm.id')
                    ->where(function($q2) {
                        $q2->whereNull('tbt_permit.deleted_by')
                           ->orWhere('tbt_permit.deleted_by', 0);
                    })
                    ->whereRaw('ROUND(tbt_permit.permit_fee, 2) = ?', [$permitFee])
                    ->where('tbt_permit.tourists', '>', 0);
            });
        }

        // Tax filter - correlated EXISTS so MySQL probes via tour_booking_id index.
        if (isset($filters['tax']) && $filters['tax'] > 0) {
            $tax = round($filters['tax'], 2);
            $query->whereExists(function($q) use ($tax) {
                $q->select(DB::raw(1))
                    ->from('tour_bookings_transact as tbt_tax')
                    ->whereColumn('tbt_tax.tour_booking_id', 'tbm.id')
                    ->where(function($q2) {
                        $q2->whereNull('tbt_tax.deleted_by')
                           ->orWhere('tbt_tax.deleted_by', 0);
                    })
                    ->whereRaw('ROUND(tbt_tax.additional_charge, 2) = ?', [$tax])
                    ->where('tbt_tax.tourists', '>', 0);
            });
        }

        // Soft Blocks filter - filter by is_reserved_for_affiliate field
        if (isset($filters['softBlocks']) && $filters['softBlocks'] !== '') {
            if ($filters['softBlocks'] === '1') {
                // Show only soft blocks (is_reserved_for_affiliate = 1)
                $query->where('tbm.is_reserved_for_affiliate', 1);
            } elseif ($filters['softBlocks'] === '0') {
                // Exclude soft blocks (is_reserved_for_affiliate = 0 or NULL)
                $query->where(function($q) {
                    $q->where('tbm.is_reserved_for_affiliate', 0)
                      ->orWhereNull('tbm.is_reserved_for_affiliate');
                });
            }
            // If empty string, no filter applied (show all bookings)
        }

        // Reallocated Funds filter - exclude split-related transactions
        if (isset($filters['reallocatedFunds']) && $filters['reallocatedFunds'] === '0') {
            // Simple boolean column check - no subqueries or JSON parsing needed
            // Excludes both split refunds and extended booking payments
            $query->where('t.is_reallocated', false);
        }
    }

    /**
     * Get disputes that were lost (and reversed) within the report date range.
     * Filters by lost_at so the deduction appears in the period it actually occurred,
     * not in the period of the original booking.
     *
     * @param array $filters
     * @return array ['total' => float, 'items' => array]
     */
    public function getDisputeReversalsInPeriod(array $filters): array
    {
        try {
            $dateFrom = ($filters['dateFrom'] ?? null) ? $filters['dateFrom'] . ' 00:00:00' : null;
            $dateTo   = ($filters['dateTo']   ?? null) ? $filters['dateTo']   . ' 23:59:59' : null;

            $query = DB::table('disputes as d')
                ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'd.booking_id')
                ->select([
                    'd.id as dispute_id',
                    'd.booking_id',
                    'tbm.name as customer_name',
                    'd.amount as dispute_amount',
                    'd.reversed_amount',
                    'd.reason',
                    'd.lost_at',
                ])
                ->where('d.status', 'lost')
                ->whereNotNull('d.reversed_amount')
                ->whereNotNull('d.lost_at');

            if ($dateFrom) {
                $query->where('d.lost_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('d.lost_at', '<=', $dateTo);
            }

            $disputes = $query->orderBy('d.lost_at', 'desc')->get();

            $items = $disputes->map(fn($d) => [
                'dispute_id'      => $d->dispute_id,
                'booking_id'      => $d->booking_id,
                'customer_name'   => $d->customer_name,
                'dispute_amount'  => (float) $d->dispute_amount,
                'reversed_amount' => round((int) $d->reversed_amount / 100, 2),
                'reason'          => $d->reason,
                'lost_at'         => date('m/d/Y g:i A', strtotime($d->lost_at)),
            ])->toArray();

            $total = round(array_sum(array_column($items, 'reversed_amount')), 2);

            return ['total' => $total, 'items' => $items];
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownRepository::getDisputeReversalsInPeriod Error: ' . $exception->getMessage());
            return ['total' => 0.0, 'items' => []];
        }
    }

    /**
     * Determine waiver status from liability_waiver_signing field value
     *
     * @param mixed $waiverValue
     * @return string
     */
    private function determineWaiverStatus($waiverValue): string
    {
        // Handle null or empty values
        if (is_null($waiverValue) || $waiverValue === '') {
            return 'Not Signed';
        }
        
        // Handle string values - check if it's valid JSON with waiver data
        if (is_string($waiverValue)) {
            $trimmed = trim($waiverValue);
            
            // Empty or "0" means not signed
            if ($trimmed === '' || $trimmed === '0') {
                return 'Not Signed';
            }
            
            // Try to decode as JSON - if it contains waiver data, it's signed
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                // Valid JSON with data means signed
                return 'Signed';
            }
            
            // If it's not JSON but has content, consider it signed
            return 'Signed';
        }
        
        // Handle numeric values (1 = signed, 0 = not signed)
        if (is_numeric($waiverValue)) {
            return (int)$waiverValue > 0 ? 'Signed' : 'Not Signed';
        }
        
        // Handle boolean values
        if (is_bool($waiverValue)) {
            return $waiverValue ? 'Signed' : 'Not Signed';
        }
        
        // Default: if there's any other value, consider it signed
        return 'Signed';
    }
}
