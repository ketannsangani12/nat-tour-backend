<?php

namespace App\Services;

use App\Utils\BokunUtils;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentBreakdownService
{
    /**
     * Process raw payment breakdown data into structured format
     *
     * @param array $rawData
     * @return array
     */
    public function processPaymentBreakdown(array $rawData): array
    {
        try {
            $processedData = [
                'paymentTypes' => $this->processPaymentTypes($rawData),
                'paymentTypeBreakdown' => $this->processPaymentTypeBreakdown($rawData), // NEW: Detailed breakdown by payment type
                'affiliateData' => $this->processAffiliateData($rawData),
                'thirdPartyData' => $this->processThirdPartyData($rawData),
                'operationalMetrics' => $this->processOperationalMetrics($rawData),
                'financialDetails' => $this->processFinancialDetails($rawData),
                'bookingDetails' => $this->processBookingDetails($rawData)
            ];

            return $processedData;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownService::processPaymentBreakdown Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Process payment breakdown data with check-in status integration
     *
     * @param array $rawData
     * @param array $checkInData
     * @param array $waiverData
     * @param array $individualCheckInData
     * @return array
     */
    public function processPaymentBreakdownWithMetrics(array $rawData, array $checkInData, array $waiverData, array $individualCheckInData = []): array
    {
        try {
            $processedData = $this->processPaymentBreakdown($rawData);

            // Use the service's waiver calculation (from aggregated data) instead of repository's separate query
            // This ensures consistency with payment types calculation
            $processedData['operationalMetrics'] = [
                'check_in_status' => $checkInData,
                'waiver_status' => [
                    'Signed' => [
                        'count' => $processedData['operationalMetrics']['waiver_status']['signed']['count'],
                        'pax' => $processedData['operationalMetrics']['waiver_status']['signed']['pax']
                    ],
                    'Not Signed' => [
                        'count' => $processedData['operationalMetrics']['waiver_status']['not_signed']['count'],
                        'pax' => $processedData['operationalMetrics']['waiver_status']['not_signed']['pax']
                    ]
                ]
            ];

            // Add individual check-in data to booking details if available
            if (!empty($individualCheckInData) && isset($processedData['bookingDetails'])) {
                foreach ($processedData['bookingDetails'] as &$booking) {
                    $bookingId = $booking['booking_id'];
                    if (isset($individualCheckInData[$bookingId])) {
                        $booking['check_in_status'] = $individualCheckInData[$bookingId]['overall_status'];
                        $booking['check_in_details'] = $individualCheckInData[$bookingId];
                    }
                }
            }

            return $processedData;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownService::processPaymentBreakdownWithMetrics Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Generate summary totals (matching Transaction Summary Report)
     *
     * @param array $data
     * @return array
     */
    public function generateSummaryTotals(array $data): array
    {
        try {
            $summary = [
                'totalBookings'         => 0,
                'totalPax'              => 0,
                'totalRevenue'          => 0.0,
                'totalRefunds'          => 0.0,
                'totalFees'             => 0.0,
                'totalDiscount'         => 0.0,
                'totalUnpaid'           => 0.0,
                'totalNet'              => 0.0,
                'netRevenue'            => 0.0,
                // Dispute reversals are injected by the controller from getDisputeReversalsInPeriod()
                'totalDisputeReversals' => 0.0,
                'paymentTypeBreakdown'  => [],
                'affiliateRevenue'      => 0.0,
                'thirdPartyRevenue'     => 0.0,
                'directRevenue'         => 0.0,
                'averageBookingValue'   => 0.0,
                'waiverComplianceRate'  => 0.0,
                'attendanceRate'        => 0.0
            ];

            // Calculate totals from payment types (which have correct fee calculations)
            if (isset($data['paymentTypes'])) {
                foreach ($data['paymentTypes'] as $paymentType => $typeData) {
                    $summary['totalBookings'] += $typeData['booking_count'];
                    $summary['totalPax'] += $typeData['total_pax'];

                    // Include ALL payment types in Revenue (respects filter)
                    $summary['totalRevenue'] += $typeData['total_amount'];
                    $summary['totalRefunds'] += $typeData['refund_amount'];
                    $summary['totalFees'] += $typeData['fee_amount'] ?? 0;

                    // Net revenue includes ALL payment types
                    $summary['netRevenue'] += $typeData['net_amount'];
                }

                // Calculate discount and unpaid total from booking details
                if (isset($data['bookingDetails'])) {
                    foreach ($data['bookingDetails'] as $booking) {
                        $summary['totalDiscount'] += (float) ($booking['discount'] ?? 0);
                        $summary['totalUnpaid'] += (float) ($booking['unpaid_total'] ?? 0);
                    }
                }
            } else {
                // Fallback: Calculate totals from booking details if payment types not available
                if (isset($data['bookingDetails'])) {
                    foreach ($data['bookingDetails'] as $booking) {
                        $summary['totalBookings']++;
                        $summary['totalPax'] += (int) ($booking['total_pax'] ?? 0);
                        $summary['totalRevenue'] += (float) ($booking['amount'] ?? 0);
                        $summary['totalRefunds'] += (float) ($booking['refund_amount'] ?? 0);
                        $summary['totalFees'] += (float) ($booking['fee_amount'] ?? 0);
                        $summary['totalDiscount'] += (float) ($booking['discount'] ?? 0);
                        $summary['totalUnpaid'] += (float) ($booking['unpaid_total'] ?? 0);
                        $summary['totalNet'] += (float) ($booking['net_amount'] ?? 0);
                    }
                }

                // Fallback net revenue calculation
                $summary['netRevenue'] = $summary['totalRevenue'] - $summary['totalRefunds'] - $summary['totalFees'];
            }

            // Round all monetary values to 2 decimal places to avoid floating-point precision errors
            $summary['totalRevenue']  = round($summary['totalRevenue'], 2);
            $summary['totalRefunds']  = round($summary['totalRefunds'], 2);
            $summary['totalFees']     = round($summary['totalFees'], 2);
            $summary['totalDiscount'] = round($summary['totalDiscount'], 2);
            $summary['totalUnpaid']   = round($summary['totalUnpaid'], 2);
            $summary['netRevenue']    = round($summary['netRevenue'], 2);

            // Calculate payment type breakdown for percentages
            if (isset($data['paymentTypes'])) {
                foreach ($data['paymentTypes'] as $paymentType => $typeData) {
                    $summary['paymentTypeBreakdown'][$paymentType] = [
                        'amount' => round($typeData['total_amount'], 2),
                        'percentage' => 0, // Will be calculated after total is known
                        'pax_count' => $typeData['total_pax'],
                        'booking_count' => $typeData['booking_count']
                    ];
                }
            }

            // Calculate percentages
            if ($summary['totalRevenue'] > 0) {
                foreach ($summary['paymentTypeBreakdown'] as $type => $breakdown) {
                    $summary['paymentTypeBreakdown'][$type]['percentage'] = round(($breakdown['amount'] / $summary['totalRevenue']) * 100, 2);
                }
            }

            // Calculate average booking value
            if ($summary['totalBookings'] > 0) {
                $summary['averageBookingValue'] = round($summary['netRevenue'] / $summary['totalBookings'], 2);
            }

            // Calculate affiliate revenue
            if (isset($data['affiliateData'])) {
                foreach ($data['affiliateData'] as $affiliate) {
                    $summary['affiliateRevenue'] += $affiliate['net_amount'];
                }
                $summary['affiliateRevenue'] = round($summary['affiliateRevenue'], 2);
            }

            // Set totalNet to netRevenue 
            // When affiliate filters are applied, the data itself is filtered,
            // so netRevenue already represents the correct amount for the filtered dataset
            $summary['totalNet'] = $summary['netRevenue'];

            // Calculate third-party revenue
            if (isset($data['thirdPartyData'])) {
                foreach ($data['thirdPartyData'] as $platform) {
                    $summary['thirdPartyRevenue'] += $platform['total_amount'];
                }
                $summary['thirdPartyRevenue'] = round($summary['thirdPartyRevenue'], 2);
            }

            // Calculate direct revenue
            $summary['directRevenue'] = round($summary['totalRevenue'] - $summary['affiliateRevenue'] - $summary['thirdPartyRevenue'], 2);

            return $summary;
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownService::generateSummaryTotals Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Validate financial calculations
     *
     * @param array $data
     * @return bool
     */
    public function validateFinancialCalculations(array $data): bool
    {
        try {
            $validationResults = [
                'transaction_amount_consistency' => true,
                'rate_component_validation' => true,
                'pax_count_consistency' => true,
                'errors' => []
            ];

            // Validate transaction amounts are positive
            if (isset($data['bookingDetails'])) {
                foreach ($data['bookingDetails'] as $booking) {
                    // Handle both array and object formats
                    $bookingArray = \is_array($booking) ? $booking : (array) $booking;

                    $transactionAmount = (float) ($bookingArray['amount'] ?? 0);

                    if ($transactionAmount < 0) {
                        $validationResults['transaction_amount_consistency'] = false;
                        $validationResults['errors'][] = "Negative transaction amount for booking {$bookingArray['booking_id']}";
                    }

                    // Validate pax count is positive
                    $paxCount = (int) ($bookingArray['total_pax'] ?? 0);
                    if ($paxCount <= 0) {
                        $validationResults['pax_count_consistency'] = false;
                        $validationResults['errors'][] = "Invalid pax count for booking {$bookingArray['booking_id']}";
                    }
                }
            }

            return empty($validationResults['errors']);
        } catch (Exception $exception) {
            Log::error('PaymentBreakdownService::validateFinancialCalculations Error: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Process payment types data (works with already aggregated data from repository)
     *
     * @param array $rawData
     * @return array
     */
    private function processPaymentTypes(array $rawData): array
    {
        $paymentTypes = [];

        foreach ($rawData as $row) {
            $paymentType = $row->purchase_type ?? 'Unpaid';
            $bookingStatus = $row->booking_status ?? '';

            if (!isset($paymentTypes[$paymentType])) {
                $paymentTypes[$paymentType] = [
                    'booking_count' => 0,
                    'total_pax'     => 0,
                    'total_amount'  => 0,
                    'refund_amount' => 0,
                    'fee_amount'    => 0,
                    'net_amount'    => 0,
                    'bookings'      => []
                ];
            }

            $paxCount        = (int) $row->tourists;
            $amount          = (float) ($row->amount ?? 0);
            $refundRemoveFee = (float) ($row->refund_remove_fee ?? 0);
            $feeAmount       = (float) ($row->fee_amount ?? 0);
            $netTotal        = (float) ($row->net_total ?? 0);

            $paymentTypes[$paymentType]['booking_count']++;
            $paymentTypes[$paymentType]['total_pax']    += $paxCount;
            $paymentTypes[$paymentType]['total_amount'] += $amount;
            $paymentTypes[$paymentType]['refund_amount']+= $refundRemoveFee;
            $paymentTypes[$paymentType]['fee_amount']   += $feeAmount;
            $paymentTypes[$paymentType]['net_amount']   += $netTotal;
            $paymentTypes[$paymentType]['bookings'][]    = $row->booking_id;
        }

        foreach ($paymentTypes as $paymentType => $typeData) {
            $paymentTypes[$paymentType]['bookings']      = array_unique($typeData['bookings']);
            $paymentTypes[$paymentType]['booking_count'] = \count($typeData['bookings']);
            $paymentTypes[$paymentType]['total_amount']  = round($typeData['total_amount'], 2);
            $paymentTypes[$paymentType]['refund_amount'] = round($typeData['refund_amount'], 2);
            $paymentTypes[$paymentType]['fee_amount']    = round($typeData['fee_amount'], 2);
            $paymentTypes[$paymentType]['net_amount']    = round($typeData['net_amount'], 2);
        }

        return $paymentTypes;
    }

    /**
     * Process affiliate data
     *
     * @param array $rawData
     * @return array
     */
    private function processAffiliateData(array $rawData): array
    {
        $affiliates = [];

        foreach ($rawData as $row) {
            // Check if customer is affiliate using customers.is_affiliate = 'Yes'
            if (isset($row->customer_is_affiliate) && $row->customer_is_affiliate === 'Yes') {
                // Use customer_id as the grouping key to ensure proper aggregation
                // This prevents issues with name variations or null affiliate_name values
                $customerId = $row->customer_id ?? null;

                if (!$customerId) {
                    continue; // Skip if no customer ID
                }

                if (!isset($affiliates[$customerId])) {
                    $affiliates[$customerId] = [
                        'id' => $customerId,
                        'name' => $row->affiliate_name ?? $row->customer_name, // Use customers.name for display
                        'booking_count' => 0,
                        'total_pax' => 0,
                        'total_amount' => 0,
                        'refund_amount' => 0,
                        'fee_amount' => 0,
                        'net_amount' => 0
                    ];
                }

                $paxCount = (int) $row->tourists;
                $amount = (float) ($row->amount ?? 0);
                $refund = (float) ($row->refund ?? 0);
                $feeAmount = (float) ($row->fee_amount ?? 0);
                $refundFeeAmount = (float) ($row->refund_fee_amount ?? 0);
                $netTotal = (float) ($row->net_total ?? 0);

                // Calculate effective fee (original fee minus refund fee)
                $effectiveFee = $feeAmount - $refundFeeAmount;

                $affiliates[$customerId]['booking_count']++;
                $affiliates[$customerId]['total_pax'] += $paxCount;
                $affiliates[$customerId]['total_amount'] += $amount;
                $affiliates[$customerId]['refund_amount'] += $refund;
                $affiliates[$customerId]['fee_amount'] += $effectiveFee; // Use effective fee
                $affiliates[$customerId]['net_amount'] += $netTotal;

                // Ensure we always have the most complete name available
                if (!empty($row->affiliate_name) && empty($affiliates[$customerId]['name'])) {
                    $affiliates[$customerId]['name'] = $row->affiliate_name;
                }
            }
        }

        return array_values($affiliates);
    }

    /**
     * Process third-party platform data
     *
     * @param array $rawData
     * @return array
     */
    private function processThirdPartyData(array $rawData): array
    {
        $platforms = [];

        foreach ($rawData as $row) {
            $platform = 'Direct';
            $otaTitle = !empty($row->ota_reference_booking)
                ? BokunUtils::getTitleFromOtaBookingCode($row->ota_reference_booking)
                : null;

            if ($otaTitle) {
                $platform = $otaTitle;
            } elseif (!empty($row->viator_bookingRef)) {
                $platform = 'TripAdvisor';
            } elseif (!empty($row->get_your_guide_reference_booking)) {
                $platform = 'GetYourGuide';
            } elseif (strpos($row->comments ?? '', '#') === 0) {
                $platform = 'FareHarbor';
            }

            if ($platform !== 'Direct') {
                if (!isset($platforms[$platform])) {
                    $platforms[$platform] = [
                        'name' => $platform,
                        'booking_count' => 0,
                        'total_pax' => 0,
                        'total_amount' => 0,
                        'commission_rate' => 0
                    ];
                }

                $paxCount = (int) $row->tourists;
                // Use net_total instead of amount to account for refunds
                $amount = (float) ($row->net_total ?? $row->amount ?? 0);

                $platforms[$platform]['booking_count']++;
                $platforms[$platform]['total_pax'] += $paxCount;
                $platforms[$platform]['total_amount'] += $amount;
            }
        }

        return array_values($platforms);
    }

    /**
     * Process operational metrics
     *
     * @param array $rawData
     * @return array
     */
    private function processOperationalMetrics(array $rawData): array
    {
        $metrics = [
            'waiver_status' => [
                'signed' => ['count' => 0, 'pax' => 0],
                'not_signed' => ['count' => 0, 'pax' => 0]
            ],
            'check_in_status' => [
                'not_checked_in' => ['count' => 0, 'pax' => 0],
                'first_checkin' => ['count' => 0, 'pax' => 0],
                'second_checkin' => ['count' => 0, 'pax' => 0],
                'absent' => ['count' => 0, 'pax' => 0]
            ]
        ];

        $processedBookings = [];

        foreach ($rawData as $row) {
            $bookingId = $row->booking_id;

            // Avoid double counting bookings
            if (isset($processedBookings[$bookingId])) {
                continue;
            }
            $processedBookings[$bookingId] = true;

            $paxCount = (int) $row->tourists;

            // Waiver status - check if liability_waiver_signing contains JSON data
            $hasWaiverData = false;
            if (!empty($row->liability_waiver_signing) && $row->liability_waiver_signing !== '0') {
                $decoded = json_decode($row->liability_waiver_signing, true);
                $hasWaiverData = (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded));
            }

            if ($hasWaiverData) {
                $metrics['waiver_status']['signed']['count']++;
                $metrics['waiver_status']['signed']['pax'] += $paxCount;
            } else {
                $metrics['waiver_status']['not_signed']['count']++;
                $metrics['waiver_status']['not_signed']['pax'] += $paxCount;
            }

            // Check-in status - this would need to be enhanced with actual check-in data
            // For now, using booking status as a proxy
            if ($row->booking_status === 'Cancelled') {
                $metrics['check_in_status']['absent']['count']++;
                $metrics['check_in_status']['absent']['pax'] += $paxCount;
            } else {
                // Default to not checked in for active bookings
                $metrics['check_in_status']['not_checked_in']['count']++;
                $metrics['check_in_status']['not_checked_in']['pax'] += $paxCount;
            }
        }

        return $metrics;
    }

    /**
     * Process financial details
     *
     * @param array $rawData
     * @return array
     */
    private function processFinancialDetails(array $rawData): array
    {
        $details = [
            'total_rate' => 0,
            'total_tax' => 0,
            'total_permit_fees' => 0,
            'total_platform_fees' => 0,
            'total_processing_fees' => 0,
            'breakdown_by_ticket_type' => []
        ];

        foreach ($rawData as $row) {
            $details['total_rate'] += (float) ($row->rate ?? 0);
            $details['total_tax'] += (float) ($row->tax ?? 0);
            $details['total_permit_fees'] += (float) ($row->permit_fee ?? 0);
            $details['total_platform_fees'] += (float) ($row->platform_fee_percentage ?? 0);
            $details['total_processing_fees'] += (float) ($row->processing_charge_percentage ?? 0);

            // Group by ticket type
            $ticketType = $row->ticket_type ?? 'Unknown';
            if (!isset($details['breakdown_by_ticket_type'][$ticketType])) {
                $details['breakdown_by_ticket_type'][$ticketType] = [
                    'count' => 0,
                    'total_amount' => 0
                ];
            }

            $details['breakdown_by_ticket_type'][$ticketType]['count']++;
            // Use net_total instead of amount to account for refunds
            $details['breakdown_by_ticket_type'][$ticketType]['total_amount'] += (float) ($row->net_total ?? $row->amount ?? 0);
        }

        return $details;
    }

    /**
     * Process booking details for frontend display (with Transaction Summary Report fields)
     *
     * @param array $rawData
     * @return array
     */
    private function processBookingDetails(array $rawData): array
    {
        $bookingDetails = [];

        foreach ($rawData as $row) {
            // Convert stdClass to array and format for frontend
            $booking = (array) $row;

            // Map database fields to frontend expected fields (data is already aggregated)
            $bookingDetails[] = [
                'booking_id' => $booking['booking_id'] ?? '',
                'tour_date' => $booking['tour_date'] ?? '',
                'tour_time' => !empty($booking['time_from']) ? date('g:i A', strtotime($booking['time_from'])) : '',
                'created_date' => $booking['created_date'] ?? '',
                'purchase_date' => $booking['purchase_date'] ?? '',
                'tour_package' => $booking['package_name'] ?? '',
                'customer_name' => $booking['customer_name'] ?? '',
                'customer_email' => $booking['email'] ?? '',
                'customer_phone' => $booking['phone_number'] ?? '',
                'purchase_type' => $booking['purchase_type'] ?? 'Unpaid',
                'charge_type' => $booking['charge_type'],
                'has_custom_charge' => $booking['has_custom_charge'] ?? false,
                'amount' => (float) ($booking['amount'] ?? 0),
                'fee_amount' => (float) ($booking['fee_amount'] ?? 0),
                'refund_amount' => (float) ($booking['refund_remove_fee'] ?? 0),
                'discount' => (float) ($booking['discount'] ?? 0),
                'net_total' => (float) ($booking['net_total'] ?? 0),
                'total_pax' => (int) ($booking['tourists'] ?? 0),
                'booking_status' => $booking['booking_status'] ?? '',
                'check_in_status' => $booking['check_in_status'] ?? 'Not Check-In',
                'waiver_status' => $booking['waiver_status'] ?? 'Not Signed',
                'third_party_platform' => $booking['third_party_platform'] ?? 'Direct',
                'affiliate_name' => ($booking['customer_is_affiliate'] ?? '') === 'Yes' ? ($booking['customer_name'] ?? '') : '',
                'rate' => (float) ($booking['rate'] ?? 0),
                'tax' => (float) ($booking['tax'] ?? 0),
                'permit_fee' => (float) ($booking['permit_fee'] ?? 0),
                'rate_for' => $booking['rate_for'] ?? 'Unknown',
                'rate_breakdown' => $booking['rate_breakdown'] ?? [],
                'pax_rate' => $booking['pax_rate'] ?? 0,
                'pax_tax' => $booking['pax_tax'] ?? 0,
                'pax_permit_fee' => $booking['pax_permit_fee'] ?? 0,
                'is_reallocated' => $booking['is_reallocated'],
                'is_soft_block' => (bool) ($booking['is_reserved_for_affiliate'] ?? false),
                // Unpaid total = Gross amount (rate + tax + permit_fee) for unpaid bookings
                'unpaid_total' => ($booking['purchase_type'] ?? '') === 'Unpaid'
                    ? (float) (($booking['rate'] ?? 0) + ($booking['tax'] ?? 0) + ($booking['permit_fee'] ?? 0))
                    : 0,
            ];
        }

        return $bookingDetails;
    }

    /**
     * Process payment type breakdown with rate group details
     * This calculates the detailed breakdown by payment type and rate groups
     * for the frontend "By Payment Type" section
     * Also includes transaction-based metrics (refunds, fees, net_amount)
     *
     * @param array $rawData
     * @return array
     */
    private function processPaymentTypeBreakdown(array $rawData): array
    {
        $paymentTypeBreakdown = [];

        foreach ($rawData as $row) {
            $paymentType = $row->purchase_type ?? 'Unpaid';

            // Initialize payment type if not exists
            if (!isset($paymentTypeBreakdown[$paymentType])) {
                $paymentTypeBreakdown[$paymentType] = [
                    'rateGroups' => [],
                    'totalPax' => 0,
                    'totalRate' => 0,
                    'totalTax' => 0,
                    'totalPermitFee' => 0,
                    'totalAmount' => 0,
                    // Transaction-based metrics (for charts and summary)
                    'bookingCount' => 0,
                    'transactionAmount' => 0,
                    'refundAmount' => 0,
                    'feeAmount' => 0,
                    'netAmount' => 0
                ];
            }

            // Aggregate transaction-based metrics per booking
            $paymentTypeBreakdown[$paymentType]['bookingCount']++;
            $paymentTypeBreakdown[$paymentType]['transactionAmount'] += (float) ($row->amount ?? 0);
            $paymentTypeBreakdown[$paymentType]['refundAmount'] += (float) ($row->refund_remove_fee ?? 0);
            $paymentTypeBreakdown[$paymentType]['feeAmount'] += (float) ($row->fee_amount ?? 0);
            $paymentTypeBreakdown[$paymentType]['netAmount'] += (float) ($row->net_total ?? 0);

            // Process rate breakdown if available
            if (isset($row->rate_breakdown) && is_array($row->rate_breakdown)) {
                foreach ($row->rate_breakdown as $rateData) {
                    $rateId = $rateData['rate_id'] ?? 'unknown';
                    $rateFor = $rateData['rate_for'] ?? 'Unknown';
                    $mappingKey = $rateData['mapping_key'] ?? 0;
                    $perPersonRate = (float) ($rateData['rate'] ?? 0);
                    $perPersonTax = (float) ($rateData['tax'] ?? 0);
                    $perPersonPermitFee = (float) ($rateData['permit_fee'] ?? 0);
                    $pax = (int) ($rateData['pax'] ?? 0);

                    // Skip rate groups with 0 passengers
                    if ($pax <= 0) {
                        continue;
                    }

                    $perPersonTotal = $perPersonRate + $perPersonTax + $perPersonPermitFee;
                    $totalForGroup = $perPersonTotal * $pax;

                    // Create unique group key (same logic as frontend)
                    $priceSignature = sprintf('%.2f|%.2f|%.2f', $perPersonRate, $perPersonTax, $perPersonPermitFee);
                    if ($mappingKey === 0) {
                        $groupKey = "{$rateId}_{$priceSignature}";
                    } else {
                        $groupKey = "{$mappingKey}_{$priceSignature}";
                    }

                    // Initialize rate group if not exists
                    if (!isset($paymentTypeBreakdown[$paymentType]['rateGroups'][$groupKey])) {
                        $paymentTypeBreakdown[$paymentType]['rateGroups'][$groupKey] = [
                            'rateFor' => $rateFor,
                            'mappingKey' => $mappingKey,
                            'pax' => 0,
                            'perPersonRate' => $perPersonRate,
                            'perPersonTax' => $perPersonTax,
                            'perPersonPermitFee' => $perPersonPermitFee,
                            'perPersonTotal' => $perPersonTotal,
                            'totalAmount' => 0
                        ];
                    }

                    // Aggregate pax and amounts
                    $paymentTypeBreakdown[$paymentType]['rateGroups'][$groupKey]['pax'] += $pax;
                    $paymentTypeBreakdown[$paymentType]['rateGroups'][$groupKey]['totalAmount'] += $totalForGroup;

                    // Update payment type totals
                    $paymentTypeBreakdown[$paymentType]['totalPax'] += $pax;
                    $paymentTypeBreakdown[$paymentType]['totalRate'] += ($perPersonRate * $pax);
                    $paymentTypeBreakdown[$paymentType]['totalTax'] += ($perPersonTax * $pax);
                    $paymentTypeBreakdown[$paymentType]['totalPermitFee'] += ($perPersonPermitFee * $pax);
                    $paymentTypeBreakdown[$paymentType]['totalAmount'] += $totalForGroup;
                }
            }
        }

        // Round all monetary values to 2 decimal places
        foreach ($paymentTypeBreakdown as $paymentType => &$typeData) {
            $typeData['totalRate'] = round($typeData['totalRate'], 2);
            $typeData['totalTax'] = round($typeData['totalTax'], 2);
            $typeData['totalPermitFee'] = round($typeData['totalPermitFee'], 2);
            $typeData['totalAmount'] = round($typeData['totalAmount'], 2);

            // Round transaction-based metrics
            $typeData['transactionAmount'] = round($typeData['transactionAmount'], 2);
            $typeData['refundAmount'] = round($typeData['refundAmount'], 2);
            $typeData['feeAmount'] = round($typeData['feeAmount'], 2);
            $typeData['netAmount'] = round($typeData['netAmount'], 2);

            foreach ($typeData['rateGroups'] as &$rateGroup) {
                $rateGroup['totalAmount'] = round($rateGroup['totalAmount'], 2);
            }
            unset($rateGroup); // CRITICAL: Break the reference to avoid issues in subsequent loops

            // Create categorized structure for accordion display
            // Group rate groups by mapping_key (unique identifier for each rate type)
            $rateCategories = [];

            foreach ($typeData['rateGroups'] as $groupKey => $rateGroup) {
                // Use mapping_key as the category identifier
                // If mapping_key is 0, use 'Default' as the category
                $mappingKey = $rateGroup['mappingKey'];
                $categoryKey = $mappingKey !== 0 ? $mappingKey : 'Default';

                // Initialize category if not exists
                if (!isset($rateCategories[$categoryKey])) {
                    $rateCategories[$categoryKey] = [
                        'categoryKey' => $categoryKey,
                        'categoryName' => $mappingKey !== 0 ? $rateGroup['rateFor'] : 'Default',
                        'mappingKey' => $mappingKey,
                        'totalPax' => 0,
                        'totalAmount' => 0,
                        'rateGroups' => []
                    ];
                }

                // Add rate group to category
                $rateCategories[$categoryKey]['rateGroups'][] = [
                    'groupKey' => $groupKey,
                    'rateFor' => $rateGroup['rateFor'],
                    'pax' => $rateGroup['pax'],
                    'perPersonRate' => $rateGroup['perPersonRate'],
                    'perPersonTax' => $rateGroup['perPersonTax'],
                    'perPersonPermitFee' => $rateGroup['perPersonPermitFee'],
                    'perPersonTotal' => $rateGroup['perPersonTotal'],
                    'totalAmount' => $rateGroup['totalAmount'],
                    'mappingKey' => $rateGroup['mappingKey']
                ];

                // Aggregate category totals
                $rateCategories[$categoryKey]['totalPax'] += $rateGroup['pax'];
                $rateCategories[$categoryKey]['totalAmount'] += $rateGroup['totalAmount'];
            }

            // Round category totals to 2 decimal places
            foreach ($rateCategories as &$category) {
                $category['totalAmount'] = round($category['totalAmount'], 2);
            }
            unset($category);

            // Add categorized structure to payment type data
            $typeData['rateCategories'] = $rateCategories;
        }
        unset($typeData); // Break reference

        return $paymentTypeBreakdown;
    }
}
