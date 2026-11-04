<?php

namespace App\Services\SwitchTourPackage;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Services\Booking\BookingService;
use App\Services\Booking\EditBookingService;
use App\Services\Booking\HistoryService;
use App\Services\TourPackage\TourPackageService;
use App\Services\TourPackageRate\TourPackageRateService;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Exception;
use App\Utils\BookingUtils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SwitchTourPackagePayLaterService
{
	/**
	 * Execute the service.
	 *
	 * @return mixed
	 */
	public static function run($request)
	{
		DB::beginTransaction();
		try {
			$tourPackageId = $request->tour_package_switch;
			$slotId = $request->tour_slot_time_switch;
			$userType = userType();

			$info = TourPackageService::getTourPackageSlotInfo($slotId);

			if (!$info) {
				throw new Exception('Unable to switch tour package');
			}

			$tourBooking = TourBooking::find($request->bookingID);
			$isPartnerBooking = $tourBooking->is_partner_booking;
			$oldDiscount = $tourBooking->discount2_value;
			$oldDiscountPercentage = $tourBooking->discount2_percentage;
			$isGroupBooking = $tourBooking->is_group_booking;
			$oldTourPackageId = $tourBooking->tour_package_id;
			$oldSlotId = $tourBooking->tour_slot_id;
			$oldPackage = TourPackage::findOrFail($oldTourPackageId);
			$customer = Customer::where('id', $tourBooking->customer_id)->withTrashed()->first();

			$rateGroups = TourPackageRateService::getMappedRateGroups($tourBooking, $customer, $info);

			if (!$rateGroups) {
				$message = "No rate groups found. Please add rate groups for the package.";
				if ($request->is('api/*')) {
					throw new Exception($message);
				}
				return sendJsonErrMsg($message);
			}

			$commissionRates = TourPackageService::getCommissionFeesPercentage($request, $customer, $info);

			// Assign the returned values to respective variables
			$natFeesPercentage = $commissionRates['nat_fees_percentage'];
			$stripeFeesPercentage = $commissionRates['stripe_fees_percentage'];
			$result = EditBookingService::processCalculationsSwitchPackage($rateGroups, $isGroupBooking, $info->is_partner_package, $request, $customer, $oldDiscount, $natFeesPercentage, $stripeFeesPercentage, $tourBooking, $userType);
			
			$subTotal = $result['subTotal'];
			$total = $result['total'];
			$tbTransact = $result['tbTransact'];
			$addedPeoplesTexts = $result['addedPeoplesTexts'];

			$newDiscountPercent = 0;
			if ($oldDiscountPercentage > 0) {
				$newDiscountedTotalWithoutServiceCharge = $subTotal - $oldDiscount;
				$newDiscountPercent = round((($subTotal - $newDiscountedTotalWithoutServiceCharge) * 100) / $subTotal, 2);
			}

			$oldRateGroupsQry = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->bookingID)
				->where('tpr.tour_package_id', $tourBooking->tour_package_id);

			$oldRateGroups = $oldRateGroupsQry->select(['tpr.rate_for', 'tbt.tourists', 'tbt.infants'])->get();

			$deletedPeoplesTexts = array();
			foreach ($oldRateGroups as $oldRateGroup) {
				if ($isGroupBooking == '1') {
					$deletedPeoplesTexts[] = "Person(s) " . $oldRateGroup->tourists;
				} else {
					$deletedPeoplesTexts[] = $oldRateGroup->rate_for . " person " . ($oldRateGroup->infants > 0 ? $oldRateGroup->infants : $oldRateGroup->tourists);
				}
			}
			
			$oldRateGroupsQry->update([
				'tbt.deleted_at' => Carbon::now(),
				'tbt.deleted_by' => Auth::id()
			]);
			DB::commit();

			// Payment link query start
			$tourBookingPaymentLink = PaymentLinkService::findLumpSumLink($tourBooking->group_id);
			if ($tourBookingPaymentLink) {
				$infoDetails = BookingUtils::getBookingDetails($tourBooking->id);
				$data = [
					'amount' => $total,
					'oldTotalAmount' => $request->old_total_amount
				];
				PaymentLinkService::paymentLinkUpdate($data, $infoDetails, $tourBooking, $tourBookingPaymentLink, 'Switch Tour Package');
			}
			// Payment link query end

			$tourBooking->tour_package_id = $tourPackageId;
			$tourBooking->tour_slot_id = $slotId;
			$tourBooking->discount2_percentage = $newDiscountPercent;
			if ($info->is_partner_package == 1 && $tourBooking->is_partner_booking != 1) {
				$tourBooking->is_partner_booking = 1;
			} else if ($info->is_partner_package != 1) {
				$tourBooking->is_partner_booking = 0;
			}
			$tourBooking->total = $subTotal;
			$tourBooking->booking_total = $subTotal;
			$tourBooking->updated_at = Carbon::now();
			$tourBooking->save();
			DB::commit();

			DB::table('tour_bookings_transact')->insert($tbTransact);
			DB::commit();

			$swipeHistoryId = HistoryService::storeSwitchPackageInPackageHistory($tourBooking, $oldTourPackageId, $tourPackageId, 'Pay Later');
			DB::commit();

			$createdByWithName = BookingService::createdUserInfo();

			$historyData = [
				'old_tour_package_id' => $oldTourPackageId,
				'new_tour_package_id' => $tourPackageId,
				'old_package' => $oldPackage->name,
				'new_package' => $info->package_name,
				'added' => $addedPeoplesTexts,
				'deleted' => $deletedPeoplesTexts
			];

			HistoryService::storeSwitchPackageInHistory($tourBooking, $swipeHistoryId, $historyData, $createdByWithName);
			DB::commit();
			SwitchTourPackageCashService::updatePackagesSlotSummary($tourBooking->tourists, $oldSlotId, $slotId);
			DB::commit();
			return [
				'bookingId' => $tourBooking->id,
				'bookingToken' => Crypt::encryptString($tourBooking->id)
			];
		} catch (Exception $exception) {
			DB::rollBack();
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
}
