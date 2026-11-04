<?php

namespace App\Services\Booking;

use App\Models\TourPromotion;
use Illuminate\Http\Request;

class DiscountService
{
    /**
     * Calculate the total after applying a discount when split payment is enabled.
     *
     * @param Request $request
     * @param float $total
     * @param int $tourPackageId
     * @param mixed $trans
     * @return float
     */
    public static function calculateTotalWithSplitDiscount($request, $total, $tourPackageId, $trans)
    {
        if (!empty($trans) || empty($request->split_value) || !$request->has('discount_type')) {
            return $total;
        }

        switch ($request->discount_type) {
            case 'Percentage':
                return !empty($request->discount_percentage)
                    ? $total - round(($total * $request->discount_percentage) / 100, 2)
                    : $total;

            case 'Fixed Money':
                return !empty($request->discount_fixed_money)
                    ? $total - round($request->discount_fixed_money, 2)
                    : $total;

            case 'Promo Code':
                if ($request->is_applied == 1 && !empty($request->discount_promo_code)) {
                    return self::applyPromoCodeDiscountWithSplit($request->discount_promo_code, $total, $tourPackageId);
                }
                return $total;
        }

        return $total;
    }
    /**
     * Calculate discount amount based on discount type.
     *
     * @param Request $request
     * @param float $total
     * @param int $tourPackageId
     * @return float
     */
    public static function calculateDiscount($request, $total, $tourPackageId)
    {
        if (!$request->has('discount_type')) {
            return 0;
        }

        switch ($request->discount_type) {
            case 'Percentage':
                return !empty($request->discount_percentage)
                    ? round(($total * $request->discount_percentage) / 100, 2)
                    : 0;

            case 'Fixed Money':
                return !empty($request->discount_fixed_money)
                    ? round($request->discount_fixed_money, 2)
                    : 0;

            case 'Promo Code':
                if ($request->is_applied == 1 && !empty($request->discount_promo_code)) {
                    return self::applyPromoCodeDiscount($request->discount_promo_code, $total, $tourPackageId);
                }
                return 0;
        }

        return 0;
    }
    /**
     * Apply discount based on a valid promo code.
     *
     * @param string $promoCode
     * @param float $total
     * @param int $tourPackageId
     * @return float
     */
    private static function applyPromoCodeDiscount($promoCode, $total, $tourPackageId)
    {
        $promoDetail = TourPromotion::where('code', $promoCode)
            ->where('tour_package_id', $tourPackageId)
            ->first();

        if (!$promoDetail) {
            return 0;
        }

        return ($promoDetail->discount_value_type === 'Percent')
            ? round(($total * $promoDetail->discount_value) / 100, 2)
            : round($promoDetail->discount_value, 2);
    }
    /**
     * Apply discount based on a valid promo code with split payment.
     *
     * @param string $promoCode
     * @param float $total
     * @param int $tourPackageId
     * @return float
     */
    private static function applyPromoCodeDiscountWithSplit($promoCode, $total, $tourPackageId)
    {
        $promoDetail = TourPromotion::where('code', $promoCode)
            ->where('tour_package_id', $tourPackageId)
            ->first();

        if (!$promoDetail) {
            return 0;
        }

        return ($promoDetail->discount_value_type === 'Percent')
            ? $total - round(($total * $promoDetail->discount_value) / 100, 2)
            : $total - round($promoDetail->discount_value, 2);
    }
    /**
     * Calculate old discount amount.
     *
     * @param mixed $tourBooking
     * @param float $total
     * @param object $request
     * @return array
     */
    public static function calculateOldDiscount(mixed $tourBooking, float $total, object $request): array
    {
        $oldDiscountAmt = 0;
        $info = is_array($tourBooking) ? $tourBooking[0] : $tourBooking;
        if (!empty($info->discount2_value) && $info->discount2_value > 0 && $info->discount2_percentage == 0) {
            $oldDiscountAmt = $info->discount2_value;
            if (empty($request->split_value) && $total > 0) {
                $total -= $info->discount2_value;
            }
        } elseif ($info->discount2_percentage > 0) {
            $oldDiscountAmt = number_format(($total * $info->discount2_percentage) / 100, 2, '.', '');
            if (empty($request->split_value) && $total > 0) {
                $total -= $oldDiscountAmt;
            }
        }

        return compact('oldDiscountAmt', 'total');
    }

    /**
     * Make Payment (Cash) with a "Paid amount": the cash actually owed, net of the discount
     * entered in the modal AND any discount already on the booking (e.g. from Pay Later). The
     * paid-amount check in paymentNewBooking() used to compare against the UNDISCOUNTED total —
     * a $450 booking with a $65 discount ($385 owed) paid with $400 was wrongly treated as a
     * $400 partial ("Split Payment") instead of a full $385 payment with $15 change.
     *
     * @param object $request     split_value must still be empty (it's the caller's precondition)
     * @param float  $total       the recomputed, undiscounted ticket total
     * @param array  $tourBooking paymentNewBooking()'s raw booking row(s)
     */
    public static function cashDueAfterDiscounts($request, float $total, array $tourBooking): float
    {
        $due = $total - (float) self::calculateDiscount($request, $total, $tourBooking[0]->tour_package_id);
        return round((float) self::calculateOldDiscount($tourBooking, $due, $request)['total'], 2);
    }

    /**
     * Make Payment → "Pay with Link": persist the discount entered in the modal on
     * tour_bookings_master BEFORE the link amount is computed. Previously the link branch
     * (PaymentLinkService::handleMakePaymentSection()) ran before any discount handling, so the
     * link was sent for the full undiscounted amount and discount2_* was never stored. The
     * customer's checkout (PaymentLinkService::bookingUpdate()) rebuilds the total from the
     * transact rates and subtracts discount2_value, so storing it here is what makes the link
     * charge the discounted amount. Same field updates as the Card/Cash path's
     * applyDiscountValues(): total/booking_total reduced, discount2_value/percentage set.
     *
     * @param object $request
     * @param array  $tourBooking paymentNewBooking()'s raw booking row(s) — [0]->total is updated
     * @param object $info        booking info — discount2_* are updated
     * @return float the discount applied (0 when none)
     */
    public static function applyPaymentLinkDiscount($request, array $tourBooking, object $info): float
    {
        if ($request->payment_method !== 'Payment Link' || !empty($request->remaining_link_id) || !self::hasEffectiveDiscount($request)) {
            return 0.0;
        }

        $booking = \App\Models\TourBooking::findOrFail($request->bookingID);
        $baseTotal = (float) $booking->total;
        $discountAmt = (float) self::calculateDiscount($request, $baseTotal, $booking->tour_package_id);
        if ($discountAmt <= 0) {
            return 0.0;
        }
        if ($discountAmt > $baseTotal) {
            throw new \Exception('Discount amount cannot exceed the ticket cost.');
        }

        $booking->total         = round($baseTotal - $discountAmt, 2);
        $booking->booking_total = round((float) $booking->booking_total - $discountAmt, 2);
        $booking->discount2_value = round(($booking->discount2_value > 0 ? (float) $booking->discount2_value : 0) + $discountAmt, 2);

        if ($request->discount_type === 'Percentage') {
            $booking->discount2_percentage = $request->discount_percentage;
        } elseif ($request->discount_type === 'Promo Code') {
            $promo = TourPromotion::where('code', $request->discount_promo_code)
                ->where('tour_package_id', $booking->tour_package_id)
                ->first();
            if ($promo && $promo->discount_value_type === 'Percent') {
                $booking->discount2_percentage = $promo->discount_value;
            }
            $booking->discount_promo_code = $request->discount_promo_code;
        }
        $booking->save();

        // Keep the caller's in-memory rows in sync for the link amount/fee calculation.
        $tourBooking[0]->total       = $booking->total;
        $info->discount2_value       = $booking->discount2_value;
        $info->discount2_percentage  = $booking->discount2_percentage;

        return $discountAmt;
    }
    /**
     * Whether the request represents an ACTUALLY effective discount, not just a discount-type
     * radio (Percentage/Fixed Money/Promo Code, name="discount_type") left checked with no
     * value entered — e.g. the user clicked "Percentage" then never typed a number, or a
     * manager-approval discount request was dismissed without approval. `$request->has('discount_type')`
     * alone only checks the radio's presence in the request, which callers were using to decide
     * whether to compute the service commission on the per-person-rounded-then-summed total (no
     * discount) or on the aggregate discounted total (discount applied) — a stray checked radio
     * with an empty value silently flipped bookings into the aggregate-rounded formula, which can
     * be off by a cent from the correct per-person figure (e.g. $14.71 vs $14.72 for 2 guests at
     * 9.809% commission). Mirrors the exact per-type gating calculateDiscountForCart() and
     * calculateDiscountForCreateBooking() already use internally, so this stays consistent with
     * whatever discount amount those functions would actually produce.
     *
     * @param Request $request
     * @return bool
     */
    public static function hasEffectiveDiscount($request): bool
    {
        if (!$request->has('discount_type') || empty($request->discount_type)) {
            return false;
        }

        switch ($request->discount_type) {
            case 'Percentage':
                return !empty($request->discount_percentage);
            case 'Fixed Money':
                return !empty($request->discount_fixed_money);
            case 'Promo Code':
                return $request->is_applied == 1 && !empty($request->discount_promo_code);
            default:
                return false;
        }
    }

    /**
     * Calculate the discount amount and percentage based on the discount type for cart.
     *
     * @param Request $request
     * @param float $total
     * @param float $cashTotal
     * @param object $info
     * @return array
     */
    public static function calculateDiscountForCart($request, $total, $cashTotal, $info)
    {
        $discountPercentage = 0;
        $discountAmt = 0;
        $discountAmtCash = 0;

        if (!$request->has('discount_type')) {
            return ['discount_percentage' => $discountPercentage, 'discount_amount' => $discountAmt, 'discount_amount_cash' => $discountAmtCash];
        }

        switch ($request->discount_type) {
            case 'Percentage':
                if (!empty($request->discount_percentage)) {
                    $discountPercentage = $request->discount_percentage;
                    $discountAmt = self::calculatePercentageDiscount($total, $discountPercentage);
                    $discountAmtCash = self::calculatePercentageDiscount($cashTotal, $discountPercentage);
                }
                break;

            case 'Fixed Money':
                if (!empty($request->discount_fixed_money)) {
                    $discountAmt = $discountAmtCash = round($request->discount_fixed_money, 2);
                }
                break;

            case 'Promo Code':
                if ($request->is_applied == 1) {
                    $discountData = self::applyPromoCode($request->discount_promo_code, $total, $cashTotal, $info);
                    $discountPercentage = $discountData['discount_percentage'];
                    $discountAmt = $discountData['discount_amount'];
                    $discountAmtCash = $discountData['discount_amount_cash'];
                }
                break;
        }

        return [
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmt,
            'discount_amount_cash' => $discountAmtCash
        ];
    }

    /**
     * Calculate the discount amount based on percentage.
     *
     * @param float $total
     * @param float $percentage
     * @return float
     */
    private static function calculatePercentageDiscount($total, $percentage)
    {
        return round(($total * $percentage) / 100, 2);
    }

    /**
     * Apply a promo code discount.
     *
     * @param string $promoCode
     * @param float $total
     * @param float $cashTotal
     * @param object $info
     * @return array
     */
    private static function applyPromoCode($promoCode, $total, $cashTotal, $info)
    {
        $promoDetail = TourPromotion::where('code', $promoCode)
            ->where('tour_package_id', $info->tour_package_id)
            ->first();

        if (!$promoDetail) {
            return ['discount_percentage' => 0, 'discount_amount' => 0, 'discount_amount_cash' => 0];
        }

        if ($promoDetail->discount_value_type === 'Percent') {
            $discountPercentage = $promoDetail->discount_value;
            $discountAmt = self::calculatePercentageDiscount($total, $discountPercentage);
            $discountAmtCash = self::calculatePercentageDiscount($cashTotal, $discountPercentage);
        } else {
            $discountPercentage = 0;
            $discountAmt = $discountAmtCash = round($promoDetail->discount_value, 2);

        }

        return [
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmt,
            'discount_amount_cash' => $discountAmtCash
        ];
    }

    /**
     * Adjust the discount percentage based on the new total and discount amount.
     *
     * @param object $tourBooking
     * @param float $discountAmt
     * @param float $total
     * @return array
     */
    public static function adjustDiscountPercentage($tourBooking, $discountAmt, $total)
    {
        $oldDiscountedTotalWithoutServiceCharge = $tourBooking->total;
        $originalTotalOld = $oldDiscountedTotalWithoutServiceCharge + $tourBooking->discount2_value;

        $newDiscountedTotalWithoutServiceCharge = $total;
        $originalTotalNew = $newDiscountedTotalWithoutServiceCharge + $discountAmt;

        $newDiscountPercent = round(
            100 - (
                ($oldDiscountedTotalWithoutServiceCharge + $newDiscountedTotalWithoutServiceCharge) * 100 /
                ($originalTotalOld + $originalTotalNew)
            ),
            2
        );
        //Ensure and validate that discount percentage proper and calculated correctly based on original totals and discount amount
        if ($newDiscountPercent > 0) {
            $finalOriginalTotal = $originalTotalOld + $originalTotalNew;
            $discountValue = round(($finalOriginalTotal * $newDiscountPercent) / 100, 2);
            if ($discountValue != $discountAmt) {
                return [
                    'newDiscountPercent' => $newDiscountPercent,
                    'newDiscountValue' => $discountValue,
                    'updatedTotal' => $finalOriginalTotal - $discountValue
                ];
            } else {
                return [
                    'discount_percentage' => $newDiscountPercent
                ];
            }
        } else {
            return [
                'discount_percentage' => $newDiscountPercent
            ];
        }
    }
}
