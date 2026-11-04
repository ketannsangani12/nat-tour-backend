<?php

namespace App\Services\SwitchTourPackage;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Services\Booking\BookingService;
use App\Services\Booking\EditBookingService;
use App\Services\Booking\HistoryService;
use App\Services\Booking\PaymentLinkService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwitchTourPackagePaymentLinkService
{
    public static function run($request)
    {
        DB::beginTransaction();
        try {
            $isValidationCheck = $request->get('validation_check') === 'true';
            $tourPackageId     = $request->tour_package_switch;
            $slotId            = $request->tour_slot_time_switch;
            $userType          = userType();

            $info = TourPackageService::getTourPackageSlotInfo($slotId);
            if (!$info) {
                throw new Exception('Unable to switch tour package');
            }

            $tourBooking          = TourBooking::find($request->bookingID);
            $isPartnerBooking     = $tourBooking->is_partner_booking;
            $oldDiscount          = $tourBooking->discount2_value;
            $oldDiscountPercentage = $tourBooking->discount2_percentage;
            $isGroupBooking       = $tourBooking->is_group_booking;
            $oldTourPackageId     = $tourBooking->tour_package_id;
            $oldSlotId            = $tourBooking->tour_slot_id;
            $oldPackage           = TourPackage::findOrFail($oldTourPackageId);
            $customer             = Customer::where('id', $tourBooking->customer_id)->withTrashed()->first();

            $rateGroups = TourPackageRateService::getMappedRateGroups($tourBooking, $customer, $info);
            if (!$rateGroups) {
                $message = 'No rate groups found. Please add rate groups for the package.';
                return sendJsonErrMsg($message);
            }

            $createdByWithName = BookingService::createdUserInfo();
            $commissionRates   = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);
            $natFeesPercentage    = $commissionRates['nat_fees_percentage'];
            $stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];

            $result               = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
            $subTotal             = $result['subTotal'];
            $serviceCommissionTotal = $result['serviceCommissionTotal'];
            $partnerFeesTotal     = $result['partnerFeesTotal'];
            $tbTransact           = $result['tbTransact'];
            $addedPeoplesTexts    = $result['addedPeoplesTexts'];

            // Payment link payments always include platform fees (same as Card)
            if ($oldDiscount > 0) {
                $total = $subTotal - $oldDiscount;
                $serviceCommissionTotal = roundout(($total * $natFeesPercentage / 100), 2);
                $total = round($total + $serviceCommissionTotal, 2);
            } else {
                $total = round($subTotal + $serviceCommissionTotal, 2);
            }

            $newDiscountPercent = 0;
            if ($oldDiscountPercentage > 0) {
                $newDiscountedTotalWithoutServiceCharge = $subTotal - $oldDiscount;
                $newDiscountPercent = round((($subTotal - $newDiscountedTotalWithoutServiceCharge) * 100) / $subTotal, 2);
            }

            $refundType = '';
            if ($request->has('refund_type')) {
                $refundType = $request->refund_type;
                if ($refundType == 'partial_refund') {
                    $refundType = 'full_refund';
                }
                if ($refundType == 'dont_refund') {
                    $refundType = '100_refund';
                }
            }

            $remainingAmounts        = SwitchTourPackageCashService::calculateRemainingAmount($request->bookingID, $refundType);
            $paymentAmount           = $total;
            $refundTicketCostAmount  = $remainingAmounts['ticketCostAmount'];
            $refundPlatformFeesAmount = $remainingAmounts['platformFeesAmount'];
            $remainingAmount         = $remainingAmounts['netAmount'];

            // First pass: return validation result (same shape as Card service)
            if ($isValidationCheck) {
                if ($paymentAmount > $remainingAmount) {
                    $netAmount   = $paymentAmount - $remainingAmount;
                    $feesAmount  = roundout($serviceCommissionTotal - $refundPlatformFeesAmount, 2);
                    $feesAmount  = max(0, $feesAmount);
                    return ['payment' => 1, 'amount' => round($netAmount, 2), 'fees' => $feesAmount];
                } else if ($remainingAmount > $paymentAmount) {
                    return ['payment' => 0];
                }
                // Equal: no payment/refund needed — fall through to complete the switch
            }

            // Net amounts for second pass
            if ($paymentAmount > $remainingAmount) {
                $netAmount          = round($paymentAmount - $remainingAmount, 2);
                $netPlatFormFeesAmount = max(0, roundout($serviceCommissionTotal - $refundPlatformFeesAmount, 2));
                $feesAmount         = roundout($netPlatFormFeesAmount, 2);
            } else if ($remainingAmount == $paymentAmount) {
                $netAmount  = 0;
                $feesAmount = 0;
            } else {
                // Refund case: not supported for Payment Link — return error
                DB::rollBack();
                return sendJsonErrMsg('Pay with Link is not available when a refund is due. Please use a different payment method.');
            }

            // Soft-delete old rate groups and insert new ones
            $oldRateGroupsQry = DB::table('tour_bookings_transact AS tbt')
                ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->where('tbt.tour_booking_id', $request->bookingID)
                ->where('tpr.tour_package_id', $tourBooking->tour_package_id);

            $oldRateGroups = $oldRateGroupsQry->select(['tpr.rate_for', 'tbt.tourists', 'tbt.infants'])->get();

            $deletedPeoplesTexts = [];
            foreach ($oldRateGroups as $oldRateGroup) {
                $deletedPeoplesTexts[] = $isGroupBooking == '1'
                    ? 'Person(s) ' . $oldRateGroup->tourists
                    : $oldRateGroup->rate_for . ' person ' . ($oldRateGroup->infants > 0 ? $oldRateGroup->infants : $oldRateGroup->tourists);
            }

            $oldRateGroupsQry->update([
                'tbt.deleted_at' => Carbon::now(),
                'tbt.deleted_by' => Auth::id(),
            ]);
            DB::commit();

            DB::table('tour_bookings_transact')->insert($tbTransact);
            DB::commit();

            $serviceCommissionUpdate = DB::raw('service_commission+' . $feesAmount);

            $updates = [
                'tour_package_id'      => $tourPackageId,
                'tour_slot_id'         => $slotId,
                'is_partner_booking'   => ($info->is_partner_package != 1) ? 0 : $isPartnerBooking,
                'service_commission'   => $serviceCommissionUpdate,
                'discount2_percentage' => $newDiscountPercent,
                'total'                => DB::raw('total+' . $netAmount),
                'booking_total'        => DB::raw('booking_total+' . $netAmount),
                'updated_at'           => mysqlDT(),
                'updated_by'           => Auth::id(),
                'updated_by_user_type' => $userType,
            ];
            $tourBooking->update($updates);
            DB::commit();

            $swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Payment Link');
            DB::commit();

            $historyData = [
                'old_tour_package_id' => $oldTourPackageId,
                'new_tour_package_id' => $tourPackageId,
                'old_package'         => $oldPackage->name,
                'new_package'         => $info->package_name,
                'added'               => $addedPeoplesTexts,
                'deleted'             => $deletedPeoplesTexts,
            ];

            // Create a remaining_payment link (with link item) and send the email
            if ($netAmount > 0) {
                if (!hasSetting('PAYMENT_LINK')) {
                    throw new Exception('Payment link feature is not enabled.');
                }

                $freshBooking = TourBooking::find($request->bookingID);
                $slotDateTime = Carbon::parse($info->date . ' ' . $info->time_from);

                // getTourPackageSlotInfo() omits fields sendPaymentLinkEmail() needs
                // (tour_op_phone, important_notes, packageEmail, etc.) — use getTourSlotInfo().
                $infoForEmail = TourPackageService::getTourSlotInfo($freshBooking->tour_slot_id, $userType);

                // upsertRemainingPaymentLink sets section='remaining_payment', creates the
                // tour_booking_payment_link_items row, and sends the email in one call.
                PaymentLinkService::upsertRemainingPaymentLink(
                    $freshBooking->group_id,
                    [
                        'type'         => 'switch_package',
                        'reference_id' => null,
                        'amount'       => $netAmount,
                        'reason'       => 'Switch Package - Remaining Payment',
                        'details'      => json_encode([
                            'tour_booking_id'      => $freshBooking->id,
                            'tourists'             => $freshBooking->tourists + $freshBooking->infants,
                            'ticket_cost'          => round($netAmount - $feesAmount, 2),
                            'platform_fees'        => $feesAmount,
                            'added_transact_data'  => [],
                            'created_by_with_name' => $createdByWithName,
                        ]),
                    ],
                    $freshBooking,
                    $slotDateTime,
                    $infoForEmail
                );
            }

            HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
            DB::commit();

            SwitchTourPackageCashService::updatePackagesSlotSummary($tourBooking->tourists, $oldSlotId, $slotId);
            DB::commit();

            return [
                'bookingId'    => $tourBooking->id,
                'bookingToken' => Crypt::encryptString($tourBooking->id),
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
