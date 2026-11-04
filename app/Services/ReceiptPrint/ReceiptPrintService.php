<?php

namespace App\Services\ReceiptPrint;

use Exception;
use finfo;
use Illuminate\Support\Collection;

class ReceiptPrintService
{
    /**
     * Format a number as a string with 2 decimal places for iOS JSON parsing.
     */
    private function toFixed(float|int|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * Format a number as a string with 2 decimal places, prefixed with currency symbol.
     */
    private function toFixedWithSymbol(float|int|null $value, string $symbol): string
    {
        return $symbol . number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * Get all receipt data for a booking.
     * note: the IOS app wants all values as strings so it's easier to handle JSON parsing.
     * @param object $bookingM Main booking object
     * @param Collection $bookingT Booking totals collection
     * @param array $pricedAddons Priced add-ons data
     * @param array $payLaterAddons Pay later add-ons data
     * @param array $tipAmounts Tip amounts data
     * @param array $customChargePrice Custom charge price data
     * @param float $paymentOnlyCheck Payment only check value
     * @param float $refundAmount Total refund amount
     * @param Collection|null $paymentTransactions Payment transactions
     * @param bool $includeLogo Whether to include base64 logo data
     *
     * @return array
     */
    public function getReceiptData(
        object $bookingM,
        Collection $bookingT,
        array $pricedAddons,
        array $payLaterAddons,
        array $tipAmounts,
        array $customChargePrice,
        float $paymentOnlyCheck,
        float $refundAmount = 0,
        ?Collection $paymentTransactions = null,
        bool $includeLogo = true
    ): array {
        $logoData = $this->getLogoData($bookingM, $includeLogo);
        $totals = $this->calculateTotals($bookingM, $bookingT, $pricedAddons, $payLaterAddons, $tipAmounts, $customChargePrice);

        $currencyCode = currencyFormation(true, false);
        $currencySymbol = currencyFormation(false, true);

        $rateGroups = $this->formatRateGroups($bookingT, $bookingM->is_group_booking == '1', $currencySymbol);
        $payments = $this->formatPaymentHistory($paymentTransactions, $currencySymbol);

        return [
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'bookingId' => bookingPrefix($bookingM->id),
            'tourOperator' => [
                'name' => $bookingM->tour_operator,
                'logo' => $logoData['url'],
                'logoBase64' => $logoData['base64'],
                'email' => $bookingM->tour_op_email ?? null,
                'phone' => $bookingM->tour_op_phone ?? null,
                'website' => $bookingM->tour_op_website ?? null,
            ],
            'customer' => [
                'name' => $bookingM->customer,
                'email' => $bookingM->cust_email ?? null,
                'phone' => $bookingM->cust_phone ?? null,
            ],
            'tour' => [
                'package' => $bookingM->tour_package,
                'date' => formatDate($bookingM->date),
                'time' => $bookingM->package_has_slots ? date('g:i A', strtotime($bookingM->time_from)) : null,
                'dateTime' => $bookingM->package_has_slots
                    ? formatDateTime($bookingM->date . ' ' . $bookingM->time_from)
                    : formatDate($bookingM->date),
                'hasSlots' => (bool) $bookingM->package_has_slots,
            ],
            'rateGroups' => $rateGroups,
            'pricing' => [
                'ticketCost' => $this->toFixedWithSymbol($totals['ticketCost'], $currencySymbol),
                'subtotal' => $this->toFixedWithSymbol($totals['subtotal'], $currencySymbol),
                'fee' => $this->toFixedWithSymbol($totals['fee'], $currencySymbol),
                'totalTax' => $this->toFixedWithSymbol($totals['totalTax'], $currencySymbol),
                'totalTaxAndFees' => $this->toFixedWithSymbol($totals['totalTaxAndFees'], $currencySymbol),
                'totalCost' => $this->toFixedWithSymbol($totals['totalCost'], $currencySymbol),
                'discount' => $this->toFixedWithSymbol($totals['discount'], $currencySymbol),
                'discountPercentage' => $this->toFixed($bookingM->discount2_percentage ?? 0),
                'addons' => $this->toFixedWithSymbol($totals['addons'], $currencySymbol),
                'addonsFee' => $this->toFixedWithSymbol($totals['addonsFee'], $currencySymbol),
                'tip' => $this->toFixedWithSymbol($totals['tip'], $currencySymbol),
                'customCharge' => $this->toFixedWithSymbol($totals['customCharge'], $currencySymbol),
                'customChargeTax' => $this->toFixedWithSymbol($totals['customChargeTax'], $currencySymbol),
                'refundAmount' => $this->toFixedWithSymbol($refundAmount, $currencySymbol),
                'paymentTotal' => $this->toFixedWithSymbol($paymentOnlyCheck > 0 ? $paymentOnlyCheck : $totals['totalCost'], $currencySymbol),
            ],
            'paymentMethod' => $bookingM->via ?? 'N/A',
            'payments' => $payments,
            'isGroupBooking' => $bookingM->is_group_booking == '1',
            'status' => $bookingM->status,
            'createdAt' => formatDateTime($bookingM->created_at),
        ];
    }

    /**
     * Get logo URL and optionally base64 data for thermal printing.
     *
     * @param object $bookingM
     * @param bool $includeBase64 Whether to include base64 encoded logo data
     * @return array ['url' => string|null, 'base64' => string|null]
     */
    public function getLogoData(object $bookingM, bool $includeBase64 = true): array
    {
        $logo = null;
        $logoBase64 = null;

        if (isset($bookingM->tour_op_logo_print) && $bookingM->tour_op_logo_print != '') {
            $logo = tourOperatorPrintLogo($bookingM->tenant_id, $bookingM->tour_op_logo_print);
        } elseif (isset($bookingM->tour_op_logo) && $bookingM->tour_op_logo != '') {
            $logo = tourOperatorPrintLogo($bookingM->tenant_id, $bookingM->tour_op_logo);
        }

        if ($logo && $includeBase64) {
            try {
                $logoContent = @file_get_contents($logo);
                if ($logoContent !== false) {
                    $mimeType = 'image/png';
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $detectedType = $finfo->buffer($logoContent);
                    if ($detectedType) {
                        $mimeType = $detectedType;
                    }
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoContent);
                }
            } catch (Exception $e) {
                // Logo base64 conversion failed, will use URL fallback
            }
        }
        return [
            'url' => $logo,
            'base64' => $logoBase64,
        ];
    }

    /**
     * Calculate all totals for the receipt.
     *
     * @param object $bookingM
     * @param Collection $bookingT
     * @param array $pricedAddons
     * @param array $payLaterAddons
     * @param array $tipAmounts
     * @param array $customChargePrice
     * @return array
     */
    public function calculateTotals(
        object $bookingM,
        Collection $bookingT,
        array $pricedAddons,
        array $payLaterAddons,
        array $tipAmounts,
        array $customChargePrice
    ): array {
        $ticketCost = 0;
        $totalFee = 0;
        $rateGroupTax = 0;
        $totalPermitFees = 0;
        foreach ($bookingT as $bkt) {
            if (isset($bkt->tourists) && $bkt->tourists > 0) {
                $tax = (float) ($bkt->additional_charge ?? 0);
                $permitFee = (float) ($bkt->permit_fee ?? 0);
                if ($bookingM->is_group_booking == '1') {
                    // Group booking: flat rate (no multiply by tourists), no permit fee
                    $rate = ($bkt->rate ?? 0) + $tax + ($bkt->partner_fees_amount ?? 0);
                    $percentage = (($bkt->additional_tax_percentage ?? 0) + ($bkt->processing_charge_percentage ?? 0)) / 100;
                    $subtotal = $rate + ($rate * $percentage);
                    $ticketCost += $subtotal;
                    $rateGroupTax += $tax;
                } else {
                    // Non-group booking: per-person rate, includes permit fee
                    $rate = ($bkt->rate ?? 0) + $tax + $permitFee + ($bkt->partner_fees_amount ?? 0);
                    $percentage = (($bkt->additional_tax_percentage ?? 0) + ($bkt->processing_charge_percentage ?? 0)) / 100;
                    $subtotal = $rate + ($rate * $percentage);
                    $ticketCost += $subtotal * $bkt->tourists;
                    $rateGroupTax += $tax * $bkt->tourists;
                    $totalPermitFees += $permitFee * $bkt->tourists;
                }
            }
        }

        $subtotal = $ticketCost;
        $discount = $bookingM->discount2_value ?? 0;
        $afterDiscount = $subtotal - $discount;

        // Service fee / processing fee
        $serviceFee = $bookingM->service_commission ?? 0;
        $totalFee = $serviceFee;

        // Add-ons
        $addonsTotal = 0;
        $addonsFee = 0;
        if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0) {
            $addonsTotal = $payLaterAddons['addons_price'];
            $addonsFee = $payLaterAddons['addons_fee'] ?? 0;
        } elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0) {
            $addonsTotal = $pricedAddons['addons_final_price'];
            $addonsFee = $pricedAddons['addons_final_fee'] ?? 0;
        }

        // Tip
        $tipAmount = $tipAmounts['tip_amount'] ?? 0;

        // Custom charge with tax
        $customChargeAmount = $customChargePrice['custom_charge_total'] ?? 0;
        $customChargeTax = $customChargePrice['custom_charge_fees'] ?? 0;
        $customChargeTotal = $customChargeAmount + $customChargeTax;

        $totalCost = $afterDiscount + $totalFee + $addonsTotal + $tipAmount + $customChargeTotal;

        // Total tax: rate group tax + custom charge tax
        $totalTax = $rateGroupTax + $customChargeTax;
        // Total tax and fees: all taxes + service fee + permit fees
        $totalTaxAndFees = $totalTax + $totalFee + $totalPermitFees;

        return [
            'ticketCost' => $ticketCost,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'fee' => $totalFee,
            'addons' => $addonsTotal,
            'addonsFee' => $addonsFee,
            'tip' => $tipAmount,
            'customCharge' => $customChargeAmount,
            'customChargeTax' => $customChargeTax,
            'totalTax' => $totalTax,
            'totalTaxAndFees' => $totalTaxAndFees,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * Format rate groups with detailed breakdown for receipt display.
     *
     * @param Collection $bookingT
     * @param bool $isGroupBooking
     * @param string $currencySymbol
     * @return array
     */
    public function formatRateGroups(Collection $bookingT, bool $isGroupBooking, string $currencySymbol): array
    {
        $rateGroups = [];

        foreach ($bookingT as $bt) {
            // Guests (tourists)
            if (isset($bt->tourists) && $bt->tourists > 0) {
                $guests = (int) $bt->tourists;
                $price = (float) ($bt->rate ?? 0);
                $permitFee = (float) ($bt->permit_fee ?? 0);
                $tax = (float) ($bt->additional_charge ?? 0);

                $totalForRate = ($price + $permitFee + $tax) * $guests;

                $rateGroups[] = [
                    'type' => 'guest',
                    'label' => $isGroupBooking ? 'Guest(s)' : ($bt->rate_for ?? 'Guest(s)'),
                    'count' => (int) $guests,
                    'price' => $this->toFixedWithSymbol($price, $currencySymbol),
                    'priceTotal' => $this->toFixedWithSymbol($price * $guests, $currencySymbol),
                    'permitFee' => $this->toFixedWithSymbol($permitFee, $currencySymbol),
                    'permitFeeTotal' => $this->toFixedWithSymbol($permitFee * $guests, $currencySymbol),
                    'tax' => $this->toFixedWithSymbol($tax, $currencySymbol),
                    'taxTotal' => $this->toFixedWithSymbol($tax * $guests, $currencySymbol),
                    'taxLabel' => $bt->permit_fee_label ?? 'Tax',
                    'totalForRate' => $this->toFixedWithSymbol($totalForRate, $currencySymbol),
                ];
            }

            // Infants (if any)
            if (isset($bt->infants) && $bt->infants > 0) {
                $infants = (int) $bt->infants;
                // Infants typically have zero cost
                $rateGroups[] = [
                    'type' => 'infant',
                    'label' => 'Infants',
                    'count' => (int) $infants,
                    'price' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'priceTotal' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'permitFee' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'permitFeeTotal' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'tax' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'taxTotal' => $this->toFixedWithSymbol(0, $currencySymbol),
                    'taxLabel' => $bt->permit_fee_label ?? 'Tax',
                    'totalForRate' => $this->toFixedWithSymbol(0, $currencySymbol),
                ];
            }
        }

        return $rateGroups;
    }

    /**
     * Format payment history for receipt display.
     *
     * @param Collection|null $transactions
     * @param string $currencySymbol
     * @return array
     */
    public function formatPaymentHistory(?Collection $transactions, string $currencySymbol): array
    {
        if (!$transactions || $transactions->isEmpty()) {
            return [];
        }

        $payments = [];

        foreach ($transactions as $transaction) {
            $payments[] = [
                'date' => formatDate($transaction->created_at),
                'time' => date('g:i A', strtotime($transaction->created_at)),
                'dateTime' => formatDateTime($transaction->created_at),
                'amount' => $this->toFixedWithSymbol($transaction->amount, $currencySymbol),
                'method' => $transaction->via ?? 'N/A',
                'status' => $transaction->status ?? 'Completed',
            ];
        }

        return $payments;
    }
}
