<?php

namespace App\Services\Booking;

use App\Constants\Constants;
use App\Events\BookingAffiliate;
use App\Events\BookingNew;
use App\Mail\TourBooking\TourBookingAcknowledgement;
use App\Models\Admin;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourOperator;
use App\Models\Transaction;
use App\Models\DiscountHistory;
use App\Models\SurchargeDate;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Models\TourPromotion;
use App\Models\TourSlotTime;
use App\Repositories\TourBooking\TourBookingFormsRepository;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Services\CardBookingService;
use App\Services\Payments\PlatformFeeMetadata;
use App\Services\SwitchTourPackage\SwitchTourPackageCardService;
use App\Services\Customer\CustomerService;
use App\Services\TimeSlot\TimeSlotService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Contracts\Payments\PaymentGatewayInterface;
use Stripe\PaymentIntent;
use App\Services\Booking\CustomChargeBookingService;
use App\Services\Booking\AddonBookingService;
use App\Services\TimeSlot\TouristService;
use App\Utils\BokunUtils;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Stripe\Customer as StripeCustomer;

class BookingService
{
	public $discountHistory = DiscountHistory::class;

	/**
	 * Execute the booking service to get the booking data.
	 * @param mixed $id
	 * @return object
	 */
	public static function booking($id)
	{
		$booking = TourBooking::findOrFail($id);
		return $booking;
	}

	/**
	 * Execute the information service to get the booking details in single raw.
	 *
	 * @param mixed $userType
	 * @param mixed $id
	 *
	 * @return object
	 */
	public static function information($userType, $id): object
	{
		// Affiliate details
		$affiliateSlotCount = 0;
		$showAllPackageBookings = 0;

		// Mirror TouristService::getTimeSlotInfo logic: count tourists from related packages when PACKAGE_RELATION is on,
		// otherwise count only from this booking's own slot+package.
		// Outer IN ensures each booking row is counted once (no JOIN multiplication).
		// Inner subquery flattens the two nested tour_package_families lookups into JOINs for index use.
		$slotAllTouristsCountSql = hasSetting('PACKAGE_RELATION')
			? "IFNULL((
				SELECT SUM(tbm2.tourists)
				FROM tour_bookings_master AS tbm2
				WHERE tbm2.status != 'Cancelled'
					AND tbm2.tour_slot_id IN (
						SELECT DISTINCT tst2.id
						FROM tour_slot_times AS tst2
						JOIN tour_slot_dates AS tsd2 ON tsd2.id = tst2.tour_slot_id
							AND tsd2.date = tsd.date
						JOIN tour_package_families AS tpf ON tpf.tour_package_id = tsd2.tour_package_id
							AND tpf.relation_year = YEAR(tsd.date)
							AND tpf.time_from = tst.time_from
						JOIN tour_package_families AS tpf_root ON tpf_root.parent_package_id = tpf.parent_package_id
							AND tpf_root.relation_year = tpf.relation_year
							AND tpf_root.tour_package_id = tp.id
						WHERE tst2.time_from = tst.time_from
					)
			), 0) AS slot_all_tourists_count"
			: "IFNULL((
				SELECT SUM(tbm2.tourists)
				FROM tour_bookings_master AS tbm2
				WHERE tbm2.tour_slot_id = tst.id
					AND tbm2.status != 'Cancelled'
					AND tbm2.tour_package_id = tp.id
			), 0) AS slot_all_tourists_count";

		if ($userType == 'Affiliate') {
			$slotDetails = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.id', $id)
				->first([
					'tst.time_from',
					'tst.time_to',
					'tst.date',
					'tbm.tour_package_id'
				]);

			$year = Carbon::parse($slotDetails->date)->format('Y');

			$affiliateSlotCount = DB::table('tour_slot_times_affiliate AS tsta')
				->where('tsta.time_from', $slotDetails->time_from)
				->where('tsta.time_to', $slotDetails->time_to)
				->where('tsta.tour_package_id', $slotDetails->tour_package_id)
				->count();

			$showAllPackageBookings = DB::table('package_affiliate_assignments AS paa')
				->where('paa.package_id', $slotDetails->tour_package_id)
				->where('paa.affiliate_id', Auth::id())
				->where('show_all_package_bookings', 1)
				->count();

			$affiliateSql = '';
			if ($showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliateSql = ' AND tbm2.customer_id = ' . Auth::id();
			}

			$selectFields = [
				'tbm.id',
				'tbm.hotel_id',
				'tbm.tour_package_id',
				'tbm.tour_guide_id',
				'tbm.tourists',
				'tbm.infants',
				'tbm.comments',
				'tbm.status as statusm',
				'tbm.discount',
				'tbm.discount2_value',
				'tbm.discount2_percentage',
				'tbm.discount_promo_code',
				'tbm.is_group_booking',
				'tbm.is_partner_booking',
				'tbm.group_id as order_id',
				'tbm.service_commission',
				'tbm.total',
				'tbm.note',
				'tbm.booking_source',
				'tbm.created_at',
				'tbm.created_by',
				'tbm.created_by_user_type',
				'tbm.liability_waiver_signing',
				'tbm.reference_booking',
				'tbm.customer_id',
				'tbm.name AS customer',
				'tbm.email AS cust_email',
				'tbm.phone_number AS cust_phone',
				'tbm.booking_total',
				'tbm.cart_id',
				'tbm.viator_bookingRef AS tripadvisor_id',
				'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
				'tbm.ota_reference_booking AS ota_booking_id',
				'tbm.external_booking_id',
				'tbm.is_reserved_for_affiliate',
				'tbm.expire_at',
				'tbm.is_partner_booking',
				'grouped.cart_booking_count as cartBookingsCount',
				'tp.tour_operator_id',
				'tp.name AS tour_package',
				't_o.stripe_destination_id',
				'tp.overbook_seats',
				'tp.package_has_slots',
				'tp.package_has_waiver',
				'tp.package_has_permit',
				'tp.bundle_package',
				'tp.checkin as is_check_in',
				'tp.package_colour',
				'tp.checkin_type',
				'tp.has_contacts',
				't.status',
				't.via',
				'tsd.date',
				'tst.id as time_id',
				'tst.time_from',
				'tst.time_to',
				'tst.id AS tour_slot_time_id',
				'tst.bookable_status AS slot_bookable_status',
				DB::raw("IFNULL(tst.time_slot_seats, 0) as slot_seats"),
				'c.is_affiliate',
				'c.is_price_show',
				'c.is_billable',
				't_o.name AS tour_operator',
				't_o.checkins AS tour_op_checkins',
				't_o.timezone AS timezone',
				't_o.logo AS tour_op_logo',
				't_o.logo_print AS tour_op_logo_print',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.checkins AS tour_op_checkins',
				't_o.tenant_id',
				DB::raw($slotAllTouristsCountSql),
				DB::raw("IFNULL((SELECT SUM(tbm2.tourists)
					FROM tour_bookings_master AS tbm2
					JOIN customers AS c ON c.id = tbm2.customer_id
					WHERE tbm2.tour_slot_id = tst.id
						AND c.is_affiliate = 'Yes'
						AND tbm2.status != 'Cancelled'
						AND tbm2.tour_package_id = tp.id
						" . $affiliateSql . "
					GROUP BY tbm2.tour_slot_id, tbm2.tour_package_id), 0) AS afl_tourists"),
				DB::raw('0 as afl_seats'),
				DB::raw("IFNULL((SELECT tpf.is_parent
                        FROM tour_package_families AS tpf
                        WHERE tpf.tour_package_id = tsd.tour_package_id
                            AND tst.time_from = tpf.time_from
                            AND tpf.tour_package_id = tp.id
							AND tpf.relation_year = $year
                        GROUP BY tpf.tour_package_id), 0) AS is_parent"),
				DB::raw("IFNULL((SELECT tpf.group_index
					FROM tour_package_families AS tpf
					WHERE tpf.tour_package_id = tsd.tour_package_id
						AND tst.time_from = tpf.time_from
						AND tpf.tour_package_id = tp.id
						AND tpf.relation_year = $year
					GROUP BY tpf.tour_package_id), 0) AS group_index")
			];
			if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
				$affiliateBookedSeats = DB::raw('(SELECT SUM(tbm2.tourists)
                    FROM tour_bookings_master AS tbm2
                    WHERE tbm2.tour_slot_id = tst.id
					AND tbm2.tour_package_id = tp.id
                        AND tbm2.status != "Cancelled"
                    GROUP BY tbm2.tour_slot_id) AS booked_seats');

				array_push($selectFields, $affiliateBookedSeats);
			}
		} else {
			$selectFields = [
				'tbm.id',
				'tbm.hotel_id',
				'tbm.tour_package_id',
				'tbm.tour_guide_id',
				'tbm.tourists',
				'tbm.infants',
				'tbm.comments',
				'tbm.status as statusm',
				'tbm.discount',
				'tbm.discount2_value',
				'tbm.discount2_percentage',
				'tbm.discount_promo_code',
				'tbm.service_commission',
				'tbm.total',
				'tbm.note',
				'tbm.booking_source',
				'tbm.created_at',
				'tbm.created_by',
				'tbm.created_by_user_type',
				'tbm.liability_waiver_signing',
				'tbm.reference_booking',
				'tbm.is_group_booking',
				'tbm.is_partner_booking',
				'tbm.group_id as order_id',
				'tbm.viator_bookingRef AS tripadvisor_id',
				'tbm.get_your_guide_reference_booking AS get_your_guide_booking_id',
				'tbm.ota_reference_booking AS ota_booking_id',
				'tbm.external_booking_id',
				'tbm.is_reserved_for_affiliate',
				'tbm.cart_id',
				'tbm.customer_id',
				'tbm.name AS customer',
				'tbm.email AS cust_email',
				'tbm.phone_number AS cust_phone',
				'tbm.booking_total',
				'tbm.expire_at',
				'tbm.is_partner_booking',
				'grouped.cart_booking_count as cartBookingsCount',
				'tp.tour_operator_id',
				'tp.name AS tour_package',
				't_o.stripe_destination_id',
				'tp.overbook_seats',
				'tp.package_has_slots',
				'tp.package_has_waiver',
				'tp.package_has_permit',
				'tp.bundle_package',
				'tp.checkin as is_check_in',
				'tp.checkin_type',
				'tp.has_contacts',
				'tp.package_colour',
				't.status',
				't.via',
				'tsd.date',
				'tst.id as time_id',
				'tst.time_from',
				'tst.time_to',
				'tst.id AS tour_slot_time_id',
				'tst.bookable_status AS slot_bookable_status',
				DB::raw("IFNULL(tst.time_slot_seats, 0) as slot_seats"),
				'c.is_affiliate',
				'c.is_price_show',
				'c.is_billable',
				't_o.name AS tour_operator',
				't_o.checkins AS tour_op_checkins',
				't_o.timezone AS timezone',
				't_o.logo AS tour_op_logo',
				't_o.logo_print AS tour_op_logo_print',
				't_o.email AS tour_op_email',
				't_o.phone_number AS tour_op_phone',
				't_o.website AS tour_op_website',
				't_o.checkins AS tour_op_checkins',
				't_o.tenant_id',
				DB::raw($slotAllTouristsCountSql)
			];
		}

		$departmentPackage = departmentPackage();

		$bookingM = DB::table('tour_bookings_master AS tbm')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
			->leftJoin('transactions As t', 't.tour_booking_id', '=', 'tbm.id')
			->leftJoin(DB::raw('
			(SELECT cart_id, COUNT(*) as cart_booking_count
			 FROM tour_bookings_master
			 GROUP BY cart_id) as grouped
		'), 'tbm.cart_id', '=', 'grouped.cart_id')
			->when($userType == 'Tour Operator', function ($query) {
				return $query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
			})
			->when($userType == 'Affiliate' && $affiliateSlotCount == 0 && $showAllPackageBookings == 0, function ($query) {
				return $query->where('tbm.customer_id', Auth::id());
			})
			->when($departmentPackage, function ($query) use ($departmentPackage) {
				return $query->whereIn('tp.id', $departmentPackage);
			})
			->where('tbm.id', $id)
			->first($selectFields);

		if (!$bookingM) {
			throw new Exception("Tour booking not found.");
		}

		$year = Carbon::parse($bookingM->date)->format('Y');

		if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ZERO) {
			/* START - mapping tour package and reducing the relational seats */
			$packageRelationSetting = hasSetting('PACKAGE_RELATION');
			$tourPackageFamily = [];
			$targetParent = [];
			if ($packageRelationSetting) {
				$tourPackageFamily = DB::select("SELECT *
				FROM tour_package_families
				WHERE relation_year = $year
				AND parent_package_id IN (
					SELECT parent_package_id
					FROM tour_package_families
					WHERE tour_package_id = $bookingM->tour_package_id
						AND relation_year = $year
				)");
			}
			/* END */

			/* START - mapping tour package and reducing the relational seats */
			$relationPackage = [];
			$totalTourists = 0;
			if ($packageRelationSetting && count($tourPackageFamily)) {
				$relationPackage = array_filter($tourPackageFamily, function ($obj) use ($bookingM) {
					return $obj->tour_package_id == $bookingM->tour_package_id && $obj->group_index == $bookingM->group_index;
				});

				$targetParent = array_filter($tourPackageFamily, function ($obj) use ($bookingM) {
					return $obj->is_parent == 1 && $obj->tour_package_id == $bookingM->tour_package_id && $obj->group_index == $bookingM->group_index;
				});

				$totalTourists = TouristService::countTotalTourists($tourPackageFamily, $bookingM);
			}
			$affiliateTimeSlots = DB::table('tour_slot_times_affiliate as tsta')
				->join('affiliate_slot_times as ast', 'ast.tour_slot_time_id', '=', 'tsta.id')
				->where('ast.affiliate_id', Auth::id())
				->where('tsta.tour_package_id', $bookingM->tour_package_id)
				->where('tsta.time_from', $bookingM->time_from)
				->where('tsta.status', 'Active')
				->where('tsta.deleted_by', 0)
				->select('tsta.id as time_slot_id', 'tsta.time_from', 'tsta.time_to', 'ast.seat_limit')
				->get()
				->toArray();
			$affiliateTimeSlots = (isset($affiliateTimeSlots) && !empty($affiliateTimeSlots)) ? $affiliateTimeSlots : [];
			$desiredSlot = collect($affiliateTimeSlots)->first(function ($slot) use ($bookingM) {
				return $slot->time_from === $bookingM->time_from;
			});

			// Actual seats calculation
			$bookedSeatsPerSlot = ((int) $totalTourists > 0 && !empty($relationPackage)) ? (int) $totalTourists : (int) $bookingM->booked_seats;
			$totalSeatsPerSlot = $bookingM->slot_seats;

			// Affiliate seats calculation
			$tourists = (int)$bookingM->afl_tourists;
			$seats = isset($desiredSlot->seat_limit) ? (($bookingM->slot_seats > 0) ? (($bookingM->slot_seats >= $desiredSlot->seat_limit) ? ($desiredSlot->seat_limit) : ($bookingM->slot_seats)) : 0) : 0;

			if ($bookedSeatsPerSlot >= $totalSeatsPerSlot || $tourists >= $seats) {
				$availableSeats = 0;
			} else {
				$globalTotal = $totalSeatsPerSlot - $bookedSeatsPerSlot;
				$affliateTotal = $seats - $tourists;
				$vacant = min($globalTotal, $affliateTotal);
				if ($vacant >= $seats) {
					$availableSeats = $seats;
				} else {
					$availableSeats = $vacant;
				}
			}
			$overbook = (isset($desiredSlot->seat_limit) && $bookingM->afl_tourists > $desiredSlot->seat_limit) ? abs($bookingM->afl_tourists - $desiredSlot->seat_limit) : 0;
			$bookingM->slot_seats = $seats;
			$bookingM->available_tourists = $availableSeats;
			$bookingM->slot_all_tourists_count = ($seats - $availableSeats) > 0 ? (string) (($seats - $availableSeats) + $overbook) : '0';
		} else if ($userType == 'Affiliate' && $showAllPackageBookings == Constants::STATUS_ONE) {
			$bookingM->available_tourists = 0;
		}

		if ($userType == 'Admin') {
			$timezone = $bookingM->timezone;
		} else if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
			$touroperatordetails = TourOperator::find(Auth::user()->tour_operator_id);
			$timezone = $touroperatordetails->timezone;
		} else {
			$timezone = $bookingM->timezone;
		}

		$created_at = $bookingM->created_at;
		$converted_date = Carbon::createFromFormat('Y-m-d H:i:s', $created_at, config('app.timezone'))
			->setTimezone($timezone)->format('Y-m-d H:i:s');

		if ($converted_date != '') {
			$bookingM->created_at = $converted_date;
		}

		if ($bookingM->package_has_slots) {
			$bookingM->tour_date_time = date('l, F jS Y @ g:i a - ', strtotime($bookingM->date . ' ' . $bookingM->time_from)) .
				date('g:i a', strtotime($bookingM->date . ' ' . $bookingM->time_to));
		} else {
			$bookingM->tour_date_time = date('l, F jS Y', strtotime($bookingM->date));
		}

		if ($bookingM->liability_waiver_signing != null) {
			$bookingM->liability_waiver_signing = static::liabilityWaiverSigning($bookingM);
		} else {
			$bookingM->liability_waiver_signing = array();
		}

		if ($userType == 'Affiliate') {
			$bookingM->overbook_seats = 0;
		}

		$bookingM->background_colour_class = BookingService::backgroundColourClass($bookingM);
		$bookingM->checkin_type = self::getBookingCheckInType($id, $bookingM->bundle_package, $bookingM->checkin_type, $bookingM->is_group_booking);

		/* Execute only when payment link feature is enabled and this feature is only for Admin/Tour Operator - START */
		$isPaymentLink = hasSetting('PAYMENT_LINK');
		$bookingM->paymentLinkEnabled = 0;

		if ($isPaymentLink) {
			$paymentLinkDays = getSettingsByKey('PAYMENT_LINK_DAYS');
			$daysPaymentLink = (int) $paymentLinkDays->value;

			$bookingDate = $bookingM->date;
			$bookingTime = $bookingM->time_from;
			$bookingSlotDateTime = Carbon::parse($bookingDate . ' ' . $bookingTime);
			$now = Carbon::now();

			if ($userType == "Affiliate") {
				$bookingM->paymentLinkEnabled = 0;
			}

			// Check if booking date is after now and not more than 3 days away
			if ($bookingSlotDateTime->gt($now) && $bookingSlotDateTime->lte($now->copy()->addDays($daysPaymentLink))) {
				$bookingM->paymentLinkEnabled = 1;
			}
		}
		/* Execute only when payment link feature is enabled and this feature is only for Admin/Tour Operator - End */
		$bookingM->ota_title = BokunUtils::getOTAChannelNameByBookingId($bookingM->id);

		return $bookingM;
	}

	/**
	 * Get the tour booking add-ons information with fees
	 * This method will provide multiple records
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public static function fetchAddonsWithPrice($id): array
	{
		$tourBookingAddons = DB::table('tour_booking_addons  as tba')
			->where('tba.booking_id', $id)
			->where('tba.booking_id', $id)
			->select(
				'tba.id',
				'tba.name',
				'tba.price',
				'tba.fee',
				'tba.status',
				'tba.refunded'
			)
			->get();

		$addonsPaymentPrice = 0;
		$addonsPaymentFee = 0;
		$addonsPaymentTotal = 0;
		$addonsRefundPrice = 0;
		$addonsRefundFee = 0;
		$addonsRefundTotal = 0;
		$addonsFinalPrice = 0;
		$addonsFinalFee = 0;
		$addonsFinalTotal = 0;
		if ($tourBookingAddons->count() > 0) {
			foreach ($tourBookingAddons as $key => $addon) {
				if ($addon->status == 'paid' || $addon->status == 'open') {
					$addonsPaymentPrice += $addon->price;
					$addonsPaymentFee += $addon->fee;
					$addonsPaymentTotal += $addon->price + $addon->fee;
				} else {
					$addonsRefundPrice += $addon->price;
					$addonsRefundFee += $addon->fee;
					$addonsRefundTotal += $addon->price + $addon->fee;
				}
			}

			if ($addon->status != 'open') {
				$addonsFinalPrice = $addonsPaymentPrice - $addonsRefundPrice;
				$addonsFinalFee = $addonsPaymentFee - $addonsRefundFee;
				$addonsFinalTotal = $addonsPaymentTotal - $addonsRefundTotal;
			}
		}

		// Gross lifetime total — every add-on ever charged on this booking, NOT netted against
		// later refunds (unlike addons_final_*/addons_payment_* above, which are both net-current
		// figures). Old-style rows keep their original price/fee on the row even once refunded
		// (see the else-branch above), so summing across both statuses recovers the true gross.
		$addonsGrossPrice = $addonsPaymentPrice + $addonsRefundPrice;
		$addonsGrossFee   = $addonsPaymentFee   + $addonsRefundFee;

		// New-style addons stored in tour_booking_addon_transactions.
		// Fields are now soft-deleted (not hard-deleted), so tour_booking_id on the transaction
		// row is the reliable join key.
		//
		// Charge and refund rows are summed SEPARATELY (each side via ABS(), then refund
		// subtracted from charge) rather than summed together in one raw SUM(), because refund
		// rows are NOT written with a consistent sign across the codebase: ChangePeopleAddonService
		// and one path in TourBookingAddonRepository store them negative, but
		// TourBookingAddonRepository's direct addon-edit refund path (ADDONS_FULL_REFUND /
		// ADDONS_TICKET_REFUND) stores them positive (see that method's own ABS()-based
		// capacity-check for the same acknowledged inconsistency). A single SUM(field_price+...)
		// across both operations would silently ADD a positive-convention refund back onto the
		// total instead of netting it out.
		//
		// Deliberately NOT filtering by tbat.deleted_at (matches
		// DeleteBookingAddonBreakdownService's identical query) — ChangePeopleAddonService's
		// removePaxAddons() soft-deletes the ORIGINAL 'paid' transaction row the moment a pax's
		// add-on field is removed, then settleAddonTransactions() separately creates a fresh
		// 'refunded' row to record the actual refund. Filtering out soft-deleted rows here would
		// exclude that original charge from the charge-side sum while still counting its refund,
		// silently under-counting the net total by the charge amount — the exact bug found via
		// booking 65091 (a reassigned add-on later refunded through Change People).
		$newAddonCharge = DB::table('tour_booking_addon_transactions as tbat')
			->where('tbat.tour_booking_id', $id)
			->where('tbat.status', 'paid')
			->where('tbat.operation', 'charge')
			->selectRaw('SUM(ABS(tbat.field_price) + ABS(tbat.option_price)) as total_price, SUM(ABS(tbat.field_fee) + ABS(tbat.option_fee)) as total_fee')
			->first();
		$newAddonRefund = DB::table('tour_booking_addon_transactions as tbat')
			->where('tbat.tour_booking_id', $id)
			->where('tbat.status', 'refunded')
			->where('tbat.operation', 'refund')
			->selectRaw('SUM(ABS(tbat.field_price) + ABS(tbat.option_price)) as total_price, SUM(ABS(tbat.field_fee) + ABS(tbat.option_fee)) as total_fee')
			->first();

		$newChargePrice = (float) ($newAddonCharge->total_price ?? 0);
		$newChargeFee   = (float) ($newAddonCharge->total_fee   ?? 0);
		$newRefundPrice = (float) ($newAddonRefund->total_price ?? 0);
		$newRefundFee   = (float) ($newAddonRefund->total_fee   ?? 0);

		if ($newChargePrice != 0 || $newChargeFee != 0 || $newRefundPrice != 0 || $newRefundFee != 0) {
			$newPrice = $newChargePrice - $newRefundPrice;
			$newFee   = $newChargeFee - $newRefundFee;
			$addonsPaymentPrice += $newPrice;
			$addonsPaymentFee   += $newFee;
			$addonsPaymentTotal += $newPrice + $newFee;
			$addonsFinalPrice   += $newPrice;
			$addonsFinalFee     += $newFee;
			$addonsFinalTotal   += $newPrice + $newFee;

			// addons_refund_* below was legacy-table-only (tour_booking_addons), so any refund
			// on a modern-system addon (tour_booking_addon_transactions, status='refunded') never
			// showed here at all — confirmed on booking 70353: a $40 option charged then $15
			// refunded left addons_gross_total=$43.92 (intentionally un-netted, see comment above)
			// and addons_final_total=$27.45 correctly reflecting the $15 net, but addons_refund_*
			// stayed $0 despite a real Stripe refund having happened. Folding $newRefundPrice/Fee
			// in here keeps gross === final + refund, matching what a "how much was refunded"
			// field should actually answer.
			$addonsRefundPrice += $newRefundPrice;
			$addonsRefundFee   += $newRefundFee;
			$addonsRefundTotal += $newRefundPrice + $newRefundFee;
		}

		// New-style gross is sourced from the main `transactions` ledger (charge_type='addons',
		// status='Completed') rather than summing tbat charge rows directly, because a "swap"
		// (remove item A + add item B in the same refund action — see
		// TourBookingAddonRepository::processAddonPayment() and
		// ChangePeopleAddonService::settleAddonTransactions(), both of which write the new item's
		// charge and the removed item's refund under the same operation_group_id) books item B's
		// charge as its own 'paid' row in tbat, while the real payment ledger only ever records the
		// NET effect as a single Refund transaction — no new money was ever actually collected for
		// item B. Summing tbat's charge rows unconditionally (the old $newChargePrice, still used
		// above for the net/final figures, where it's correct) counted that never-collected amount
		// as gross too, overstating Booking Total by exactly the swapped-in item's value (confirmed
		// on booking 66520: a $3 "Extra Water Bottles" addition bundled inside a $5 removal's 100%
		// refund inflated gross, and therefore Booking Total, by $3).
		$newAddonGrossFromLedger = DB::table('transactions')
			->where('tour_booking_id', $id)
			->where('charge_type', 'addons')
			->where('status', 'Completed')
			->selectRaw('SUM(COALESCE(amount, 0) - COALESCE(fee_amount, 0)) as total_price, SUM(COALESCE(fee_amount, 0)) as total_fee')
			->first();

		$addonsGrossPrice += (float) ($newAddonGrossFromLedger->total_price ?? 0);
		$addonsGrossFee   += (float) ($newAddonGrossFromLedger->total_fee ?? 0);
		$addonsGrossTotal  = $addonsGrossPrice + $addonsGrossFee;

		// Same floating-point rounding as fetchPayLaterAddonsWithPrice() below — summing/subtracting
		// money across multiple rows and sources (legacy tour_booking_addons, tbat, the
		// transactions ledger) can drift by a cent-fraction epsilon even when every individual
		// input was already a clean 2-decimal value — round every returned figure so API/display
		// consumers never see e.g. 0.8699999999999999 instead of 0.87.
		return [
			'addons_payment_price' => roundout($addonsPaymentPrice, 2),
			'addons_payment_fee' => roundout($addonsPaymentFee, 2),
			'addons_payment_total' => roundout($addonsPaymentTotal, 2),
			'addons_gross_price' => roundout($addonsGrossPrice, 2),
			'addons_gross_fee' => roundout($addonsGrossFee, 2),
			'addons_gross_total' => roundout($addonsGrossTotal, 2),
			'addons_refund_price' => roundout($addonsRefundPrice, 2),
			'addons_refund_fee' => roundout($addonsRefundFee, 2),
			'addons_refund_total' => roundout($addonsRefundTotal, 2),
			'addons_final_price' => roundout($addonsFinalPrice, 2),
			'addons_final_fee' => roundout($addonsFinalFee, 2),
			'addons_final_total' => roundout($addonsFinalTotal, 2),
		];
	}

	/**
	 * Amount to add back onto the CURRENT (already-decremented) tour_bookings_master.
	 * service_commission so it reads as the gross, never-decremented figure — matching
	 * booking_total's own untouched-by-split convention, and addons_gross_fee's — for display
	 * purposes only (e.g. "Booking Fees" on tour-booking-info.blade.php).
	 *
	 * service_commission (unlike booking_total) IS decremented by the moved pax's fee in every
	 * ExtendBooking*Service split, so combining it as-is with a gross addons_gross_fee mixes a
	 * current figure with a lifetime one. Reversing that decrement in the STORED column itself
	 * was ruled out — several cancellation/refund services key off booking_total staying gross,
	 * and service_commission has never been audited the same way — so this only reconstructs the
	 * gross figure for THIS display, without touching the stored column or the split write path.
	 *
	 * Scoped via is_reallocated=1, set ONLY on the tour-side transactions ExtendBooking*Service
	 * writes during a split (never by any other refund/delete-people flow), so this returns 0.0
	 * — a no-op — for every booking split has never touched.
	 *
	 * @param int $bookingId
	 * @return float
	 */
	public static function fetchSplitReallocatedTourFee(int $bookingId): float
	{
		return (float) (DB::table('transactions')
			->where('tour_booking_id', $bookingId)
			->whereNull('charge_type')
			->where('status', 'Refund')
			->where('is_reallocated', 1)
			->sum('fee_amount') ?? 0);
	}

	/**
	 * Get the tour booking add-ons information with fees
	 * This method will provide multiple records
	 *
	 * @param int $id
	 * @param mixed $isPaid
	 *
	 * @return array
	 */
	public static function fetchPayLaterAddonsWithPrice($id, $natFeesPercentage, $isPaid = null): array
	{
		$addonsPrice = 0;
		$addonsFee = 0;
		$addonsTotal = 0;
		if ($isPaid == null) {
			$tourBookingFormsRepository = new TourBookingFormsRepository();
			$tourBookingForm = $tourBookingFormsRepository->getBookingForm(null, $id);

			if (isset($tourBookingForm->form_submission) && !empty($tourBookingForm->form_submission)) {
				if ($tourBookingForm->form_submission != null) {
					$decodedForm = json_decode($tourBookingForm->form_submission, true);
					if (!empty($decodedForm)) {
						foreach ($decodedForm as $key => $addon) {
							if ($addon['priceInfo']['enabled'] == 'true') {
								$addonsPrice += $addon['priceInfo']['price'];
								$platformFee = roundout(($addon['priceInfo']['price'] * $natFeesPercentage / 100), 2);
								$addonsFee += $platformFee;
								$addonsTotal += $addon['priceInfo']['price'] + $platformFee;
							}
						}
					}
				}
			}

			// New-style addons stored in tour_booking_addon_fields (price_per_pax / one_time / price_per_unit)
			$addonFieldRows = DB::table('tour_booking_addon_fields as tbaf')
				->join('addon_fields as af', 'af.id', '=', 'tbaf.field_id')
				->leftJoin('addon_field_options as afo', 'afo.id', '=', 'tbaf.option_id')
				->where('tbaf.tour_booking_id', $id)
				->whereNull('tbaf.deleted_at')
				->where(function ($q) {
					$q->where(function ($q2) {
						$q2->where('af.additional_fee', 1)->where('af.price', '>', 0);
					})->orWhere(function ($q3) {
						$q3->where('afo.additional_fee', 1)->where('afo.price', '>', 0);
					});
				})
				->select([
					'af.additional_fee as field_additional_fee',
					'af.price as field_price',
					'afo.additional_fee as option_additional_fee',
					'afo.price as option_price',
					'tbaf.unit_type',
					'tbaf.value',
				])
				->get();

			foreach ($addonFieldRows as $row) {
				$fieldPrice  = ($row->field_additional_fee  && $row->field_price  > 0) ? (float) $row->field_price  : 0.0;
				$optionPrice = ($row->option_additional_fee && $row->option_price > 0) ? (float) $row->option_price : 0.0;

				if ($fieldPrice <= 0 && $optionPrice <= 0) continue;

				$qty        = ($row->unit_type === 'price_per_unit') ? max(1, (int) $row->value) : 1;
				$price      = ($fieldPrice + $optionPrice) * $qty;
				// Round PER UNIT, then multiply by qty — deliberate policy (2026-08-10,
				// reversing the prior "round on the combined total" convention). Must stay
				// consistent with AddonBookingService::calculateFieldAmounts()/
				// createAddonTransactionsFromFields(), or the Stripe charge disagrees with
				// what gets stored on the transaction row.
				$fieldFee   = round($fieldPrice  * $natFeesPercentage / 100, 2) * $qty;
				$optionFee  = round($optionPrice * $natFeesPercentage / 100, 2) * $qty;
				$fee        = $fieldFee + $optionFee;

				$addonsPrice += $price;
				$addonsFee   += $fee;
				$addonsTotal += $price + $fee;
			}
		}

		// Each row's fee is already rounded to cents above, but summing multiple already-rounded
		// floats (or even a single rounded fee × qty, e.g. round(0.2946, 2) * 3) can still drift
		// by a floating-point epsilon (0.29 * 3 = 0.8699999999999999 in PHP, not 0.87) — round the
		// final totals too so callers (including API responses) always get clean cent values.
		return [
			'addons_price' => roundout($addonsPrice, 2),
			'addons_fee' => roundout($addonsFee, 2),
			'addons_total' => roundout($addonsTotal, 2)
		];
	}

	/**
	 * Get the total information with tour package rates
	 * This method will provide multiple records
	 *
	 * @param mixed $id
	 *
	 * @return object
	 */
	public static function totalInformation($id, $isGroupBooking = ''): object
	{
		if ($isGroupBooking == '1') {
			$subQueryForCardFailed = DB::raw('(tbt.rate + tbt.additional_charge + tbt.partner_fees_amount) as makePaymentValueForCardFailed');
		} else {
			$subQueryForCardFailed = DB::raw('(tbt.rate + tbt.permit_fee + tbt.additional_charge + tbt.partner_fees_amount) * tbt.tourists as makePaymentValueForCardFailed');
		}

		$selectFieldsBookingT = [
			'tbt.id',
			'tbt.tour_package_rate_id',
			'tbt.check_in_status',
			'tbt.refund_permitfee_status',
			'tbt.tourists',
			'tbt.infants',
			'tbt.processing_charge_percentage',
			'tbt.additional_tax_percentage',
			'tbt.permit_fee',
			'tbt.partner_fees_type',
			'tbt.partner_fees_percentage',
			'tbt.partner_fees_amount',
			'tbt.additional_charge',
			'tbt.state_tax',
			'tbt.rate',
			'tbt.total',
			'tbt.nat_fees_percentage',
			'tbt.stripe_fees_percentage',
			'tbt.is_group_rate',
			'tbt.is_infant_rate',
			'tbt.redeem_ticket',
			'tpr.processing_charge_percentage as original_processing_charge_percentage',
			'tpr.rate_for',
			'tp.service_commission_percentage',
			'tpr.age_from',
			'tpr.age_to',
			'tpr.permit_fee as tpr_permit_fee',
			'tpr.mapping_key',
			'tpr.cash_rate',
			'tpr.cash_tax',
			'tpr.is_cash_rate',
			'tpr.rate as tpr_rate',
			'tpr.additional_charge as tpr_additional_charge',
			'tpr.additional_tax_percentage as tpr_additional_tax_percentage',
			'tpr.processing_charge_percentage as tpr_processing_charge_percentage',
			$subQueryForCardFailed
		];

		$bookingT = DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->where('tbt.tour_booking_id', $id)
			->where('tbt.deleted_by', 0)
			->orderBy('tpr.orderby', 'asc')
			->get($selectFieldsBookingT);

		if (!$bookingT) {
			throw new Exception("Transaction details not found.");
		}

		// the following isn't working in .blade view file when an element is dynamically created if it doesn't exist
		// laravel is throwing error of undefined offset even after element is created by assigning a new class to it

		/* Get bundle/non-bundle package integration for redeem tickets start */
		$tourBooking = TourBooking::where('id', $id)->select('tour_package_id')->firstOrFail(); // Get package id from master
		$package = TourPackage::where('id', $tourBooking->tour_package_id)->firstOrFail(); // Get package details using package id

		if (!$package) {
			throw new Exception("Tour package not found.");
		}

		$bundlePackageCheckInTypes = [];

		$packages = BundlePackageBookingService::getBundlePackageBookings($package->bundle_package, $id, $tourBooking->tour_package_id);
		/* Get bundle/non-bundle package integration for redeem tickets end */

		$tourBookingAddonRepository = new TourBookingAddonRepository();

		foreach ($bookingT as $booking) {
			$statusInfo = ($booking->check_in_status != '') ? (array)json_decode((string) $booking->check_in_status) : [];
			$redeemTicket = ($booking->redeem_ticket != '') ? (array)json_decode((string) $booking->redeem_ticket) : [];
			$redeemTicket = self::sortBundleRedeemTickets($package, $redeemTicket);

			if ($booking->check_in_status != '') {
				$booking->old_checkin_type = 0;
			}
			$tourists = ($booking->is_infant_rate == 1) ? $booking->infants : $booking->tourists;

			for ($b = 0; $b < $tourists; $b++) {
				if (!isset($statusInfo[$b])) {
					$statusInfo[$b] = new \stdClass;
					$statusInfo[$b]->status = 'N';
				}
				$statusInfo[$b]->ageGroupIndex = $b;
			}

			// For redeem tickets
			$redeemTicketInfo = [];
			$ticketCountArr = [];
			$notRedeemedTickets = 0;
			$redeemedTickets = 0;

			for ($b = 0; $b < $tourists; $b++) {
				// If redeem ticket field is empty
				if (empty($redeemTicket)) {
					for ($a = 0; $a < count($packages); $a++) {
						$redeemTicketInfo[$b][$a] = [
							'id' => $packages[$a]->tour_package_id,
							'name' => $packages[$a]->package_name,
							'checkin_type' => $packages[$a]->checkin_type,
							'status' => ($packages[$a]->checkin_type == 0) ? "N" : 'Active',
							'ageGroupIndex' => $b
						];
						$ticketCountArr[$a][$b]['status'] = ($packages[$a]->checkin_type == 0) ? "N" : 'Active';

						$bundlePackageCheckInTypes[] = [
							'tour_package_id' => $packages[$a]->tour_package_id,
							'package_name' => $packages[$a]->package_name,
							'checkin_type' => $packages[$a]->checkin_type
						];
					}
				}

				if (!empty($ticketCountArr)) {
					for ($a = 0; $a < count($packages); $a++) {
						if ($ticketCountArr[$a][$b]['status'] == "Active") {
							$notRedeemedTickets++;
						}
					}
				}

				for ($a = 0; $a < count($redeemTicket); $a++) {
					// Get checkin_type of package for booking with bundle package
					$checkInType = TourPackage::select('checkin_type')->where('id', $redeemTicket[$a]->id)->firstOrFail();

					$bundlePackageCheckInTypes[] = [
						'tour_package_id' => $redeemTicket[$a]->id,
						'package_name' => $redeemTicket[$a]->name,
						'checkin_type' => isset($redeemTicket[$a]->checkin_type) ? $redeemTicket[$a]->checkin_type : (isset($redeemTicket[$a]->data[$b]) && (($redeemTicket[$a]->data[$b]->status == 'Active') || ($redeemTicket[$a]->data[$b]->status == 'Inactive')) ? 1 : $checkInType->checkin_type)
					];

					$redeemTicketInfo[$b][$a] = [
						'id' => $redeemTicket[$a]->id,
						'name' => $redeemTicket[$a]->name,
						'checkin_type' => isset($redeemTicket[$a]->checkin_type) ? $redeemTicket[$a]->checkin_type : (isset($redeemTicket[$a]->data[$b]) && (($redeemTicket[$a]->data[$b]->status == 'Active') || ($redeemTicket[$a]->data[$b]->status == 'Inactive')) ? 1 : $checkInType->checkin_type),
						'status' => isset($redeemTicket[$a]->data[$b]) ? $redeemTicket[$a]->data[$b]->status : (($checkInType->checkin_type == 0) ? 'N' : 'Active'),
						'ageGroupIndex' => $b
					];

					if ($redeemTicketInfo[$b][$a]['status'] == "Active") {
						$notRedeemedTickets++;
					}

					if ($redeemTicketInfo[$b][$a]['status'] == "Inactive") {
						$redeemedTickets++;
					}
				}
			}

			$permitFeesRefunded = 0;
			$booking->noPermitFees = 0;
			if ($booking->refund_permitfee_status != '') {
				$refundPermitFeeInfo = json_decode($booking->refund_permitfee_status);
				if (!empty($refundPermitFeeInfo)) {
					foreach ($refundPermitFeeInfo as $info) {
						if (!isset($info->is_pax_added)) {
							if ($info->status == 'Y') {
								if (isset($info->amount) && $info->amount > 0) {
									$permitFeesRefunded += $info->amount;
								}
							}
						}
					}
				}

				if ($booking->tpr_permit_fee > 0 && $booking->permit_fee == 0) {
					$booking->noPermitFees = 1;
				}
			} else {
				if ($booking->tpr_permit_fee > 0 && $booking->permit_fee == 0) {
					$booking->noPermitFees = 1;
				}
			}

			$booking->permitFeesRefunded = $permitFeesRefunded;
			$booking->check_in_status = $statusInfo;
			$booking->redeem_ticket = $redeemTicketInfo;
			$booking->bundle_package_checkin_types = ($bundlePackageCheckInTypes != null) ? array_map("unserialize", array_unique(array_map("serialize", $bundlePackageCheckInTypes))) : $bundlePackageCheckInTypes;
			$booking->permit_fee_history = [];
			if (isset($booking->refund_permitfee_status)) {
				$permitStatus = json_decode($booking->refund_permitfee_status);
				$booking->permit_fee_history = (array) $permitStatus;
			}

			$booking->redeemed_tickets = 0;
			$booking->not_redeemed_tickets = 0;
			$booking->redeemed_tickets += $redeemedTickets;
			$booking->not_redeemed_tickets += $notRedeemedTickets;
			$booking->is_rate_group_has_pax_addons = $tourBookingAddonRepository->isBookingPaxAddonsExists($id, $booking->id);

			// Get pax indexes that have addons for this transact
			$booking->pax_addons_indexes = [];
			if ($booking->is_rate_group_has_pax_addons) {
				$booking->pax_addons_indexes = $tourBookingAddonRepository->getPaxAddonsIndexes($id, $booking->id, $tourists);
			}
		}

		return $bookingT;
	}

	/**
	 * Get the staff users based on role of user
	 *
	 * @param mixed $bookingM
	 *
	 * @return object
	 */
	public static function staffUsers($bookingM): object
	{
		if ($bookingM->created_by_user_type == 'Tour Operator') {
			$staffUsers = DB::table('tour_operator_staff')
				->where('tour_operator_id', $bookingM->tour_operator_id)
				->where(function ($qry) use ($bookingM) {
					$qry->where(function ($qry2) {
						$qry2->where('status', 'Active')
							->where('deleted_by', 0);
					})
						->orWhere('id', $bookingM->created_by);
				})
				->get(['id', 'first_name', 'last_name']);
		} else {
			$staffUsers = DB::table('tour_operator_staff')
				->where('tour_operator_id', $bookingM->tour_operator_id)
				->where('status', 'Active')
				->where('deleted_by', 0)
				->get(['id', 'first_name', 'last_name']);
		}

		return $staffUsers;
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function bookingUser($id): array
	{
		$bookingUser = TourBooking::where('id', $id)->withTrashed()->first();
		$user = [];
		switch ($bookingUser->created_by_user_type) {
			case "Admin":
				$admin = Admin::where('id', $bookingUser->created_by)->withTrashed()->first();
				$user['name'] = $admin ? $admin->name : '';
				$user['type'] = "Admin";
				break;
			case "Tour Operator":
				$operation = Staff::where('id', $bookingUser->created_by)->withTrashed()->first();
				$user['name'] = $operation ? "{$operation->first_name} {$operation->last_name}" : '';
				$user['type'] = "Tour Operator";
				break;
			case "Affiliate":
				$operation = Customer::where('id', $bookingUser->created_by)->withTrashed()->first();
				$user['name'] = $operation ? $operation->name : '';
				$user['type'] = "Affiliate";
				break;
			default:
				$user['name'] = 'Online';
				$user['type'] = "";
				break;
		}

		return $user;
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $id
	 *
	 * @return mixed
	 */
	public static function createdUserInfo($needUser = false, $booking = null, int $userId = null, string $userType = null): mixed
	{
		$resolvedUserType = $userType ?? userType();
		$resolvedUserId   = $userId ?? Auth::id();

		if ($resolvedUserType == "Admin") {
			$user = Admin::find($resolvedUserId);
			$created_by_with_name = "{$user->name}: (Admin)";
		} elseif ($resolvedUserType == "Tour Operator") {
			$user = Staff::find($resolvedUserId);
			$created_by_with_name = "{$user->name}: (Tour Operator)";
		} elseif ($resolvedUserType == "Affiliate") {
			$user = Customer::find($resolvedUserId);
			$created_by_with_name = "{$user->name}: (Affiliate)";
		} else {
			// if you do not pass the var $booking its default value is null there for will not
			// be an object and will not have the customer_id property
			if ($booking && isset($booking->customer_id)) {
				$user = Customer::find($booking->customer_id);
				$created_by_with_name = "{$user->name}: (Customer)";
			} else {
				$user = null;
				$created_by_with_name = "AppUser: (Customer)";
			}
		}

		if ($needUser) {
			if ($booking) {
				return [
					'user' => $user,
					'created_by_with_name' => $created_by_with_name,
				];
			}
			return $user;
		}

		return $created_by_with_name;
	}

	/**
	 * Resolve a Customer's id by the booking's own stored email — used to attribute
	 * payment-link records (operation_by/created_by) to the real customer when a payment is
	 * completed via a public Payment Link, where no admin/staff session exists so Auth::id()
	 * has nothing to resolve.
	 *
	 * @param mixed $bookingId
	 * @return int|null
	 */
	public static function customerIdByBookingEmail($bookingId): ?int
	{
		$email = TourBooking::where('id', $bookingId)->value('email');
		if (!$email) {
			return null;
		}

		return Customer::where('email', $email)->value('id');
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $id
	 *
	 * @return mixed
	 */
	public static function refundRouteWithError($bookingId, $modalUrl, $message): mixed
	{
		// Callers that explicitly ask for JSON (the "Delete Booking" modal's AJAX submit) get
		// the error back as JSON so it can be shown inline via SweetAlert, instead of a redirect
		// that reloads the whole page with the error flashed into the `errors` session bag.
		if (request()->expectsJson()) {
			return sendJsonErrMsg($message);
		}

		$path = url('/');
		$currentURL = Route::currentRouteName();

		if ($currentURL == "affiliate.booking.refund_and_booking_edit") {
			return redirect()->to($path . '/affiliate/booking/' . $bookingId . $modalUrl)
				->withErrors(['errors' => $message]);
		} elseif ($currentURL == 'admin.tour-operator.booking.refund_and_booking_edit') {
			return redirect()->to($path . '/admin/tour-operator/booking/' . $bookingId . $modalUrl)
				->withErrors(['errors' => $message]);
		} else {
			return redirect()->to($path . '/tour-operator/booking/' . $bookingId . $modalUrl)
				->withErrors(['errors' => $message]);
		}
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $id
	 *
	 * @return mixed
	 */
	public static function refundRouteWithSuccess($bookingId, $modalUrl, $message): mixed
	{
		$path = url('/');
		$currentURL = Route::currentRouteName();

		if ($currentURL == "affiliate.booking.refund_and_booking_edit") {
			$redirectUrl = $path . '/affiliate/booking/' . $bookingId . $modalUrl;
		} elseif ($currentURL == 'admin.tour-operator.booking.refund_and_booking_edit') {
			$redirectUrl = $path . '/admin/tour-operator/booking/' . $bookingId . $modalUrl;
		} else {
			$redirectUrl = $path . '/tour-operator/booking/' . $bookingId . $modalUrl;
		}

		// Same JSON branch as refundRouteWithError() above, for the AJAX "Delete Booking" submit.
		// The success message is still flashed to the session so the reload triggered by the
		// `redirect` URL below shows the normal green success banner, same as the non-JSON path.
		if (request()->expectsJson()) {
			session()->flash('message', $message);
			return response()->json(['message' => $message, 'redirect' => $redirectUrl]);
		}

		return redirect()->to($redirectUrl)->with('message', $message);
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $id
	 * @return mixed
	 */
	public static function backgroundColourClass($booking, $fromAgenda = false): mixed
	{
		$uiClass = '';
		if (isset($booking->tripadvisor_id) && $booking->tripadvisor_id != null) {
			$uiClass = 'tripadvisor-booking bg-[#fef5e9] hover:bg-[#ffecd4]';
		} elseif (isset($booking->get_your_guide_booking_id) && $booking->get_your_guide_booking_id != null) {
			$uiClass = 'get-your-guide-booking bg-[#fef5e9] hover:bg-[#ffecd4]';
		} elseif ($booking->ota_booking_id != null) {
			$uiClass = 'ota-booking bg-[#fef5e9] hover:bg-[#ffecd4]';
		} elseif (isset($booking->relational_booking) && $booking->relational_booking == 1) {
			$uiClass = 'relational-booking bg-[#ffcfcf] hover:bg-[#ffc4c4]';
		} elseif (isset($booking->cartBookingsCount) && $booking->cartBookingsCount > 1) {
			$uiClass = 'combo-booking bg-[#f5e8fd] hover:bg-[#ebd8f8]';
		} elseif (isset($booking->is_reserved_for_affiliate) && $booking->is_reserved_for_affiliate == 1) {
			$uiClass = 'affiliate-soft bg-[#E4FFE6] hover:bg-[#E4FFF6]';
		} elseif (isset($booking->is_affiliate) && $booking->is_affiliate == 'Yes') {
			$uiClass = 'affiliate-catch bg-[#ff03] hover:bg-[#ff05]';
		} elseif (isset($booking->reference_booking) && (str_starts_with($booking->reference_booking, 'B-') || (in_array(tenant()->id, ['cdc', 'avct']) && str_starts_with($booking->reference_booking, '#')))) {
			$uiClass = 'peekpro-booking bg-[#d8f2ff] hover:bg-[#d5f2fe]';
		} else {
			$uiClass = 'normal-booking bg-[#f4feff] hover:bg-[#e4eeff]';
		}

		return $uiClass;
	}

	/**
	 * Get the booking user based on role of user
	 *
	 * @param mixed $bookingM
	 *
	 * @return array
	 */
	public static function liabilityWaiverSigning($bookingM): array
	{
		$waiverData = json_decode($bookingM->liability_waiver_signing, true);
		$modifiedWaiverData = array();

		foreach ($waiverData as $key => $waiver) {
			if ($waiver[1] == "-1" || ($waiver[0] == '' && $waiver[1] == '')) {
				$modifiedWaiverData[$key]['liability_waiver_signing'] = '';
				$modifiedWaiverData[$key]['name'] = '';
				$modifiedWaiverData[$key]['age'] = '';
			} else {
				$modifiedWaiverData[$key]['liability_waiver_signing'] = isset($waiver[2]) ? $waiver[2] : '';
				$modifiedWaiverData[$key]['name'] = (isset($waiver[0])) ? (string)$waiver[0] : '';
				$modifiedWaiverData[$key]['age'] = (isset($waiver[1]) && $waiver[1] > 0) ? (string)$waiver[1] : '';
			}
		}

		return $modifiedWaiverData;
	}

	/**
	 * Get last 4 digit of the card number
	 *
	 * @param mixed $bookingId
	 * @param mixed $transactionNumber
	 *
	 * @return array
	 */
	public static function cardLastFourDigit($bookingId, $transactionNumber): array
	{
		// whereNull OR charge_type='addons' — a priced add-on charged on its own PaymentIntent
		// (no accompanying tour-side charge) is a valid, admin-selectable transaction too (see
		// the Delete Booking modal's "Tour Transaction" picker), so its card must resolve here
		// the same as a tour-side transaction rather than returning null.
		$transactionDetails = Transaction::where('tour_booking_id', $bookingId)->where('transaction_number', $transactionNumber)->where('status', 'Completed')->where(function ($query) {
			$query->whereNull('charge_type')->orWhere('charge_type', 'addons');
		})->first(['id']);
		$histDetails = TourBookingHistory::where('transaction_id', $transactionDetails->id)->where('tour_booking_id', $bookingId)->where('operation', 'Payment')->first(['card_last_4_digit']);
		$cardDetails['card_last_4_digit'] = $histDetails->card_last_4_digit ?? 0;
		return $cardDetails;
	}

	/**
	 * Calculate ticket cost of the booking
	 *
	 * @param mixed $bookingT
	 *
	 * @return float
	 */
	public static function calculateTicketCost($bookingT): float
	{
		$totalTicketCost = 0;
		$touristNum = 0;
		foreach ($bookingT as $bt) {
			if ($bt->is_group_rate == '1') {
				$rate = $bt->rate;
				$totalTicketCost = $rate + $bt->additional_charge;
			} else {
				$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee;
				$per = $rate * ($bt->additional_tax_percentage + $bt->processing_charge_percentage) / 100;
				$subtotal = $rate + $per;
			}
			if ($bt->is_group_rate != '1') {
				for ($a = 0; $a < $bt->tourists; $a++, $touristNum++) {
					$refund_permitfee_status = ($bt->refund_permitfee_status != '') ? json_decode($bt->refund_permitfee_status) : '';
					if ($refund_permitfee_status == '') {
						$totalamtafterpermitfeesrefunded = $bt->total / $bt->tourists;
					} else {
						if (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y" && !isset($refund_permitfee_status[$a]->is_pax_added)) {
							if ($bt->tourists > 1) {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee - $refund_permitfee_status[$a]->processing_fees;
							} else {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee;
							}
						} else if ($bt->permit_fee != $bt->tpr_permit_fee && $bt->permit_fee > $bt->tpr_permit_fee && isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y" && isset($refund_permitfee_status[$a]->is_pax_added)) {
							if (isset($refund_permitfee_status[$a]->processing_fees)) {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee - $refund_permitfee_status[$a]->processing_fees;
							} else {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee;
							}
						} else if ($bt->permit_fee == $bt->tpr_permit_fee && isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y" && isset($refund_permitfee_status[$a]->is_pax_added)) {
							if (isset($refund_permitfee_status[$a]->processing_fees)) {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee - $refund_permitfee_status[$a]->processing_fees;
							} else {
								$totalamtafterpermitfeesrefunded = $subtotal - $bt->permit_fee;
							}
						} else {
							$totalamtafterpermitfeesrefunded = $subtotal;
						}
					}

					$totalTicketCost += $totalamtafterpermitfeesrefunded;
				}
			}
		}
		return $totalTicketCost;
	}

	/**
	 * Get all bookings ids of the specific cart
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function cartBookingIds($id): array
	{
		$tourBooking = DB::table('tour_bookings_master AS tbm')
			->where('tbm.id', $id)
			->first('cart_id');

		$cartBookingIds = array();

		if ($tourBooking->cart_id && $tourBooking->cart_id != '') {
			$cartBookings = DB::table('tour_bookings_master AS tbm')
				->whereNot('tbm.id', $id)
				->where('tbm.cart_id', $tourBooking->cart_id)
				->get('id');

			foreach ($cartBookings as $cartBooking) {
				$cartBookingIds[] = $cartBooking->id;
			}
		}

		return $cartBookingIds;
	}

	/**
	 * Get all bookings ids of the specific cart
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function cartBookingsInfo($id): array
	{
		$tourBooking = DB::table('tour_bookings_master AS tbm')
			->where('tbm.id', $id)
			->first('cart_id');

		$comboBookings = array();

		if ($tourBooking->cart_id && $tourBooking->cart_id != '') {
			$cartBookings = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->whereNot('tbm.id', $id)
				->where('tbm.cart_id', $tourBooking->cart_id)
				->get(['tbm.id', 'tbm.tourists', 'tp.name', 'tst.time_from', 'tsd.date']);

			foreach ($cartBookings as $cartBooking) {
				$comboBookings[] = [
					'booking_id' => $cartBooking->id,
					'tourists' => $cartBooking->tourists,
					'time_from' => $cartBooking->time_from,
					'date' => $cartBooking->date,
					'tour_package' => $cartBooking->name,
				];
			}
		}

		return $comboBookings;
	}

	/**
	 * Get all bookings ids and package name of the cart bookings
	 *
	 * @param mixed $id
	 *
	 * @return array
	 */
	public static function comboBookingsData($id): array
	{
		$tourBooking = DB::table('tour_bookings_master AS tbm')
			->where('tbm.id', $id)
			->first('cart_id');

		$comboBookings = array();

		if ($tourBooking->cart_id) {
			$comboBookingsQuery = DB::table('tour_bookings_master AS tbm')
				->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
				->leftJoin('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->leftJoin('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tbm.cart_id', $tourBooking->cart_id)
				->orderBy('tbm.id', 'asc')
				->get([
					'tbm.id',
					'tp.name',
					'tp.package_has_slots',
					DB::raw('tsd.date AS date'),
					DB::raw('tst.time_from AS time_from'),
					'tst.time_to',
					'tbm.tourists',
					'tbm.infants',
				]);

			$firstId = $comboBookingsQuery->first()->id ?? null;

			foreach ($comboBookingsQuery as $cartBooking) {
				$date     = $cartBooking->date     ?? null;
				$timeFrom = $cartBooking->time_from ?? null;
				$timeTo   = $cartBooking->time_to   ?? null;

				if ($cartBooking->package_has_slots && $date && $timeFrom && $timeTo) {
					$tourDateTime = date('l, F jS Y @ g:i a - ', strtotime("$date $timeFrom"))
						. date('g:i a', strtotime("$date $timeTo"));
				} elseif ($date) {
					$tourDateTime = date('l, F jS Y', strtotime($date));
				} else {
					$tourDateTime = '';
				}

				$comboBookings[] = [
					'id'             => $cartBooking->id,
					'packageName'    => $cartBooking->name,
					'tour_date_time' => $tourDateTime,
					'tourists'       => (int) $cartBooking->tourists + (int) $cartBooking->infants,
					'is_parent'      => $cartBooking->id === $firstId,
					'is_current'     => $cartBooking->id === $id,
				];
			}
		}

		if (count($comboBookings) > Constants::STATUS_ONE) {
			return $comboBookings;
		}

		return $comboBookings;
	}

	/**
	 * Update booking data in 3d secure payments in make payment feature
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	public static function completeSecureCardPayment($data): string
	{
		$userType = userType();
		$paymentIntentClientSecret = $data['payment_intent_client_secret'];
		$paymentIntentId = (isset($data['payment_intent'])) ? $data['payment_intent'] : '';
		$paymentAcknowledgement = '';

		$bookingdetails = TourBooking::where('stripe_paymentintent_id', $paymentIntentClientSecret)->where('status', 'Booked')->first();

		if ($bookingdetails) {
			$transactionsexist = Transaction::where('tour_booking_id', $bookingdetails->id)->where('status', 'Completed')->whereNull('charge_type')->first();
			$paymentIntentDetails = PaymentIntent::retrieve(['id' => $paymentIntentId]);
			$metaDataInfo = (isset($paymentIntentDetails->metadata['by']) && !empty($paymentIntentDetails->metadata['by'])) ? $paymentIntentDetails->metadata['by'] : '';
			if ($metaDataInfo != '' && $metaDataInfo == 'Switch Package - Web') {
				SwitchTourPackageCardService::completeSecureCardPayment($data);
			}
			if ((!$transactionsexist) || (!empty($transactionsexist) && $transactionsexist->payment_type == '1') || ($metaDataInfo != '' && $metaDataInfo == 'Change Payment Method - Web') || ($metaDataInfo != '' && $metaDataInfo == 'Change People - Web')) {
				if (!empty($paymentIntentDetails)) {
					if (($metaDataInfo != '' && $metaDataInfo == 'Change Payment Method - Web')) {
						if ($paymentIntentDetails->status == 'succeeded') {
							$info = DB::table('tour_slot_times AS tst')
								->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
								->join('tour_bookings_master as tbm', 'tbm.tour_package_id', '=', 'tp.id')
								->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
								->join('states AS st', 'st.id', '=', 'ct.state_id')
								->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
								->where('tbm.id', $bookingdetails->id)
								->select([
									'tbm.id',
									'tst.time_from',
									'tsd.date',
									'tst.time_slot_seats as seats',
									'tp.tour_operator_id',
									'tsd.tour_package_id',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'tp.name AS package_name',
									'tp.important_notes',
									'tp.things_to_bring',
									'ct.state_id',
									'st.permit_fee',
									't_o.name AS tour_operator',
									't_o.email AS tour_op_email',
									't_o.phone_number AS tour_op_phone',
									't_o.website AS tour_op_website',
									't_o.logo AS tour_op_logo',
									't_o.cancellation_policy',
									'tp.service_commission_percentage',
									'cus.affiliate_processing_percentage',
									'cus.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor',
									't_o.card_reader_prefix',
									'cus.name as customername',
									'cus.email as customeremail',
									'cus.phone_number as customerphone',
									'tp.email as packageEmail',
									'tp.from_name as packageFromName',
									'tp.from_email as packageFromEmail',
									'tp.api_key as packageApiKey',
									'tbm.tourists',
									DB::raw("(SELECT SUM(tbm.tourists)
										FROM tour_bookings_master AS tbm
										WHERE tbm.tour_slot_id = tst.id
											AND tbm.status != 'Cancelled'
										GROUP BY tbm.tour_slot_id) AS booked_seats")
								])
								->first();

							$tourBooking = DB::table('tour_bookings_master AS tbm')
								->join('customers as c', 'c.id', '=', 'tbm.customer_id')
								->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
								->join('tour_packages as pkg', 'pkg.id', '=', 'tbm.tour_package_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'pkg.tour_operator_id')
								->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
								->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
								->where('tbm.id', '=', $bookingdetails->id)
								->select(
									'tbm.id',
									'tbm.id as booking_id',
									'tbm.total',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'c.name as Name',
									'sd.date as Date',
									'st.time_from as StartTime',
									'st.time_to as EndTime',
									'c.phone_number as ContactNumber',
									'c.email as Email',
									'tbm.tourists',
									'c.id as customer_id',
									't_o.id as tour_operator_id',
									't_o.name as tour_operator_name',
									'tbm.service_commission',
									't_o.stripe_destination_id',
									'tp.name as package_name',
									'tp.service_commission_percentage',
									'c.affiliate_processing_percentage',
									'c.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor'
								)
								->get();

							$totalamount = (isset($paymentIntentDetails->charges->data[0]->amount)) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
							$feesamount = PlatformFeeMetadata::feeFor($paymentIntentDetails, (isset($paymentIntentDetails->charges->data[0]->application_fee_amount)) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);
							$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
							$cardtype = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

							$history = TourBookingHistory::where('tour_booking_id', $bookingdetails->id)->where('operation', 'Payment')->first();
							$transaction = Transaction::find($history->transaction_id);
							$originalPaymentType = $history->payment_type;

							if ($userType == "Admin") {
								$user = Admin::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Admin" . ')';
							} elseif ($userType == "Tour Operator") {
								$user = Staff::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
							} elseif ($userType == "Affiliate") {
								$user = Customer::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
							}

							$jsonData = json_decode($history->details, true);
							$jsonData['amount'] = $totalamount;
							$jsonData['payment_type'] = 'Card';
							$jsonData['card_number'] = $crd ?? null;
							$jsonData['created_by_with_name'] = $created_by_with_name;
							$jsonData['payment_method'] = 'Full Payment';
							$history->payment_type = 'Card';
							$history->details = json_encode($jsonData, JSON_NUMERIC_CHECK);
							$history->save();
							DB::commit();

							$historyId = $history->id;
							$transaction->amount = $totalamount;
							$transaction->fee_amount = $feesamount;
							$transaction->transaction_number = $_GET['payment_intent'] ?? null;
							$transaction->application_fee_id = $paymentIntentDetails->charges->data[0]->application_fee ?? null;
							$transaction->payment_type = 0;
							$transaction->via = 'Card';
							$transaction->save();
							DB::commit();

							$transactionId = $transaction->id;
							$amountUpdate = TourBooking::findOrFail($bookingdetails->id);
							$amountUpdate->total = $totalamount;
							$amountUpdate->booking_total = $totalamount;
							$amountUpdate->service_commission = $feesamount;
							$amountUpdate->save();
							DB::commit();

							$tourBookingHistory = new TourBookingHistory();
							$tourBookingHistory->tour_booking_id = $history->tour_booking_id;
							$tourBookingHistory->operation = 'Payment Type Update';
							$tourBookingHistory->details = json_encode(['history_id' => $history->id, 'from' => $originalPaymentType, 'to' => 'Card', 'created_by_with_name' => $created_by_with_name], JSON_NUMERIC_CHECK);
							$tourBookingHistory->operation_at = Carbon::now();
							$tourBookingHistory->operation_by = Auth::id();
							$tourBookingHistory->operation_by_user_type = $userType;
							$tourBookingHistory->save();
							DB::commit();

							$isSplitOrFull = 'Full Payment';
							$tourBooking[0]->tour_operator_id = $info->tour_operator_id;
							CardBookingService::run((array)$tourBooking[0], $transactionId, $historyId, $isSplitOrFull, [], $paymentIntentDetails);

							DB::commit();
							$paymentAcknowledgement = 'success';
						} else {
							$paymentAcknowledgement = 'failed';
						}
					} else if ($metaDataInfo != '' && $metaDataInfo == 'Change People - Web') {
						if ($paymentIntentDetails->status == 'succeeded') {
							$info = DB::table('tour_slot_times AS tst')
								->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
								->join('tour_bookings_master as tbm', 'tbm.tour_package_id', '=', 'tp.id')
								->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
								->join('states AS st', 'st.id', '=', 'ct.state_id')
								->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
								->where('tbm.id', $bookingdetails->id)
								->select([
									'tbm.id',
									'tst.time_from',
									'tsd.date',
									'tst.time_slot_seats as seats',
									'tp.tour_operator_id',
									'tsd.tour_package_id',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'tp.name AS package_name',
									'tp.important_notes',
									'tp.things_to_bring',
									'ct.state_id',
									'st.permit_fee',
									't_o.name AS tour_operator',
									't_o.email AS tour_op_email',
									't_o.phone_number AS tour_op_phone',
									't_o.website AS tour_op_website',
									't_o.logo AS tour_op_logo',
									't_o.cancellation_policy',
									'tp.service_commission_percentage',
									'cus.affiliate_processing_percentage',
									'cus.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor',
									't_o.card_reader_prefix',
									'cus.name as customername',
									'cus.email as customeremail',
									'cus.phone_number as customerphone',
									'tp.email as packageEmail',
									'tp.from_name as packageFromName',
									'tp.from_email as packageFromEmail',
									'tp.api_key as packageApiKey',
									'tbm.tourists',
									DB::raw("(SELECT SUM(tbm.tourists)
												FROM tour_bookings_master AS tbm
												WHERE tbm.tour_slot_id = tst.id
													AND tbm.status != 'Cancelled'
												GROUP BY tbm.tour_slot_id) AS booked_seats")
								])
								->first();
							$totalamount = (isset($paymentIntentDetails->charges->data[0]->amount)) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
							$feesamount = PlatformFeeMetadata::feeFor($paymentIntentDetails, (isset($paymentIntentDetails->charges->data[0]->application_fee_amount)) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);
							$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
							$cardtype = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

							$transactionId = DB::table('transactions')
								->insertGetId([
									'tour_booking_id' => $bookingdetails->id,
									'amount' => $totalamount,
									'fee_amount' => $feesamount,
									'transaction_number' => !empty($paymentIntentId) ? $paymentIntentId : 0,
									'application_fee_id' => !empty($paymentIntentDetails->charges->data[0]->application_fee) ? $paymentIntentDetails->charges->data[0]->application_fee : null,
									'via' => 'Card',
									'response_init' => '',
									'response_end' => '',
									'response_end_datetime' => null,
									'payment_type' => 0,
									'status' => 'Completed',
									'visitor' => request()->getClientIp(),
									'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
									'created_by' => Auth::id(),
									'created_by_user_type' => $userType,
									'created_at' => Carbon::now(),
								]);
							DB::commit();

							if ($userType == "Admin") {
								$user = Admin::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Admin" . ')';
							} elseif ($userType == "Tour Operator") {
								$user = Staff::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
							} elseif ($userType == "Affiliate") {
								$user = Customer::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
							}

							$tourBookingDetails = TourBooking::find($bookingdetails->id);

							$historyId = DB::table('tour_booking_history')
								->insertGetId([
									'tour_booking_id' => $bookingdetails->id,
									'tourists' => $tourBookingDetails->tourists,
									'operation' => 'Payment',
									'details' => json_encode(['amount' => $totalamount, 'payment_type' => 'Card', 'card_number' => $crd, 'card_type' => $cardtype, 'payment_method' => 'Full Payment', 'created_by_with_name' => $created_by_with_name], JSON_NUMERIC_CHECK),
									'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
									'operation_by' => Auth::id(),
									'operation_by_user_type' => $userType,
									'updated_at' => Carbon::now(),
									'created_at' => Carbon::now(),
								]);
							DB::commit();

							$addedPeopleTexts = (isset($paymentIntentDetails->metadata['added_people_texts']) && !empty($paymentIntentDetails->metadata['added_people_texts'])) ? explode(',', (string)$paymentIntentDetails->metadata['added_people_texts']) : '';
							$deletedPeopleTexts = (isset($paymentIntentDetails->metadata['deleted_people_texts']) && !empty($paymentIntentDetails->metadata['deleted_people_texts'])) ? explode(',', (string)$paymentIntentDetails->metadata['deleted_people_texts']) : '';
							$addedTourists = (isset($paymentIntentDetails->metadata['added_tourists']) && !empty($paymentIntentDetails->metadata['added_tourists'])) ? $paymentIntentDetails->metadata['added_tourists'] : '';
							$deletedTourists = (isset($paymentIntentDetails->metadata['deleted_tourists']) && !empty($paymentIntentDetails->metadata['deleted_tourists'])) ? $paymentIntentDetails->metadata['deleted_tourists'] : '';
							$discountPercentage = (isset($paymentIntentDetails->metadata['discount_percentage']) && !empty($paymentIntentDetails->metadata['discount_percentage'])) ? $paymentIntentDetails->metadata['discount_percentage'] : 0;
							$discountValue = (isset($paymentIntentDetails->metadata['discount_value']) && !empty($paymentIntentDetails->metadata['discount_value'])) ? $paymentIntentDetails->metadata['discount_value'] : 0;
							$refundTotal = (isset($paymentIntentDetails->metadata['refund_total']) && !empty($paymentIntentDetails->metadata['refund_total'])) ? $paymentIntentDetails->metadata['refund_total'] : 0;
							$paymentTotal = (isset($paymentIntentDetails->metadata['payment_total']) && !empty($paymentIntentDetails->metadata['payment_total'])) ? $paymentIntentDetails->metadata['payment_total'] : 0;

							$operationTouristsChanged = [
								'tour_booking_id' => $bookingdetails->id,
								'tourists' => $tourBookingDetails->tourists,
								'operation' => 'Update',
								'details' => json_encode(['sub_operation' => 'Tourists Changed', 'discount_percentage' => $discountPercentage, 'discount2_value' => $discountValue, 'added' => $addedPeopleTexts, 'deleted' => $deletedPeopleTexts, 'addedTourists' => $addedTourists, 'deletedTourits' => $deletedTourists, 'paymenttotal' => $paymentTotal, 'refundtotal' => $refundTotal, 'created_by_with_name' => $created_by_with_name], JSON_NUMERIC_CHECK),
								'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
								'operation_by' => Auth::id(),
								'operation_by_user_type' => $userType,
								'updated_at' => Carbon::now(),
								'created_at' => Carbon::now(),
							];
							DB::table('tour_booking_history')
								->insert($operationTouristsChanged);
							DB::commit();

							$tourBooking = DB::table('tour_bookings_master AS tbm')
								->join('customers as c', 'c.id', '=', 'tbm.customer_id')
								->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
								->join('tour_packages as pkg', 'pkg.id', '=', 'tbm.tour_package_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'pkg.tour_operator_id')
								->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
								->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
								->where('tbm.id', '=', $bookingdetails->id)
								->select(
									'tbm.id',
									'tbm.id as booking_id',
									'tbm.total',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'c.name as Name',
									'sd.date as Date',
									'st.time_from as StartTime',
									'st.time_to as EndTime',
									'c.phone_number as ContactNumber',
									'c.email as Email',
									'tbm.tourists',
									'c.id as customer_id',
									't_o.id as tour_operator_id',
									't_o.name as tour_operator_name',
									'tbm.service_commission',
									't_o.stripe_destination_id',
									'tp.name as package_name',
									'tp.service_commission_percentage',
									'c.affiliate_processing_percentage',
									'c.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor'
								)
								->get();

							$tourBooking[0]->tour_operator_id = $info->tour_operator_id;
							$tourBooking[0]->is_pax_added = true;

							CardBookingService::run((array)$tourBooking[0], $transactionId, $historyId, 'Full Payment', [], $paymentIntentDetails);

							$tourBookingDetails->stripe_paymentintent_id = '';
							$tourBookingDetails->save();
							DB::commit();

							$paymentAcknowledgement = 'success';
						} else {
							$paymentAcknowledgement = 'failed';
						}
					} else {
						if ($paymentIntentDetails->status == 'succeeded') {
							$info = DB::table('tour_slot_times AS tst')
								->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
								->join('tour_bookings_master as tbm', 'tbm.tour_package_id', '=', 'tp.id')
								->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
								->join('states AS st', 'st.id', '=', 'ct.state_id')
								->join('customers as cus', 'cus.id', '=', 'tbm.customer_id')
								->where('tbm.id', $bookingdetails->id)
								->select([
									'tbm.id',
									'tst.time_from',
									'tsd.date',
									'tst.time_slot_seats as seats',
									'tp.tour_operator_id',
									'tsd.tour_package_id',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'tp.name AS package_name',
									'tp.important_notes',
									'tp.things_to_bring',
									'ct.state_id',
									'st.permit_fee',
									't_o.name AS tour_operator',
									't_o.email AS tour_op_email',
									't_o.phone_number AS tour_op_phone',
									't_o.website AS tour_op_website',
									't_o.logo AS tour_op_logo',
									't_o.cancellation_policy',
									'tp.service_commission_percentage',
									'cus.affiliate_processing_percentage',
									'cus.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor',
									't_o.card_reader_prefix',
									'cus.name as customername',
									'cus.email as customeremail',
									'cus.phone_number as customerphone',
									'tp.email as packageEmail',
									'tp.from_name as packageFromName',
									'tp.from_email as packageFromEmail',
									'tp.api_key as packageApiKey',
									'tp.attach_waiver_pdf',
									'tp.waiver_pdf',
									'tbm.tourists',
									DB::raw("(SELECT SUM(tbm.tourists)
												FROM tour_bookings_master AS tbm
												WHERE tbm.tour_slot_id = tst.id
													AND tbm.status != 'Cancelled'
												GROUP BY tbm.tour_slot_id) AS booked_seats")
								])
								->first();

							$tourBooking = DB::table('tour_bookings_master AS tbm')
								->join('customers as c', 'c.id', '=', 'tbm.customer_id')
								->join('tour_bookings_transact as tbt', 'tbt.tour_booking_id', '=', 'tbm.id')
								->join('tour_packages as pkg', 'pkg.id', '=', 'tbm.tour_package_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'pkg.tour_operator_id')
								->join('tour_slot_times as st', 'st.id', '=', 'tbm.tour_slot_id')
								->join('tour_slot_dates as sd', 'sd.id', '=', 'st.tour_slot_id')
								->where('tbm.id', '=', $bookingdetails->id)
								->select(
									'tbm.id',
									'tbm.id as booking_id',
									'tbm.total',
									'tbm.discount2_value',
									'tbm.discount2_percentage',
									'c.name as Name',
									'sd.date as Date',
									'st.time_from as StartTime',
									'st.time_to as EndTime',
									'c.phone_number as ContactNumber',
									'c.email as Email',
									'tbm.tourists',
									'c.id as customer_id',
									't_o.id as tour_operator_id',
									't_o.name as tour_operator_name',
									'tbm.service_commission',
									't_o.stripe_destination_id',
									'tp.name as package_name',
									'tp.service_commission_percentage',
									'c.affiliate_processing_percentage',
									'c.stripe_affiliate_percentage',
									'tp.stripe_commission_percentage',
									't_o.statement_descriptor'
								)
								->get();

							$splitpayment = isset($paymentIntentDetails->charges->data[0]->metadata->split_payment) ? $paymentIntentDetails->charges->data[0]->metadata->split_payment : 0;
							$splitamount = isset($paymentIntentDetails->charges->data[0]->amount) ? $paymentIntentDetails->charges->data[0]->amount / 100 : 0;
							$feesamount = PlatformFeeMetadata::feeFor($paymentIntentDetails, isset($paymentIntentDetails->charges->data[0]->application_fee_amount) ? $paymentIntentDetails->charges->data[0]->application_fee_amount / 100 : 0);

							$transactionId = DB::table('transactions')
								->insertGetId([
									'tour_booking_id' => $bookingdetails->id,
									'amount' => $splitamount,
									'fee_amount' => $feesamount,
									'transaction_number' => $paymentIntentId,
									'application_fee_id' => !empty($paymentIntentDetails->charges->data[0]->application_fee) ? $paymentIntentDetails->charges->data[0]->application_fee : 0,
									'via' => 'Card',
									'response_init' => '',
									'response_end' => '',
									'response_end_datetime' => null,
									'payment_type' => ($splitpayment == 1) ? 1 : 0,
									'status' => 'Completed',
									'visitor' => request()->getClientIp(),
									'request_from' => request()->is('api/*') ? Constants::API : Constants::BACKEND,
									'created_by' => Auth::id(),
									'created_by_user_type' => $userType,
									'created_at' => Carbon::now(),
								]);
							DB::commit();

							$crd = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->last4 : '';
							$cardtype = !empty($paymentIntentDetails->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentDetails->charges->data[0]->payment_method_details->card->brand : '';

							if ($userType == "Admin") {
								$user = Admin::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Admin" . ')';
							} elseif ($userType == "Tour Operator") {
								$user = Staff::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
							} elseif ($userType == "Affiliate") {
								$user = Customer::find(Auth::id());
								$created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
							}

							$historyId = DB::table('tour_booking_history')
								->insertGetId([
									'tour_booking_id' => $bookingdetails->id,
									'tourists' => $bookingdetails->tourists,
									'operation' => 'Payment',
									'details' => json_encode(['amount' => $splitamount, 'payment_type' => 'Card', 'card_number' => $crd, 'card_type' => $cardtype, 'payment_method' => ($splitpayment == 1) ? 'Split Payment' : 'Full Payment', 'created_by_with_name' => $created_by_with_name], JSON_NUMERIC_CHECK),
									'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
									'operation_by' => Auth::id(),
									'operation_by_user_type' => $userType,
									'updated_at' => Carbon::now(),
									'created_at' => Carbon::now(),
								]);
							DB::commit();

							if (!empty($data['note'])) {
								DB::table('tour_booking_history')
									->insert([
										'tour_booking_id' => $bookingdetails->id,
										'operation' => 'Note',
										'details' => $bookingdetails->note,
										'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
										'operation_by' => Auth::id(),
										'operation_by_user_type' => $userType,
										'updated_at' => Carbon::now(),
										'created_at' => Carbon::now(),
									]);
							}

							$isSplitOrFull = ($splitpayment == 1) ? 'Split Payment' : 'Full Payment';
							$bookingdetails->booking_id = $bookingdetails->id;
							$bookingdetails->tour_operator_id = $info->tour_operator_id;

							CardBookingService::run($bookingdetails->toArray(), $transactionId, $historyId, $isSplitOrFull, [], $paymentIntentDetails);

							$bookingDetails = TourBooking::where('id', $bookingdetails->id)->first();
							$bookingDetails->stripe_paymentintent_id = '';
							$bookingDetails->save();
							DB::commit();

							$info = DB::table('tour_slot_times AS tst')
								->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
								->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
								->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
								->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
								->join('states AS st', 'st.id', '=', 'ct.state_id')
								->where('tst.id', $bookingdetails->tour_slot_id)
								->select([
									'tst.time_from',
									'tsd.date',
									'tst.time_slot_seats as seats',
									'tp.tour_operator_id',
									'tsd.tour_package_id',
									'tp.name AS package_name',
									'tp.important_notes',
									'tp.things_to_bring',
									'ct.state_id',
									'st.permit_fee',
									't_o.name AS tour_operator',
									't_o.email AS tour_op_email',
									't_o.phone_number AS tour_op_phone',
									't_o.website AS tour_op_website',
									't_o.logo AS tour_op_logo',
									't_o.cancellation_policy',
									'tp.service_commission_percentage',
									't_o.tenant_id',
									't_o.stripe_destination_id',
									'tp.email as packageEmail',
									'tp.from_name as packageFromName',
									'tp.from_email as packageFromEmail',
									'tp.api_key as packageApiKey',
									DB::raw("(SELECT SUM(tbm.tourists)
										FROM tour_bookings_master AS tbm
										WHERE tbm.tour_slot_id = tst.id
											AND tbm.status != 'Cancelled'
										GROUP BY tbm.tour_slot_id) AS booked_seats")
								]);

							if ($userType == 'Tour Operator' || $userType == 'Affiliate') {
								$info = $info->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
							}

							$info = $info->first();

							$tourListwithSeat = DB::table('tour_bookings_transact as tbt')
								->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
								->where('tbt.tour_booking_id', '=', $bookingdetails->id)
								->where('tbt.deleted_by', 0)
								->select('tpr.rate_for', 'tbt.tourists', 'tbt.infants')
								->get();

							$totalTourists = 0;
							$totalInfants = 0;
							foreach ($tourListwithSeat as $value) {
								$totalTourists += $value->tourists;
								$totalInfants += $value->infants;
							}

							$customerdetails = Customer::find($bookingdetails->customer_id);
							$tour_d_t = "{$info->date} {$info->time_from}";

							$arEmailData = [
								'bookingId' => $bookingdetails->id,
								'tourOperatorId' => $info->tour_operator_id,
								'tourPackageId' => $info->tour_package_id,
								'tourOperator' => $info->tour_operator,
								'tourOpPhone' => $info->tour_op_phone,
								'tourOpEmail' => $info->tour_op_email,
								'tourOpWebsite' => $info->tour_op_website,
								'tourPackage' => $info->package_name,
								'tourDate' => date('l, F j, Y', strtotime($tour_d_t)),
								'tourDateTime' => date('l, F j, Y @ g:i a', strtotime($tour_d_t)),
								'urlBookingInfo' => route('customer.tour-booking-info', ['bookingId' => Crypt::encryptString($bookingdetails->id)]),
								'custName' => $customerdetails->name,
								'custEmail' => $customerdetails->email,
								'custPhone' => $customerdetails->phone_number,
								'urlLiabilityWaiver' => route('customer.liability-waiver-form', ['bookingId' => Crypt::encryptString($bookingdetails->id)]),
								'importantNotes' => $info->important_notes,
								'thingsToBring' => $info->things_to_bring ? json_decode($info->things_to_bring) : [],
								'cancellationPolicy' => $info->cancellation_policy,
								'cancellationUrl' => route('customer.confirm-cancel-booking', ['bookingId' => Crypt::encryptString($bookingdetails->id)]),
								'numberoftourist' => $totalTourists + $totalInfants,
								'tourListwithSeat' => $tourListwithSeat,
								'packageEmail' => $info->packageEmail,
								'packageFromName' => $info->packageFromName,
								'packageFromEmail' => $info->packageFromEmail,
								'packageApiKey' => $info->packageApiKey,
								'waiverPdf' => $info->waiver_pdf ?? null,
								'attachWaiverPdf' => $info->attach_waiver_pdf ?? null,
								'emailType' => config('constants.email_types.tour_booking_acknowledgement'),
								'operationBy' => Auth::id(),
								'operationByUserType' => $userType,
								'tenantId' => $info->tenant_id
							];

							if (!empty($info->tour_op_logo)) {
								$tourOperatorLogo = tourOperatorLogo($info->tenant_id, $info->tour_op_logo);
								$arEmailData['tourOpLogo'] = $tourOperatorLogo;
							}

							$tourPkgPhoto = DB::table('tour_package_photos')
								->where('tour_package_id', $info->tour_package_id)
								->where('placement', 2)
								->where('status', 'Active')
								->where('deleted_by', 0)
								->latest()
								->first(['path']);

							if ($tourPkgPhoto) {
								$tourPackageLogo = tourPackageLogo($info->tenant_id, $info->tour_package_id, $tourPkgPhoto->path);
								$arEmailData['tourPkgPhoto'] = $tourPackageLogo;
							}

							$tourPackageImage = tourPackageImage($info->tenant_id, $info->tour_package_id);
							$arEmailData['tourPkgImage'] = $tourPackageImage;

							if (in_array(Auth::user()->email, config('constants.prevent_sending_emails')) == false) {
								Mail::send(new TourBookingAcknowledgement($arEmailData, 'tour_booking_acknoledgement'));
							}

							$paymentAcknowledgement = 'success';
						} else {
							$bookingdetails->status = 'Cancelled';
							$bookingdetails->updated_by = Auth::id();
							$bookingdetails->updated_by = $userType;
							$bookingdetails->save();
							DB::commit();

							$paymentAcknowledgement = 'failed';
						}
					}
				} else {
					$paymentAcknowledgement = 'wrongdetails';
				}
			}
		}

		return $paymentAcknowledgement;
	}

	/**
	 * Get total payment amount of the cart
	 *
	 * @param int $id
	 * @param string $isAffiliate
	 *
	 * @return array
	 */
	public static function calculateTotalCartPayment($id, $isAffiliate): array
	{
		$tourBooking = DB::table('tour_bookings_master AS tbm')
			->where('tbm.id', $id)
			->first('cart_id');

		$oldBookingId = oldBookingId();
		$cashPriceEnabled = cashPriceEnabled();
		$cartPaymentTotalForCard = 0;
		$cartPaymentTotalForCash = 0;
		$cartCashNewPricingSubtt = $cartCashNewPricingTotal = $cartCashNewPricingServiceCommissionTotal = 0;
		$cartServiceCommissionTotal = 0;

		if ($tourBooking->cart_id && $tourBooking->cart_id != '') {
			$cartBookings = DB::table('tour_bookings_master AS tbm')
				->where('tbm.cart_id', $tourBooking->cart_id)
				->get();

			foreach ($cartBookings as $booking) {
				$bookingT = static::totalInformation($booking->id);
				$forCardProcChargePercentage = 0;
				$totalSubtotal = 0;
				$forCardProcChargePercentageForOld = 0;
				$serviceCommissionTotal = 0;
				$totalV = 0;
				$makePaymentValueForCardFailed = 0;
				$bookingCashSubtotal = 0;
				$bookingCashServiceCommission = 0;

				foreach ($bookingT as $bt) {
					$isCashRate = ((int)$bt->is_cash_rate == 1);
					$cashRate = ($cashPriceEnabled == 1 && $isCashRate == 1) ? $bt->cash_rate : $bt->rate;
					$cashAdditionalCharge = ($cashPriceEnabled == 1 && $isCashRate == 1) ? $bt->cash_tax : $bt->additional_charge;

					if ($bt->is_group_rate == '1') {
						$rate = $bt->total;
						$subtotal = $rate;
						$touristSubtotal = $subtotal;
						$totalSubtotal += $subtotal;
					} else {
						$rate = $bt->rate + $bt->additional_charge + $bt->permit_fee + $bt->partner_fees_amount;
						$per = $rate * ($bt->additional_tax_percentage + $bt->processing_charge_percentage) / 100;
						$subtotal = $rate + $per;
						$touristSubtotal = $subtotal * $bt->tourists;
						$totalSubtotal += $subtotal * $bt->tourists;
					}

					$cardProccharge = number_format((float)($touristSubtotal) * $bt->original_processing_charge_percentage / 100, 2, '.', '');
					$forCardProcChargePercentage += $cardProccharge;
					if ($booking->id < $oldBookingId) {
						$subtotalOld = $rate + $per;
						$touristSubtotalOld = $subtotalOld * $bt->tourists;
						$cardProcchargeOld = number_format((float)($touristSubtotalOld) * 2.9 / 100, 2, '.', '');
						$forCardProcChargePercentageForOld += $cardProcchargeOld;
					} else {
						$per = roundout(($rate * ($bt->nat_fees_percentage) / 100), 2);
						$serviceCommissionTotal += ($bt->is_group_rate == '1') ? $per : $bt->tourists * $per;
					}
					$cartServiceCommissionTotal += $serviceCommissionTotal;
					$makePaymentValueForCardFailed += $bt->makePaymentValueForCardFailed;


					if ($cashPriceEnabled == 1) {
						if ($bt->is_group_rate == '1') {
							$cashGroupTotal = $cashRate + $cashAdditionalCharge + $bt->partner_fees_amount;
						} else {
							$cashGroupTotal = ($bt->tourists * ($cashRate +
								$cashAdditionalCharge +
								$bt->permit_fee + $bt->partner_fees_amount
							));
						}

						$bookingCashSubtotal += $cashGroupTotal;

						if ($bt->is_group_rate == '1') {
							$bookingCashServiceCommission += roundout((($cashRate + $cashAdditionalCharge + $bt->partner_fees_amount) * $bt->nat_fees_percentage / 100), 2);
						} else {
							$cashServiceCommissionPax = roundout((($cashRate + $cashAdditionalCharge + $bt->permit_fee + $bt->partner_fees_amount) * $bt->nat_fees_percentage / 100), 2);
							$bookingCashServiceCommission += $bt->tourists * $cashServiceCommissionPax;
						}
					}
				}

				if ($booking->service_commission == null || $booking->service_commission == "0.00") {
					$subtotal = $booking->total - $booking->service_commission;
				} else {
					$subtotal = $makePaymentValueForCardFailed;
				}

				if ($isAffiliate == 'Yes') {
					if ($booking->discount2_value > 0) {
						$subtotal = $subtotal - $booking->discount2_value;
					}
					if ($booking->discount2_value > 0) {
						if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
							$subtotal = $booking->total;
						} else {
							$oldDiscountAmt = number_format((($booking->total) * $bookingT[0]->nat_fees_percentage) / 100, 2, '.', '');
							$subtotal = $booking->total + $oldDiscountAmt;
						}
					} else {
						if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
							if ($booking->id < $oldBookingId) {
								$processing_charge = $booking->total * 2.9 / 100;
								$totalV = $booking->total + $processing_charge;
							} else {
								$totalV = $booking->total;
							}
						} else {
							if (isset($bookingT[0]->affiliate_processing_percentage) && $bookingT[0]->affiliate_processing_percentage != "0.00") {
								if ($booking->id < $oldBookingId) {
									$processing_charge = $subtotal * 2.9 / 100;
									$totalV = $subtotal + $processing_charge;
								} else {
									$totalV = $subtotal + $serviceCommissionTotal;
								}
							} else {
								$totalV = $booking->total + $forCardProcChargePercentage;
							}
						}
					}
				} else {
					if ($booking->discount2_percentage > 0) {
						if ($booking->id < $oldBookingId) {
							$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentageForOld) * $booking->discount2_percentage) / 100, 2, '.', '');
							if ($booking->is_affiliate == "No") {
								$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) * 6) / 100), 2);
							} else {
								$percentage = 0;
							}
							if ($booking->service_commission == null || $booking->service_commission == "0.00") {
								$totalV = ($totalSubtotal + $forCardProcChargePercentageForOld - $oldDiscountAmt) + $percentage;
							} else {
								$totalV = $booking->total;
							}
						} else {
							$oldDiscountAmt = number_format((($totalSubtotal + $forCardProcChargePercentage) * $booking->discount2_percentage) / 100, 2, '.', '');
							$percentage = roundout(((($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) * $bookingT[0]->service_commission_percentage) / 100), 2);
							if ($booking->service_commission == null || $booking->service_commission == "0.00") {
								$totalV = ($totalSubtotal + $forCardProcChargePercentage - $oldDiscountAmt) + $percentage;
							} else {
								$totalV = $booking->total;
							}
						}
					} else {
						if ($booking->id < $oldBookingId) {
							if ($booking->is_affiliate == "No") {
								$percentage = roundout(((($subtotal + $forCardProcChargePercentageForOld) * 6) / 100), 2);
							} else {
								$percentage = 0;
							}

							if ($booking->service_commission == null || $booking->service_commission == "0.00") {
								$totalV = $subtotal + $forCardProcChargePercentageForOld + $percentage;
							} else {
								$totalV = $booking->total;
							}
						} else {
							if ($booking->discount2_value > 0 && isset($bookingT[0]->nat_fees_percentage)) {
								$serviceCommissionTotal = roundout(($subtotal * ($bookingT[0]->nat_fees_percentage) / 100), 2);

								$totalV = $subtotal + $serviceCommissionTotal;
							} else {
								if ($serviceCommissionTotal > 0) {
									$totalV = $subtotal + $serviceCommissionTotal;
								} else {
									$totalV = $booking->total;
								}
							}
						}
					}
				}
				if ($cashPriceEnabled == 1) {
					$cashDiscountAmt = 0;

					if ($booking->discount2_percentage > 0) {
						$cashDiscountAmt = roundout(($bookingCashSubtotal * $booking->discount2_percentage / 100), 2);
						$bookingCashServiceCommission = roundout((($bookingCashSubtotal - $cashDiscountAmt) * $bookingT[0]->nat_fees_percentage / 100), 2);
					} elseif ($booking->discount2_value > 0) {
						$cashDiscountAmt = $booking->discount2_value;
						$bookingCashServiceCommission = roundout((($bookingCashSubtotal - $cashDiscountAmt) * $bookingT[0]->nat_fees_percentage / 100), 2);
					}
					$cartCashNewPricingSubtt += ($bookingCashSubtotal - $cashDiscountAmt);
					$cartCashNewPricingServiceCommissionTotal += $bookingCashServiceCommission;
					$cartCashNewPricingTotal += ($bookingCashSubtotal - $cashDiscountAmt) + $bookingCashServiceCommission;
				}

				// Priced add-ons still owed on this booking. fetchPayLaterAddonsWithPrice covers
				// BOTH storages — old-style tour_booking_forms.form_submission AND new-style
				// tour_booking_addon_fields rows — so it replaces the direct form_submission
				// loop that predated it here, which missed new-style add-ons entirely and made
				// the cart payment modal (and the charge's destination fee) understate the total.
				// A booking already settled by a completed base payment returns zeros.
				$bookingTrans = Transaction::where('tour_booking_id', $booking->id)
					->where('status', 'Completed')
					->whereNull('charge_type')
					->orderBy('id', 'DESC')
					->first();
				$payLaterAddons = static::fetchPayLaterAddonsWithPrice(
					$booking->id,
					(float) ($bookingT[0]->nat_fees_percentage ?? 0),
					$bookingTrans
				);
				if ($payLaterAddons['addons_price'] > 0) {
					$subtotal += $payLaterAddons['addons_price'];
					$cartServiceCommissionTotal += $payLaterAddons['addons_fee'];
					$totalV += $payLaterAddons['addons_total'];
					if ($cashPriceEnabled == 1) {
						$bookingCashSubtotal += $payLaterAddons['addons_price'];
						$bookingCashServiceCommission += $payLaterAddons['addons_fee'];
					}
				}

				$customCharges = CustomChargeBookingService::customChargeList($booking->id);
				if (is_iterable($customCharges)) {
					foreach ($customCharges as $key => $customCharge) {
						$subtotal += $customCharge->amount;
						$totalV += $customCharge->amount;
						if ($customCharge->taxable == 1) {
							$totalV += $customCharge->fees;
							$cartServiceCommissionTotal += $customCharge->fees;
						}
						if ($cashPriceEnabled == 1) {
							$bookingCashSubtotal += $customCharge->amount;
							if ($customCharge->taxable == 1) {
								$bookingCashServiceCommission += $customCharge->fees;
							}
						}
					}
				}



				$cartPaymentTotalForCash += $subtotal;
				$cartPaymentTotalForCard += $totalV;
			}
		}
		return [
			'cartPaymentTotalForCash' => $cartPaymentTotalForCash,
			'cartPaymentTotalForCard' => $cartPaymentTotalForCard,
			'cartServiceCommissionTotal' => $cartServiceCommissionTotal,
			'cartCashNewPricingSubtt' => $cartCashNewPricingSubtt,
			'cartCashNewPricingTotal' => $cartCashNewPricingTotal,
			'cartCashNewPricingServiceCommissionTotal' => $cartCashNewPricingServiceCommissionTotal,
		];
	}

	/**
	 * Get All info of Booking
	 *
	 * @param int $id
	 *
	 * @return mixed
	 */
	public static function bookingInfoData($id): mixed
	{
		$bookingInfo = DB::select("
				SELECT
					`tbm`.`id`,
					`tbm`.`id` as `booking_id`,
					`tbm`.`hotel_id`,
					`tbm`.`total`,
					`tbm`.`discount2_value`,
					`tbm`.`discount2_percentage`,
					`c`.`name` as `Name`,
					`sd`.`date` as `Date`,
					`st`.`time_from` as `StartTime`,
					`st`.`time_to` as `EndTime`,
					`tp`.`package_has_slots`,
					`c`.`is_affiliate`,
					`c`.`phone_number` as `ContactNumber`,
					`c`.`email` as `Email`,
					`tbm`.`tourists`,
					`tbm`.`infants`,
					`c`.`id` as `customer_id`,
					`t_o`.`id` as `tour_operator_id`,
					`t_o`.`name` as `tour_operator_name`,
					`tbm`.`service_commission`,
					`t_o`.`stripe_destination_id`,
					`tp`.`stripe_destination_id` as `partnerDestionationId`,
					`tp`.`name` as `package_name`,
					`tp`.`service_commission_percentage`,
					`c`.`affiliate_processing_percentage`,
					`tbm`.`created_at`,
					`tbm`.`booking_total`,
					`tbm`.`is_reserved_for_affiliate`,
					`tbm`.`tour_package_id`,
					`tbm`.`created_by`,
					`tbm`.`created_by_user_type`,
					`tbm`.`viator_bookingRef` AS `tripadvisor_id`,
					`tbm`.`get_your_guide_reference_booking` AS `get_your_guide_booking_id`,
					`tbm`.`ota_reference_booking` AS `ota_booking_id`,
					`tbm`.`reference_booking`
				FROM
					`tour_bookings_master` as `tbm`
					INNER JOIN `customers` as `c` ON `c`.`id` = `tbm`.`customer_id`
					INNER JOIN `tour_bookings_transact` as `tbt` ON `tbt`.`tour_booking_id` = `tbm`.`id`
					INNER JOIN `tour_packages` as `tp` ON `tp`.`id` = `tbm`.`tour_package_id`
					INNER JOIN `tour_operators` as `t_o` ON `t_o`.`id` = `tp`.`tour_operator_id`
					INNER JOIN `tour_slot_times` as `st` ON `st`.`id` = `tbm`.`tour_slot_id`
					INNER JOIN `tour_slot_dates` as `sd` ON `sd`.`id` = `st`.`tour_slot_id`
				WHERE
				`tbm`.`id` = " . $id . "
			");

		return $bookingInfo;
	}

	/**
	 * Modify Tourists Groups string
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	public static function tGroupsString($id): string
	{
		$tGroups = DB::select(
			"(SELECT GROUP_CONCAT(
        CONCAT(
            LEFT(tpr.rate_for, 1), ':',
            CASE 
                WHEN tbt.is_infant_rate = 1 THEN tbt.infants 
                ELSE tbt.tourists 
            END,
            '^',
            tpr.rate_for
        )
        ORDER BY tpr.age_from DESC, tpr.age_to DESC 
        SEPARATOR ' | '
    ) AS t_groups
    FROM tour_bookings_transact AS tbt
    INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id
    WHERE tbt.tour_booking_id = " . $id . ")"
		);

		$tGroupsChanged = '';
		$rateGroupWithTourists = array();
		$groupsArray = array();
		if (isset($tGroups[0]->t_groups) && !empty($tGroups[0]->t_groups)) {
			$groupsArray = explode('|', $tGroups[0]->t_groups);
			$cnt = count($groupsArray);
			if (!empty($groupsArray)) {
				foreach ($groupsArray as $key1 => $value) {
					$rateGroupWithTourists = explode('^', $value);
					if (!empty($rateGroupWithTourists)) {
						if (Str::contains($value, "Adults - Military Teachers") || Str::contains($value, "Children (Ages 8-12) - Military/Teachers") || Str::contains($value, "Children (Ages 4-7) - Military/Teachers")) {
							if (Str::contains($value, "Adults - Military Teachers")) {
								$tGroupsChanged .= "AM" . substr($rateGroupWithTourists[0], 2);
							} else if (Str::contains($value, "Children (Ages 8-12) - Military/Teachers") || Str::contains($value, "Children (Ages 4-7) - Military/Teachers")) {
								$tGroupsChanged .= "CM" . substr($rateGroupWithTourists[0], 2);
							}
							if ($key1 != $cnt - 1) {
								$tGroupsChanged .= " | ";
							}
						} else {
							$tGroupsChanged .= $rateGroupWithTourists[0];
							if ($key1 != $cnt - 1) {
								$tGroupsChanged .= " | ";
							}
						}
					}
				}
			}
		}

		return $tGroupsChanged;
	}

	/**
	 * Calculate remaining total ticket cost for partial refund feature
	 *
	 * @param int $bookingId
	 *
	 * @return mixed
	 */
	public static function calculateRemainingTotalTicketCost($bookingId): mixed
	{
		$tourBooking = TourBooking::where('id', $bookingId)->first();
		$isCashFees = cashFees();
		$transaction = Transaction::where('tour_booking_id', $bookingId)
			->where('status', 'Completed')
			->whereNull('charge_type')
			->orderBy('id', 'ASC')
			->first();

		$paymentOnly = TourBookingHistory::where('tour_booking_id', $bookingId)->where('operation', 'Payment')->whereNull('charge_type')->get();
		$refundOnly = TourBookingHistory::where('tour_booking_id', $bookingId)->where('operation', 'Refund')->whereNull('charge_type')->get();
		$paymentTransCount = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->count();

		$netAmount = 0;
		$netPartnerFees = 0;
		if (($transaction != null) && ($transaction->via == "Cash" || $transaction->via == "Invoiced" || $transaction->via == "Wire Transfer")) {
			$ref = 0;
			$net_amount_split = 0;
			$fees = 0;
			$dontrefundamount = 0;
			$oldRefundKeepUs = 0;
			$ticketCostAmount = 0;
			$platformFeesAmount = 0;
			$partnerFeesAmount = 0;
			$refundPartnerFeesAmount = 0;
			foreach ($refundOnly as $value) {
				$details = json_decode($value->details);
				$ref += $details->amount;
				$refundPartnerFeesAmount += $value->partner_fees;
				if ($isCashFees == '1' && $value->payment_method == "Don't Refund") {
					$dontrefundamount += $details->amount;
				}
				if ($isCashFees == '1' && isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees') {
					if (isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
						$oldRefundKeepUs += $details->refund_keep_us;
					}
				}
			}

			foreach ($paymentOnly as $value) {
				$details = json_decode($value->details);
				$net_amount_split += $details->amount;
				$fees += $value->platform_fees;
				$ticketCostAmount += $value->ticket_cost;
				$platformFeesAmount += $value->platform_fees;
				$partnerFeesAmount += $value->partner_fees;
			}

			if ($isCashFees == '1' && $transaction != null && $transaction->via == "Cash") {
				if ($transaction->payment_type == 1 || $paymentTransCount > 1) {
					$netAmount = $ticketCostAmount - $ref - $dontrefundamount;
				} else {
					$netAmount = $tourBooking->total + $oldRefundKeepUs - $tourBooking->service_commission;
				}
			} else {
				$netAmount = $net_amount_split - $ref;
			}
			$netPartnerFees = $partnerFeesAmount - $refundPartnerFeesAmount;
		}
		//Calculate ticket cost for card bookings
		if ($transaction != null && $transaction->via == "Card") {
			if ($transaction->payment_type == 1 || $paymentTransCount > 1) {
				$trans = Transaction::where('tour_booking_id', $bookingId)->where('status', 'Completed')->whereNull('charge_type')->get();
				foreach ($trans as $value) {
					$histPaymentDetails = TourBookingHistory::where('transaction_id', $value->id)->where('operation', 'Payment')->first(['card_last_4_digit', 'ticket_cost', 'platform_fees']);
					$refundedTransForTransaction = Transaction::where('tour_booking_id', $bookingId)->where('transaction_number', $value->transaction_number)->where('status', 'Refund')->whereNull('charge_type')->get(['id', 'amount']);

					$refundedTransForTransactionZero = Transaction::where('tour_booking_id', $bookingId)->where('transaction_number', $value->transaction_number)->where('status', 'Refund')->where('amount', 0)->whereNull('charge_type')->get(['id', 'amount']);
					$dontRefundAmount = 0;
					foreach ($refundedTransForTransactionZero as $his) {
						$tBH = TourBookingHistory::where('transaction_id', $his->id)->first();
						$jsonData = json_decode($tBH->details, true);
						$dontRefundAmount += $jsonData['amount'];
					}
					$totalRefundedAmountForTransaction = 0;
					foreach ($refundedTransForTransaction as $ky => $val) {
						$totalRefundedAmountForTransaction += $val->amount;
					}
					$netAmountTrans = $value->amount - $totalRefundedAmountForTransaction - $dontRefundAmount;

					$netAmount += $netAmountTrans;
				}
			} else {
				$oldRefundKeepUs = 0;
				foreach ($refundOnly as $key => $value) {
					$details = json_decode($value->details);
					if (isset($details->refund_type) && $details->refund_type == 'Full Refund minus Fees') {
						if (isset($details->refund_keep_us) && $details->refund_keep_us > 0) {
							$oldRefundKeepUs += $details->refund_keep_us;
						}
					}
				}
				$netAmount = $tourBooking->total + $oldRefundKeepUs; //- $tourBooking->service_commission;
			}
		}


		return [
			'netAmount' => $netAmount
		];
	}

	/**
	 * Guard a discount-refund amount against the tour cost genuinely still available to
	 * discount against.
	 *
	 * editDiscount()/applyDiscount()/editDiscountRefund() (both TourBookingRepository and its
	 * V3 counterpart) only ever compared the WHOLE booking's charges to its refunds across
	 * every charge_type combined (tour, add-ons, tips, custom charges, prior discounts, all
	 * lumped together) -- so that check can only ever catch "this booking has been refunded in
	 * full", never "the TOUR side specifically has already been refunded down to $0 while
	 * add-ons/Don't-Refund settlements are still outstanding". That gap let a discount refund
	 * draw down money that has nothing to do with the tour -- confirmed live on booking
	 * D-71132: one pax was removed with a 100% ticket refund (fully zeroing the remaining
	 * pax's tour cost, principal and fee both), a 10% discount was then applied anyway, and
	 * its $8.05 refund silently ate into a separately-tracked "Don't Refund" add-on settlement
	 * instead, since there was no tour money left for it to legitimately come from.
	 *
	 * Reuses calculateRemainingTotalTicketCost() -- the same tour-only net-available figure
	 * (principal + fee combined, same as $refundAmount below) PartialRefundCancelService
	 * already caps ticket refunds against -- so "how much tour money is still refundable"
	 * stays answered the same way everywhere in the codebase.
	 *
	 * @param  int   $bookingId
	 * @param  float $refundAmount The discount's own refund amount (principal + fee) about to
	 *                             be sent to Stripe/cash/ledger.
	 * @throws Exception when $refundAmount exceeds the tour cost still available to discount.
	 */
	public static function ensureDiscountRefundWithinTicketAvailability(int $bookingId, float $refundAmount): void
	{
		$available = round(max(0.0, (float) (self::calculateRemainingTotalTicketCost($bookingId)['netAmount'] ?? 0)), 2);

		// Small epsilon for float/rounding noise between how $refundAmount and $available were
		// each independently accumulated -- not a loophole, just avoids a false trip on
		// something like 7.330000000000001 vs 7.33.
		if (round($refundAmount, 2) > $available + 0.01) {
			throw new Exception(
				'Unable to apply discount. Refund amount (' . number_format($refundAmount, 2) . ') is greater '
				. 'than the tour cost still available to discount (' . number_format($available, 2) . ') -- the '
				. 'rest has already been refunded elsewhere.'
			);
		}
	}

	/**
	 * New booking from agenda section from slot
	 *
	 * @param Request $request
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function bookFromAgenda($request, $vars): array
	{
		$timeSlot = TimeSlotService::getTimeSlot($request->time_slot_id);

		if (!$timeSlot) {
			throw new InvalidArgumentException("Time slot not found for ID: {$request->time_slot_id}");
		}

		$request->replace([
			'date' => str_replace('-', '', $timeSlot->date),
			'tourPackageId' => $timeSlot->tour_package_id,
			'getRateGroups' => 1
		]);

		$tourSlotRepository = new TourSlotRepository();
		$tourSlotTime = $tourSlotRepository->getTourSlotTimesByDate((object)$request);
		$response = json_decode(json_encode($tourSlotTime));

		$vars = [
			'slotTimes' => $response->slotTimes,
			'rateGroups' => $response->rateGroups,
			'date' => $timeSlot->date,
			'tourPackageId' => $timeSlot->tour_package_id,
			'timeSlotId' => $timeSlot->id,
			'isGroupRateEnabled' => $response->isGroupRateEnabled,
			'applyOnSlots' => $response->applyOnSlots ?? 0
		] + $vars;

		return $vars;
	}

	/**
	 * New booking from agenda section from slot
	 *
	 * @param Request $request
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function bookFromRebook($request, $vars): array
	{
		$customer = CustomerService::getCustomerWithBooking($request->customer_id, $request->booking_id);
		$vars['customer'] = $customer;

		// Check whether customer is an affiliate
		$checkAff = array();
		if ($customer->is_affiliate == "Yes") {
			$checkAff['is_affiliate'] = 1;
			$checkAff['affiliate_id'] = $customer->id;
		}

		$vars['is_affiliate'] = $checkAff;
		$vars['tourPackageId'] = $customer->tour_package_id;

		if (request('rebook')) {
			$vars['tourPackageId'] = $customer->tour_package_id;

			$vars['touristsGroups'] = DB::table('tour_bookings_transact AS tbt')
				->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
				->where('tbt.tour_booking_id', $request->booking_id)
				->orderByDesc('tpr.age_from')
				->orderByDesc('tpr.age_to')
				->get(['tpr.rate_for', 'tbt.tour_package_rate_id', 'tbt.tourists']);

			$bookData = DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->where('tbm.id', $request->booking_id)
				->first(['tst.id as timeSlotId', 'tst.date']);

			$request->replace([
				'date' => $bookData->date,
				'tourPackageId' => $vars['tourPackageId'],
				'getRateGroups' => 1,
				'fromCreateBooking' => 1,
				'affiliateId' => ($customer->is_affiliate == "Yes") ? $customer->id : 0
			]);

			$tourSlotRepository = new TourSlotRepository();
			$tourSlotTime = $tourSlotRepository->getTourSlotTimesByDate((object)$request);
			$response = json_decode(json_encode($tourSlotTime));
			$vars = [
				'date' => $bookData->date,
				'slotTimes' => $response->slotTimes,
				'rateGroups' => $response->rateGroups,
				'timeSlotId' => $bookData->timeSlotId,
				'isGroupRateEnabled' => $response->isGroupRateEnabled,
				'applyOnSlots' => $response->applyOnSlots ?? 0,
			] + $vars;
		}

		return $vars;
	}

	/**
	 * Get the route prefix info based on user role
	 *
	 *
	 * @return string
	 */
	public static function routePrefixInfo(): string
	{
		$userType = userType();

		if ($userType == "Admin") {
			$routePrefix = 'admin.';
		} elseif ($userType == "Tour Operator") {
			$routePrefix = '';
		} elseif ($userType == "Affiliate") {
			$routePrefix = '';
		}
		return $routePrefix;
	}

	/**
	 * Edit discount of booking
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public static function addEditDiscount($data)
	{
		return DiscountHistory::create($data);
	}

	/**
	 * This function is used to edit booking table data while edit discount
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public static function editBooking($data)
	{
		$booking = TourBooking::find($data['tour_booking_id']);
		$booking->tour_promotion_id = $data['new_tour_promotion_id'];
		$booking->discount2_value = $data['new_discount2_value'];
		$booking->discount2_percentage = $data['new_discount2_percentage'];
		$booking->service_commission = (float)$data['service_commission'];
		$booking->total = $data['rate'];
		$booking->booking_total = $data['rate'];
		$booking->discount_promo_code = $data['new_discount_promo_code'];
		$booking->save();
	}

	/**
	 * This function is used to edit booking table data while edit discount
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public static function updateBooking($data, $isPayment = true, $payLater = 0, $split = 0)
	{
		$total = roundout($data['new_total'] + $data['service_commission']);
		$subtt = roundout($data['new_total']);
		$isCashFees = cashFees();

		if ($payLater == 1) {
			$tourBooking = TourBooking::find($data['tour_booking_id']);
			$bookingTotal = $data['totalTicketCost'] - $data['new_discount2_value'];

			/* Pay Later */
			$updates = [
				'tour_promotion_id' => $data['new_tour_promotion_id'],
				'discount2_value' => $data['new_discount2_value'],
				'total' => $bookingTotal,
				'booking_total' => $bookingTotal,
				'updated_at' => mysqlDT(),
				'updated_by' => Auth::id(),
				'updated_by_user_type' => userType(),
				'discount2_percentage' => $data['new_discount2_percentage'],
				'discount_promo_code' => $data['new_discount_promo_code']
			];
		} else {
			$totalUpdate = round(($data['payment_method'] == "Card" || ($isCashFees == 1 && $data['payment_method'] == 'Cash')) ? $total : $subtt, 2);
			$serviceCommissionUpdate = round(($data['payment_method'] == "Card" || ($isCashFees == 1 && $data['payment_method'] == 'Cash')) ? $data['service_commission'] : 0, 2);

			if ($isPayment) {
				/* Payment */
				$updates = [
					'tour_promotion_id' => $data['new_tour_promotion_id'],
					'discount2_value' => $data['new_discount2_value'],
					'service_commission' => DB::raw("service_commission + $serviceCommissionUpdate"),
					'total' => DB::raw("total + $totalUpdate"),
					'booking_total' => DB::raw("booking_total + $totalUpdate"),
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => userType(),
					'discount2_percentage' => $data['new_discount2_percentage'],
					'discount_promo_code' => $data['new_discount_promo_code']
				];
			} else {
				/* Refund */
				$updates = [
					'tour_promotion_id' => $data['new_tour_promotion_id'],
					'discount2_value' => $data['new_discount2_value'],
					'service_commission' => DB::raw("service_commission - $serviceCommissionUpdate"),
					'total' => DB::raw("total - $totalUpdate"),
					'updated_at' => mysqlDT(),
					'updated_by' => Auth::id(),
					'updated_by_user_type' => userType(),
					'discount2_percentage' => $data['new_discount2_percentage'],
					'discount_promo_code' => $data['new_discount_promo_code']
				];

				if ($split == 1) {
					$updates['booking_total'] = DB::raw("booking_total - $totalUpdate");
				}
			}
		}

		$tourBooking = TourBooking::find($data['tour_booking_id']);
		$tourBooking->update($updates);
	}

	/**
	 * This function is used to edit transaction table data while edit discount
	 *
	 * @param array $data
	 *
	 * @return mixed
	 */
	public static function editTransaction($data)
	{
		Transaction::where('tour_booking_id', $data['tour_booking_id'])->update([
			'amount' => $data['total_sum_booking'],
			'fee_amount' => $data['total_sum_fees']
		]);
	}

	/**
	 * Get the refund amount bases on cancellation policy
	 * @param mixed $bookingId
	 * @param mixed $bookingM
	 * @param mixed $refundType
	 * @param mixed $refundPercent
	 * @return array{partialAmount: float|int|array{partialAmount: int}}
	 */
	public static function calculateRefundAmountOnCancellation($bookingId, $bookingM, $refundType, $refundPercent)
	{
		$bookingT = DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', $bookingId)
			->orderByDesc('tpr.age_from')
			->orderByDesc('tpr.age_to')
			->get([
				'tbt.id',
				'tbt.tourists',
				'tbt.processing_charge_percentage',
				'tbt.additional_tax_percentage',
				'tbt.permit_fee',
				'tbt.additional_charge',
				'tbt.state_tax',
				'tbt.rate',
				'tbt.total',
				'tbt.stripe_fees_percentage',
				'tpr.rate_for'
			]);

		if (!$bookingT) {
			abort(500, "Unable to get booking's information.");
		}

		$trans = Transaction::where('tour_booking_id', $bookingId)
			->where('status', 'Completed')
			->whereNull('charge_type')
			->orderBy('id', 'DESC')
			->first();

		$historyPaymentTime = TourBookingHistory::where('tour_booking_id', $bookingId)
			->where('operation', 'Payment')
			->first();

		if (!empty($historyPaymentTime)) {
			$tourBookingHistory = TourBookingHistory::where('tour_booking_id', $bookingId)
				->where('operation_at', '>', $historyPaymentTime->operation_at)
				->where(function ($query) {
					return $query->where('operation', '=', 'Refund')
						->orWhere('operation', '=', 'Cancelled');
				})
				->get();
		}

		$paymentOnly = TourBookingHistory::where('tour_booking_id', $bookingId)
			->where('operation', 'Payment')
			->whereNull('charge_type')
			->get();

		$paymentOnlyCheck = 0;
		foreach ($paymentOnly as $value) {
			$details = json_decode($value->details);
			$paymentOnlyCheck += $details->amount;
		}

		$amount = 0;
		$paidF = 0;
		if (!empty($trans)) {
			$paidF = $paymentOnlyCheck;
		}

		if (!empty($tourBookingHistory)) {
			foreach ($tourBookingHistory as $bookingHistory) {
				$details = json_decode($bookingHistory->details);
				if (isset($details->refund_type) && $details->refund_type != "Don't Refund" && $details->refund_type != "don't refund") {
					$amount += $details->amount;
				}

				$paidF = $paymentOnlyCheck - $amount;
			}
		}

		$partialAmount = 0;
		if ($paidF >= 1) {
			if ($bookingM->service_commission > 0 && $refundType == 'ticket_cost') {
				$stripeFeesPercentage = $paidF * $bookingT[0]->stripe_fees_percentage / 100;
				$ticketCost = $paidF - $stripeFeesPercentage;
				if ($refundPercent < 100) {
					$partialAmount = round(($ticketCost * $refundPercent) / 100, 2);
				} else {
					$partialAmount = round($ticketCost, 2);
				}
			} else if ($refundType == 'ticket_cost') {
				if ($refundPercent < 100) {
					$partialAmount = round(($paidF * $refundPercent) / 100, 2);
				} else {
					$partialAmount = round($paidF, 2);
				}
			}
		}

		return [
			'partialAmount' => $partialAmount
		];
	}

	/**
	 * Summary of calculateAddEditDiscountAmount
	 * @param mixed $bookingId
	 * @param mixed $discountValue
	 * @return array{discountType: string, discountValue: mixed, fees: float, newDiscountValue: float, oldDiscountValue: float, remainingDiscountValue: float, sub_total: float, ticket_cost: float, total_discount_fees: float}
	 */
	public static function calculateAddEditDiscountAmount($bookingId, $discountValue)
	{
		$tourBooking = DB::table('tour_bookings_transact AS tbt')
			->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbt.tour_booking_id', $bookingId)
			->orderByDesc('tpr.age_from')
			->orderByDesc('tpr.age_to')
			->get([
				'tbt.id',
				'tbt.tourists',
				'tbt.processing_charge_percentage',
				'tbt.additional_tax_percentage',
				'tbt.permit_fee',
				'tbt.additional_charge',
				'tbt.state_tax',
				'tbt.rate',
				'tbt.total',
				'tbt.stripe_fees_percentage',
				'tpr.rate_for',
				'tbt.nat_fees_percentage'
			]);

		$booking = TourBooking::find($bookingId);

		$ticketCost = (float)$tourBooking[0]->total;

		$subTotal = $ticketCost - $discountValue;

		$oldDiscountValue = (float)$booking->discount2_value;
		$remainingDiscountValue = $oldDiscountValue - round($discountValue, 2);
		$discountType = 'same';
		$newDiscountValue = 0.00;
		if ($remainingDiscountValue > 0) {
			$discountType = 'payment';
			$newDiscountValue =  $remainingDiscountValue;
		} elseif ($remainingDiscountValue < 0) {
			$newDiscountValue =  $discountValue - $oldDiscountValue;
			$discountType = 'refund';
		}
		$fees = $newDiscountValue * $tourBooking[0]->nat_fees_percentage / 100;

		$totalDiscountFees = $subTotal * $tourBooking[0]->nat_fees_percentage / 100;

		return [
			'ticket_cost' => $ticketCost,
			'sub_total' => $subTotal,
			'fees' => roundout($fees, 2),
			'discountValue' => $discountValue,
			'newDiscountValue' => $newDiscountValue,
			'discountType' => $discountType,
			'oldDiscountValue' => $oldDiscountValue,
			'remainingDiscountValue' => $remainingDiscountValue,
			'total_discount_fees' => $totalDiscountFees
		];
	}

	/**
	 * Summary of sortBundleRedeemTickets
	 * @param object $package
	 * @param array $redeemTicket
	 * @return array
	 */
	public static function sortBundleRedeemTickets($package, $redeemTicket)
	{
		$bundleRelationPackages = DB::table('tour_package_bundle_relations as tpbr')
			->where('tpbr.parent_id', $package->id)
			->whereNull('tpbr.deleted_at')
			->pluck('tpbr.tour_package_id')
			->toArray();

		if (count($bundleRelationPackages) && count($redeemTicket)) {
			usort($redeemTicket, function ($a, $b) use ($bundleRelationPackages) {
				// Get the index of the current IDs from $bundleRelationPackages
				$posA = array_search($a->id, $bundleRelationPackages);
				$posB = array_search($b->id, $bundleRelationPackages);

				// Sort based on the position in $bundleRelationPackages
				return $posA - $posB;
			});
		}

		return $redeemTicket;
	}

	/**
	 * Calculation for discount payment
	 * 
	 * @param int $id
	 * @return mixed
	 */
	public static function calculateDiscountpayment($id)
	{
		$totalDiscountPayment = 0;

		$transactionsDiscount = Transaction::with('bookingHistory')
			->where('tour_booking_id', $id)
			->whereIn('status', ['Completed', 'Failed', 'Refund'])
			->where('charge_type', 'discount')
			->orderBy('id', 'DESC')
			->first();

		if ($transactionsDiscount) {
			$historyPaymentTime = TourBookingHistory::select('id', 'operation_at', 'details')
				->where('tour_booking_id', $id)
				->where('operation', 'Payment')
				->where('charge_type', 'discount')
				->get();

			foreach ($historyPaymentTime as $data) {
				$decodedHistory = json_decode($data->details);
				$amount = $decodedHistory->amount ?? 0;
				$totalDiscountPayment += $amount;
			}
		}

		return $totalDiscountPayment;
	}

	/**
	 * Get the custom rate date data based on customer booking date and slot
	 * 
	 * @param string $date
	 * @param mixed $packageId
	 * @param string $slot
	 * @return mixed
	 */
	public static function getCustomRateDate($date, $packageId, $slotTimeId = 0, $affiliateId = 0)
	{
		$slotTime = null;
		if ($slotTimeId) {
			$slotTime = TourSlotTime::find($slotTimeId);
		}

		if (!$affiliateId) {
			$affiliateId = null;
		}

		$slotDate = null;
		if ($slotTime) {
			$slotDate = SurchargeDate::where('affiliate_id', $affiliateId)
				->where('tour_package_id', $packageId)
				->where('date_range_start', '<=', $date)
				->where('date_range_end', '>=', $date)
				->whereHas('rateSlotMappings', fn($query) => $query->whereTime('time_from', $slotTime->time_from))
				->orderBy('priority', 'desc')
				->first();
		}

		$generalDate = SurchargeDate::where('affiliate_id', $affiliateId)
			->where('tour_package_id', $packageId)
			->where('date_range_start', '<=', $date)
			->where('date_range_end', '>=', $date)
			->where('apply_on_slots', 0)
			->orderBy('priority', 'desc')
			->first();

		// Priority decides between overlapping slot-specific and all-slot custom dates; a
		// slot-specific one only wins a tie. Returning the slot match unconditionally let a
		// lower priority rule win (kens/Ajoy: "May-Oct" $52 on 10:00-14:00 at priority 1 beat
		// "June - Dec | Standardized Rate" $49 at priority 2).
		if ($slotDate && (!$generalDate || $slotDate->priority >= $generalDate->priority)) {
			return $slotDate;
		}

		return $generalDate;
	}

	/**
	 * Execute the getBookingCheckInType service to get the booking existing/updated check-in type.
	 *
	 * @param mixed $bookingId
	 *
	 * @return int
	 */
	public static function getBookingCheckInType($bookingId, $isBundlePackage, $checkInType, $isGroupBooking): int
	{
		$bookingCheckInType = $checkInType;
		$bookingT = BookingService::totalInformation($bookingId, $isGroupBooking);

		foreach ($bookingT as $key => $booking) {
			if ($isBundlePackage == 0) {
				if (isset($booking->old_checkin_type)) {
					$bookingCheckInType = $booking->old_checkin_type;
				} else {
					foreach ($booking->redeem_ticket as $key => $ticket) {
						foreach ($ticket as $key => $tt) {
							if ($checkInType != $tt['checkin_type']) {
								$bookingCheckInType = $tt['checkin_type'];
							}
						}
					}
				}
			}
		}

		return $bookingCheckInType;
	}

	/**
	 * Execute the calculateExpiryDate to get expiry date.
	 *
	 * @param mixed $packageId, $customerId
	 *
	 * @return string
	 */
	public static function calculateExpiryDate($packageId, $customerId)
	{
		$expireAt = NULL;
		$package = TourPackage::find($packageId);
		$customer = Customer::find($customerId);

		$expireInDays = ($customer && $customer->is_affiliate === "Yes")
			? (int) $customer->expire_in_days
			: (int) $package->expire_in_days;

		if ($expireInDays > 0) {
			$expireData = Carbon::now()->addDays($expireInDays);
			$expireAt = $expireData->toDateTimeString();
		}
		return $expireAt;
	}

	/**
	 * Summary of getManagers
	 * @return Collection
	 */
	public static function getManagers()
	{
		$managers = DB::table('tour_operator_staff as tos')
			->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
			->join('roles as r', 'r.id', '=', 'mhr.role_id')
			->where('mhr.model_type', 'App\Models\Staff')
			->where('r.name', 'manager')
			->where('tos.status', '!=', 'Deleted')
			->where('tos.department_id', Auth::user()->department_id)
			->get(['tos.id', DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as name")]);

		return $managers;
	}

	/**
	 * Summary of getTourGuides
	 * @return Collection
	 */
	public static function getTourGuides()
	{
		$tourGuides = DB::table('tour_operator_staff as tos')
			->join('model_has_roles as mhr', 'mhr.model_id', '=', 'tos.id')
			->join('roles as r', 'r.id', '=', 'mhr.role_id')
			->where('mhr.model_type', 'App\Models\Staff')
			->where('r.name', 'tour-guide')
			->where('tos.status', '!=', 'Deleted')
			//->where('tos.department_id', Auth::user()->department_id)
			->orderBy('tos.first_name')
			->get(['tos.id', DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as name"), 'tos.capacity', 'guide_status']);

		return $tourGuides;
	}

	/**
	 * Summary of getAssignedTourGuides
	 * @return array
	 */
	public static function getAssignedTourGuides($id)
	{
		$tourGuides = DB::table('booking_tour_guides')
			->where('booking_id', $id)
			->pluck('guide_id')
			->toArray();

		return $tourGuides;
	}

	/**
	 * Summary of requestPermissions
	 * @param mixed $id
	 * @return array
	 */
	public static function requestPermissions($id): array
	{
		// Fetch the default permissions from the config
		$requestPermissions = config('constants.request_permissions');

		// Map request types to their respective policies
		$policyMappings = config('constants.policy_mappings');

		// Check permissions dynamically
		foreach ($requestPermissions as $type => $permission) {
			if (isset($policyMappings[$type]) && checkPermission($policyMappings[$type])) {
				$requestPermissions[$type] = 'Yes';
			}
		}

		// Pass the permissions to the repo file
		return $requestPermissions;
	}

	/**
	 * Summary of getMixedRateGroups
	 * @param mixed $bookingM
	 * @param mixed $bookingT
	 * @param mixed $isAffiliate
	 * @param mixed $rateGroupWiseTourists
	 * @param mixed $rateGroupWiseTotal
	 * @param mixed $rateGroupWiseRate
	 * @param mixed $rateGroupWiseAdditionalCharge
	 * @param mixed $rateGroupWiseOldServiceCommission
	 * @param mixed $rateGroupWisePermitFeesRefunded
	 * @param mixed $rateGroupWiseNoPermitFees
	 * @return array
	 */
	public static function getMixedRateGroups(
		$bookingM,
		$bookingT,
		$isAffiliate,
		$affiliateId,
		$rateGroupWiseTourists,
		$rateGroupWiseInfants,
		$rateGroupWiseTotal,
		$rateGroupWiseRate,
		$rateGroupWiseAdditionalCharge,
		$rateGroupWiseOldServiceCommission,
		$rateGroupWisePermitFeesRefunded,
		$rateGroupWiseNoPermitFees,
		$rateGroupWisePartnerFeesType = [],
		$rateGroupWisePartnerFeesValue = [],
		$rateGroupWisePartnerFees = []
	): array {
		/* START: Check for custom rates based on date and slots */
		$slotTimeId = 0;
		$checkCustomRate = TourPackageRate::find($bookingT[0]->tour_package_rate_id);
		if ($checkCustomRate->custom_date_id) {
			$standardRateDate = SurchargeDate::find($checkCustomRate->custom_date_id);
			$slotTimeId = $standardRateDate->apply_on_slots ? $bookingM->time_id : 0;
		}

		//Check if booking is rescheduled earlier
		$checkForReschedule = TourBookingHistory::where('tour_booking_id', $bookingM->id)
			->where('operation', 'Rescheduled')
			->orderBy('id', 'DESC')
			->first();
		if ($checkForReschedule) {
			$details = json_decode($checkForReschedule->details);
			$dateFrom = $details->dateFrom ?? null;
			$dateTo = $details->dateTo ?? null;
			if ($dateFrom && $dateTo) {
				$isBlackoutDateForOldDate = isBlackoutDateEnabled($dateFrom);
				if (!$isBlackoutDateForOldDate) {
					$isBlackoutDate = false;
				} else {
					$isBlackoutDate = isBlackoutDateEnabled($bookingM->date);
				}
			}
		} else {
			$isBlackoutDate = isBlackoutDateEnabled($bookingM->date);
		}

		$affiliateIdForRate = ($isAffiliate == 'Yes' && !$isBlackoutDate) ? $affiliateId : NULL;
		$customRateDate = isCustomDateRateEnabled($bookingM->date, $bookingM->tour_package_id, $affiliateIdForRate, $slotTimeId);
		/* END */
		$tourPackageRate = TourPackageRate::where('affiliate_id', $affiliateIdForRate)
			->where('scheduling_year', date('Y', strtotime($bookingM->date)))
			->first();

		$rateGroupsQuery = DB::table('tour_package_rates AS tpr')
			->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
			->join('states AS st', 'st.id', '=', 'ct.state_id')
			->where([
				'tpr.tour_package_id' => $bookingM->tour_package_id,
				'tpr.deleted_by' => 0
			])
			->when($isAffiliate == 'Yes' && $tourPackageRate && !$isBlackoutDate, function ($query) use ($affiliateIdForRate) {
				return $query->where('tpr.affiliate_id', $affiliateIdForRate);
			}, function ($query): mixed {
				return $query->whereNull('tpr.affiliate_id');
			})
			->where('tpr.scheduling_year', date('Y', strtotime($bookingM->date)))
			->orderBy('tpr.orderby', 'asc')
			->select([
				'tpr.id',
				'tpr.rate_for',
				'tpr.rate',
				'tpr.description',
				'tpr.permit_fee',
				'tpr.partner_fee_type',
				'tpr.partner_fee_percentage',
				'tpr.partner_fee_amount',
				'tpr.additional_tax_percentage',
				'tpr.processing_charge_percentage',
				'tpr.additional_charge',
				'tpr.cash_rate',
				'tpr.cash_tax',
				'tpr.is_group_rate',
				'tpr.is_infant_rate',
				'tpr.is_cash_rate',
				'tpr.min_group_size',
				'tpr.max_group_size',
				'tp.service_commission_percentage',
				'tp.stripe_commission_percentage',
				'tpr.mapping_key'
			]);

		// Step 1: Fetch Normal and Custom Rate Groups
		$normalRateGroups = (clone $rateGroupsQuery)->whereNull('tpr.custom_date_id')->get();

		$customRateGroups = (clone $rateGroupsQuery)->when($customRateDate, fn($query) => $query->where('tpr.custom_date_id', $customRateDate), fn($query) => $query->whereNull('tpr.custom_date_id'))->get();

		// Step 2: Fetch existing booking rate groups id and mapping keys
		$rateGroupWiseIds = $bookingT->pluck('tour_package_rate_id')->toArray();

		// Step 2.5: Fetch booking's existing rate groups (even if from different period)
		// This ensures rescheduled bookings keep their original rate groups
		$bookingRateGroups = collect();
		if (!empty($rateGroupWiseIds)) {
			$bookingRateGroups = DB::table('tour_package_rates AS tpr')
				->join('tour_packages AS tp', 'tp.id', '=', 'tpr.tour_package_id')
				->whereIn('tpr.id', $rateGroupWiseIds)
				->where('tpr.deleted_by', 0)
				->select([
					'tpr.id',
					'tpr.rate_for',
					'tpr.rate',
					'tpr.description',
					'tpr.permit_fee',
					'tpr.partner_fee_type',
					'tpr.partner_fee_percentage',
					'tpr.partner_fee_amount',
					'tpr.additional_tax_percentage',
					'tpr.processing_charge_percentage',
					'tpr.additional_charge',
					'tpr.cash_rate',
					'tpr.cash_tax',
					'tpr.is_group_rate',
					'tpr.is_infant_rate',
					'tpr.is_cash_rate',
					'tpr.min_group_size',
					'tpr.max_group_size',
					'tp.service_commission_percentage',
					'tp.stripe_commission_percentage',
					'tpr.mapping_key'
				])
				->get()
				->keyBy('id');
		}

		// Step 3: Initialize Rate Groups Collection
		$rateGroups = collect();

		// Step 4: Add booking's existing rate groups first (keeps original rates)
		// Note: Use direct assignment instead of merge() to preserve numeric keys
		// merge() re-indexes numeric keys, which breaks the ID-based lookup
		$rateGroups = $bookingRateGroups;
		$existingMappingKeys = $rateGroups->pluck('mapping_key')->toArray();
		$existingRateNames = $rateGroups->pluck('rate_for')->toArray();

		// Step 5: Add remaining rates based on current date
		// If current date has custom rates, use custom rates for remaining
		// Otherwise, use normal rates for remaining
		$remainingRates = ($customRateDate && $customRateGroups->count() > 0) ? $customRateGroups : $normalRateGroups;

		foreach ($remainingRates as $rate) {
			// Skip if already added by ID
			if ($rateGroups->has($rate->id)) {
				continue;
			}

			// Group rates share the same mapping_key (differentiated by min/max group size),
			// so skip mapping_key deduplication and add by ID only
			if (!empty($rate->is_group_rate) && $rate->is_group_rate == 1) {
				$rateGroups->put($rate->id, $rate);
				continue;
			}

			// For mapping_key 0 (Cash rates), check by rate_for name to avoid duplicates
			// For other mapping_keys, check by mapping_key
			$isCashRate = empty($rate->mapping_key) || $rate->mapping_key == '0';

			if ($isCashRate) {
				// Skip if same rate name already exists
				if (!in_array($rate->rate_for, $existingRateNames)) {
					$rateGroups->put($rate->id, $rate);
					$existingRateNames[] = $rate->rate_for;
				}
			} else {
				// Skip if same mapping_key already exists
				if (!in_array($rate->mapping_key, $existingMappingKeys)) {
					$rateGroups->put($rate->id, $rate);
					$existingMappingKeys[] = $rate->mapping_key;
				}
			}
		}

		// Step 7: Process Final Rate Groups
		$paxWiseGroupRateIds = [];
		$paxWiseGroupRates = [];
		$paxWiseGroupAdditionalCharges = [];
		foreach ($rateGroups as $key => $rateGroup) {
			// Assign default percentages
			$rateGroup->affiliate_processing_percentage = (float) $bookingT[0]->nat_fees_percentage;
			$rateGroup->stripe_affiliate_percentage = $bookingT[0]->stripe_fees_percentage;

			// Assign tourist and total data
			if ($rateGroup->is_infant_rate == '1') {
				$rateGroup->tourists = !empty($rateGroupWiseInfants[$rateGroup->id]) ? $rateGroupWiseInfants[$rateGroup->id] : 0;
			} else {
				$rateGroup->tourists = !empty($rateGroupWiseTourists[$rateGroup->id]) ? $rateGroupWiseTourists[$rateGroup->id] : 0;
			}
			//$rateGroup->tourists = !empty($rateGroupWiseTourists[$rateGroup->id]) ? $rateGroupWiseTourists[$rateGroup->id] : 0;
			$rateGroup->infants = !empty($rateGroupWiseInfants[$rateGroup->id]) ? $rateGroupWiseInfants[$rateGroup->id] : 0;
			$rateGroup->total = !empty($rateGroupWiseTotal[$rateGroup->id]) ? (float)$rateGroupWiseTotal[$rateGroup->id] : 0;

			// Assign rate and additional charge data
			if (isset($rateGroupWiseRate[$rateGroup->id]) && !empty($rateGroupWiseRate[$rateGroup->id])) {
				$rateGroup->rate = $rateGroupWiseRate[$rateGroup->id];
			}
			if (isset($rateGroupWiseAdditionalCharge[$rateGroup->id]) && !empty($rateGroupWiseAdditionalCharge[$rateGroup->id])) {
				$rateGroup->additional_charge = $rateGroupWiseAdditionalCharge[$rateGroup->id];
			}

			// Old processing charge percentage
			$rateGroup->old_processing_charge_percentage = !empty($rateGroupWiseOldServiceCommission[$rateGroup->id]) && $rateGroupWiseOldServiceCommission[$rateGroup->id] > 0 && $rateGroup->rate > 0
				? (float) $rateGroupWiseOldServiceCommission[$rateGroup->id]
				: 0;

			// Permit fees handling - check for effective permit fee from existing refunds
			$rateGroup->permitfees_refunded = !empty($rateGroupWisePermitFeesRefunded[$rateGroup->id]) ? (float) $rateGroupWisePermitFeesRefunded[$rateGroup->id] : 0;
			if (!empty($rateGroupWiseNoPermitFees[$rateGroup->id]) && $rateGroupWiseNoPermitFees[$rateGroup->id] == 1) {
				$rateGroup->permit_fee = 0;
			} else {
				// Check if this rate group has existing permit fee refunds and calculate effective permit fee
				$effectivePermitFee = $rateGroup->permit_fee; // Default to original permit fee

				if ($rateGroup->tourists > 0) { // Only check for existing rate groups with people
					$existingTransaction = DB::table('tour_bookings_transact')
						->where('tour_booking_id', $bookingM->id)
						->where('tour_package_rate_id', $rateGroup->id)
						->where('deleted_by', 0)
						->first(['refund_permitfee_status']);

					if ($existingTransaction && $existingTransaction->refund_permitfee_status != '') {
						$refundStatus = json_decode($existingTransaction->refund_permitfee_status);

						if (!empty($refundStatus)) {
							foreach ($refundStatus as $status) {
								if (isset($status->status) && $status->status == 'Y') {
									if (isset($status->new_permit_fee)) {
										$effectivePermitFee = $status->new_permit_fee; // Use partial refund amount (e.g., $15)
									} else {
										$effectivePermitFee = 0; // Full refund case
									}
									break; // All people in this rate group should use same effective permit fee
								}
							}
						}
					}
				}

				$rateGroup->permit_fee = $effectivePermitFee;
			}

			// Group rate handling
			if ($rateGroup->is_group_rate == '1' || $bookingM->is_group_booking == '1') {
				if ($rateGroup->min_group_size > 0 && $rateGroup->max_group_size > 0 && $rateGroup->min_group_size <= $rateGroup->max_group_size) {
					for ($i = $rateGroup->min_group_size; $i <= $rateGroup->max_group_size; $i++) {
						$paxWiseGroupRateIds[$i] = $rateGroup->id;
						$paxWiseGroupRates[$i] = (float)$rateGroup->rate;
						$paxWiseGroupAdditionalCharges[$i] = (float)$rateGroup->additional_charge;
					}
				}
			}

			// Convert permit_fee to string for consistency
			$rateGroup->permit_fee = (string) $rateGroup->permit_fee;

			//Assign partner fees data
			if (isset($rateGroupWisePartnerFeesType[$rateGroup->id]) && !empty($rateGroupWisePartnerFeesType[$rateGroup->id])) {
				$rateGroup->partner_fee_type = $rateGroupWisePartnerFeesType[$rateGroup->id];
			}
			if (isset($rateGroupWisePartnerFeesValue[$rateGroup->id]) && !empty($rateGroupWisePartnerFeesValue[$rateGroup->id])) {
				$rateGroup->partner_fee_percentage = $rateGroupWisePartnerFeesValue[$rateGroup->id];
			}
			if (isset($rateGroupWisePartnerFees[$rateGroup->id]) && !empty($rateGroupWisePartnerFees[$rateGroup->id])) {
				$rateGroup->partner_fee_amount = $rateGroupWisePartnerFees[$rateGroup->id];
			}
			// Reassign processed rate group back to collection
			$rateGroups[$key] = $rateGroup;
		}

		$formattedRates = [];
		foreach ($rateGroups as $key => $rateGroup) {
			$formattedRates[] = $rateGroup;
		}

		return [
			'rateGroups' => $formattedRates,
			'paxWiseGroupRateIds' => $paxWiseGroupRateIds,
			'paxWiseGroupRates' => $paxWiseGroupRates,
			'paxWiseGroupAdditionalCharges' => $paxWiseGroupAdditionalCharges
		];
	}

	/**
	 * Summary of managerRequestEnable
	 * @param mixed $id
	 * @return array
	 */
	public static function managerRequestEnable($id): array
	{
		// Fetch the default permissions from the config
		$requestPermissions = config('constants.request_permissions');

		// Map request types to their respective policies
		$policyMappings = config('constants.policy_mappings');

		// Check permissions dynamically
		$mangerRequest = hasSetting('MANAGER_REQUEST');
		foreach ($requestPermissions as $type => $permission) {
			if (isset($policyMappings[$type]) && checkPermission($policyMappings[$type])) {
				$requestPermissions[$type] = 'Yes';
			} else {
				if ($mangerRequest && userType() == 'Tour Operator') {
					$requestPermissions[$type] = 'Yes';
				}
			}
		}

		// Pass the permissions to hide or show the feature on the repo file
		return $requestPermissions;
	}

	/**
	 * Summary of requestPermissions
	 * @param mixed $id
	 * @return array
	 */
	public static function fieldRequestPermissions($id): array
	{
		// Fetch the default permissions from the config
		$requestPermissions = config('constants.request_permissions');

		// Map request types to their respective policies
		$policyMappings = config('constants.policy_mappings');

		// Check permissions dynamically
		foreach ($requestPermissions as $type => $permission) {
			if (isset($policyMappings[$type]) && checkPermission($policyMappings[$type])) {
				$requestPermissions[$type] = 'Yes';
			}
		}

		$tourBooking = TourBooking::where('id', $id)->first();
		$qryBookings = 'FIND_IN_SET("' . $id . '" ,bookings)'; //This is to check request from POS/Backend exist
		$approvedRequestsField = BookingRequest::where('staff_id', Auth::user()->id)
			->where('order_id', $tourBooking->group_id)
			->where('status', 'Approved')
			->whereIn('request_type', array_keys($requestPermissions))
			->whereRaw($qryBookings)
			->get(['request_type', 'details']);

		$approvedFields = [];
		foreach ($approvedRequestsField  as $key => $approvedField) {
			if (isset($approvedField->details)) {
				$details = json_decode($approvedField->details);
				$approvedRequestsField[$key]->field_type = $details->field_type;
				$approvedFields[] = $approvedRequestsField[$key]->field_type;
			}
		}

		// Pass the field wise permissions to the repo file
		return $approvedFields;
	}


	/**
	 * Updates the Stripe payment intent with metadata related to a booking
	 * @param string $paymentIntentId 
	 * @param array  $data          
	 * 
	 * @return \Stripe\PaymentIntent The updated Stripe payment intent object.
	 */
	public static function updateCardReaderIntent($paymentIntentId, $data)
	{
		/* prepare stripe metadata */
		$stripeMetadata = [
			'description' => 'Booking Id: ' . $data['booking_id'] .
				"\nTour Package: " . $data['tour_package'] .
				"\nTour Date: " . $data['date'] .
				"\nTour Time: " . $data['time_from'] .
				"\nTourists: " . $data['tourists'] .
				"\nEmail: " . $data['customer_email'],
			'metadata' => [
				'booking_id' => $data['booking_id'],
				'tour_package' => $data['tour_package'],
				'tour_date' => $data['date'],
				'tour_time' => $data['time_from'],
				'tourists' => $data['tourists'],
				'split_payment' => (isset($data['split_value']) && !empty($data['split_value']) && $data['split_value'] != 0) ? 1 : 0,
				'from' => (isset($data['from']) && !empty($data['from'])) ? "Web Manual Card" : "Web Card Reader",
				'tenant_id' => tenant()->id,
			],
		];

		/* additional params for change people */
		if (isset($data['change_people']) && $data['change_people'] == 1) {
			$stripeMetadata['metadata'] = array_merge($stripeMetadata['metadata'], [
				'added_people_texts' => $data['addedpeoplesetexts'],
				'deleted_people_texts' => $data['deletedpeoplestexts'],
				'added_tourists' => $data['addedtourists'] ?? 0,
				'deleted_tourists' => $data['deletedtourits'] ?? 0,
				'discount_percentage' => $data['discount_percentage'],
				'discount_value' => $data['discount2_value'],
				'amount' => $data['amount'] ?? 0.00,
				'platform_fee' => $data['fee'] ?? 0.00,
				'partner_fee' => $data['partner_fee'] ?? 0.00,
				'payout_amount' => $data['payout_amount'] ?? 0.00,
				'refund_total' => $data['refundtotal'] ?? 0,
				'payment_total' => $data['paymenttotal'] ?? 0,
			]);
		}

		/* additional params for custom charge, edit_discount*/
		if ((isset($data['custom_charge']) && $data['custom_charge'] == 1) || (isset($data['edit_discount']) && $data['edit_discount'] == 1)) {
			$stripeMetadata['metadata'] = array_merge($stripeMetadata['metadata'], [
				'currency' => currencyFormation(code: true),
				'amount' => $data['amount'],
				'statement_descriptor' => $data['statement_descriptor'],
				'confirm' => $data['confirm'],
				'application_fee_amount' => $data['application_fee_amount'],
			]);
		}

		/* additional params for create bookings */
		if ((isset($data['create_booking_card']) && $data['create_booking_card'] == 1)) {
			$stripeMetadata['metadata'] = array_merge($stripeMetadata['metadata'], [
				'bookings' => json_encode(['BookingId' => $data['booking_id']]),
			]);
		}

		$updateParams = $stripeMetadata;
		$intentData = app(PaymentGatewayInterface::class)->updatePayment($paymentIntentId, $updateParams);

		$retrieveOptions = ['expand' => ['charges.data.transfer']];
		$paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);

		if (isset($paymentIntent->charges->data[0]->transfer->destination_payment)) {
			$chargeParams = $stripeMetadata;
			app(PaymentGatewayInterface::class)->updateConnectedPayment(
				$paymentIntent->charges->data[0]->transfer->destination_payment,
				$chargeParams,
				$paymentIntent->charges->data[0]->transfer->destination
			);
		}

		return $intentData;
	}

	public static function getCustomerStripeId($customerId)
	{
		$customer = Customer::find($customerId);
		if ($customer->stripe_id == null) {
			$stripeCustomer = StripeCustomer::create([
				'name' => $customer->name,
				'email' => $customer->email,
				'phone' => $customer->phone
			]);
			$customer->stripe_id = $stripeCustomer->id;
			$customer->save();
		}
		return $customer->stripe_id;
	}
	/**
	 * Calculate the total booking amount including fees, commission, and discounts.
	 *
	 * @param Request $request
	 * @param object $bookingT
	 * @param object $tourBooking
	 * @param int $oldBookingId
	 * @param bool $isGroupBooking
	 * @return array
	 */
	public static function calculateTotalAmount($request, $bookingT, $tourBooking, $oldBookingId, $isGroupBooking)
	{
		$total = 0;
		$serviceCommissionTotal = 0;
		$partnerFeesTotal = 0;

		$natFeesPercentage = $bookingT[0]->nat_fees_percentage;

		foreach ($bookingT as $tbTransaction) {
			$rate = $tbTransaction->rate;
			$permitFee = $tbTransaction->permit_fee;
			$partnerFees = $tbTransaction->partner_fees_amount;
			$additionalCharge = $tbTransaction->additional_charge;
			$tourists = ($tbTransaction->is_infant_rate == 1) ? $tbTransaction->infants : $tbTransaction->tourists;

			$procFee = ($request->bookingID < $oldBookingId) ? 2.9 : $tbTransaction->packageRate->processing_charge_percentage;

			if (
				!$request->has('discount_type') || empty($request->discount_type) ||
				(empty($request->discount_fixed_money) && empty($request->discount_percentage)) &&
				$tourBooking->discount2_value
			) {
				$serviceCommissionTotal += self::calculateServiceCommission(
					$rate,
					$additionalCharge,
					$permitFee,
					$natFeesPercentage,
					$tourists,
					$isGroupBooking,
					$partnerFees
				);
			}

			$subTotal = self::calculateSubTotal($rate, $permitFee, $additionalCharge, $tourists, $isGroupBooking, $partnerFees, $tbTransaction->refund_permitfee_status);
			$total += $subTotal + ($subTotal * ($procFee / 100));
			$partnerFeesTotal += $partnerFees * $tourists;
		}

		return [
			'total' => $total,
			'serviceCommissionTotal' => $serviceCommissionTotal,
			'partnerFeesTotal' => $partnerFeesTotal,
			'payoutAmount' => ($partnerFeesTotal > 0) ? $total - $partnerFeesTotal : 0.00,
		];
	}

	/**
	 * Calculate service commission per transaction.
	 *
	 * @param float $rate
	 * @param float $additionalCharge
	 * @param float $permitFee
	 * @param float $natFeesPercentage
	 * @param int $tourists
	 * @param bool $isGroupBooking
	 * @param float $partnerFees
	 * @return float
	 */
	public static function calculateServiceCommission($rate, $additionalCharge, $permitFee, $natFeesPercentage, $tourists, $isGroupBooking, $partnerFees = 0)
	{
		if ($isGroupBooking) {
			return roundout((($rate + $additionalCharge + $partnerFees) * $natFeesPercentage / 100), 2);
		}

		return roundout((($rate + $additionalCharge + $permitFee + $partnerFees) * $natFeesPercentage / 100), 2) * $tourists;
	}

	/**
	 * Calculate the subtotal for a booking.
	 *
	 * `$permitFee` is the transact row's single CURRENT effective permit fee. It only gets
	 * updated to a new (reduced) value when EVERY tourist in that row was reduced together
	 * (see TourBookingRepository::editBooking()'s permit-fee-update branch) -- when only some
	 * tourists were reduced (a mixed row), the column is deliberately left at the pre-reduction
	 * fee, and `calculatePendingPermitFeeAdjustment()` supplies the gap for the tourists who
	 * were reduced. When every tourist's reduction IS already reflected in the column, that
	 * gap is zero, so this never double-subtracts an already-applied reduction.
	 *
	 * @param float $rate
	 * @param float $permitFee
	 * @param float $additionalCharge
	 * @param int $tourists
	 * @param bool $isGroupBooking
	 * @param float $partnerFees
	 * @param string|array|null $refundPermitFeeStatus
	 * @return float
	 */
	public static function calculateSubTotal($rate, $permitFee, $additionalCharge, $tourists, $isGroupBooking, $partnerFees = 0, $refundPermitFeeStatus = null)
	{
		if ($isGroupBooking) {
			return $rate + $additionalCharge + $partnerFees;
		}

		$pendingAdjustment = self::calculatePendingPermitFeeAdjustment($refundPermitFeeStatus, $permitFee);

		return ($rate + $permitFee + $additionalCharge + $partnerFees) * $tourists - $pendingAdjustment;
	}

	/**
	 * How much the transact row's aggregate `permit_fee` still overstates the true fee for
	 * tourists who individually had a permit-fee refund applied to them.
	 *
	 * `permit_fee` is one value per transact row (per rate group), but a refund can be applied
	 * to a subset of that row's tourists (their entries in `refund_permitfee_status` get
	 * `new_permit_fee` set to their reduced fee, or it's omitted/absent on legacy full-refund
	 * entries, meaning reduced to 0). If ALL of the row's tourists end up reduced, the column is
	 * updated to match and every entry's gap is 0. If only SOME are, the column stays at the
	 * pre-reduction fee, and this returns the sum of (current column value - each such
	 * tourist's true fee) so the subtotal isn't overstated for them. Entries still pending
	 * addition (`is_pax_added`) are a separate, not-yet-priced tourist and are skipped.
	 *
	 * @param string|array|null $refundPermitFeeStatus
	 * @param float $currentPermitFee
	 * @return float
	 */
	public static function calculatePendingPermitFeeAdjustment($refundPermitFeeStatus, $currentPermitFee): float
	{
		if (empty($refundPermitFeeStatus)) {
			return 0.0;
		}

		$refundData = is_string($refundPermitFeeStatus) ? json_decode($refundPermitFeeStatus, true) : $refundPermitFeeStatus;
		if (empty($refundData)) {
			return 0.0;
		}

		$pending = 0.0;
		foreach ($refundData as $entry) {
			$entry = (array) $entry;
			if (($entry['status'] ?? null) !== 'Y' || isset($entry['is_pax_added'])) {
				continue;
			}
			$trueFee = isset($entry['new_permit_fee']) ? (float) $entry['new_permit_fee'] : 0.0;
			$pending += ((float) $currentPermitFee - $trueFee);
		}

		return $pending;
	}

	/**
	 * Calculate the total cost for a tour package booking.
	 *
	 * @param Request $request
	 * @param float $stripeFeesPercentage
	 * @param float $natFeesPercentage
	 * @param object $info Tour package information.
	 * @param string $isGroupRate Group rate.
	 * @return array The calculated total, service commission, and fees.
	 * @throws Exception If unable to add the tour package.
	 */
	public static function calculateTotalForCreateBooking(Request $request, float $stripeFeesPercentage, float $natFeesPercentage, object $info, string $isGroupRate): array
	{
		// Initialize variables
		$addonPrice = $addonFee = $addonTotal = $partnerFee = $payoutAmount = 0;
		$paxAddonPrice = $paxAddonFee = $paxAddonTotal = 0;
		$otherAddonPrice = $otherAddonFee = $otherAddonTotal = 0;
		$customChargeFee = $tipAmount = $tipFees = $customChargeAmount = 0;
		$totalCustomCharge = $totalTipAmount = 0;

		// If a split payment value is provided, use it directly
		if ($request->split_value) {
			$total = $request->split_value;
			$serviceCommission = roundout($total * $stripeFeesPercentage / 100, 2);
		} else {
			$rateGrpIds = [];
			$permitfeeAr = [];

			// Affiliates cannot set the permit fee: force the rate's value (server source of truth).
			// Tour Operators/Admins may legitimately waive the fee, so keep their posted value.
			$isAffiliate = (userType() === 'Affiliate');
			$ratePermitFeeMap = DB::table('tour_package_rates')
				->whereIn('id', $request->rate_group ?? [])
				->pluck('permit_fee', 'id');

			// Extract rate group IDs and permit fees
			foreach ($request->tourists as $index => $touristCount) {
				if ($touristCount) {
					$rateGrpIds[$index] = $request->rate_group[$index];
					$permitfeeAr[$index] = $isAffiliate
						? (float) ($ratePermitFeeMap[$request->rate_group[$index]] ?? 0)
						: ($request->permit_fee[$index + 1] ?? 0);
				}
			}

			if (empty($rateGrpIds)) {
				throw new Exception("No rate groups found for the booking.");
			}
			$isPartnerPackage = $info->is_partner_package;
			$parterCommissionFields = '';
			if ($isPartnerPackage) {
				$parterCommissionFields = ",partner_fee_percentage,partner_fee_type,partner_fee_amount,margin";
			}

			// Fetch rate group details from the database
			$rateGroups = DB::select("
                SELECT id, rate_for, rate, additional_tax_percentage, 
                       processing_charge_percentage, additional_charge 
					   " . $parterCommissionFields . "
                FROM tour_package_rates
                WHERE deleted_by = 0
                AND id IN (" . implode(',', $rateGrpIds) . ")
                ORDER BY orderby ASC
            ");

			$total = $discountAmt = $serviceCommissionTotal = $subtt = $partnerFeesTotal = $affiliateFeesTotal = $payoutAmount = 0;

			// Calculate pricing for each tourist group
			foreach ($request->tourists as $index => $touristCount) {
				if (!$touristCount) continue;

				// Find the corresponding rate group
				$rateGroup = collect($rateGroups)->firstWhere('id', $request->rate_group[$index]);

				if (!$rateGroup) {
					DB::rollBack();
					throw new Exception('Unable to add the tour package.');
				}

				//Fetch partner fee type and value
				$partnerFeeType = ($isPartnerPackage == 1 && $rateGroup->partner_fee_type != '') ? $rateGroup->partner_fee_type : '';
				$partnerFeesValue = ($isPartnerPackage == 1 && $rateGroup->rate > 0 && $rateGroup->partner_fee_percentage > 0) ? $rateGroup->partner_fee_percentage : 0;
				$affiliateFeesValue = ($isPartnerPackage == 1 && $request->affiliatecust > 0 && $rateGroup->margin > 0) ? $rateGroup->margin : 0;
				$partnerFees = ($isPartnerPackage == 1 && $rateGroup->rate > 0) ? $rateGroup->partner_fee_amount : 0;

				// Calculate group total based on pricing and rate type
				if ($isGroupRate) {
					$groupTotal = $rateGroup->rate + $rateGroup->additional_charge + $partnerFees;
					if ($isPartnerPackage == 1) {
						$payoutAmount += ($rateGroup->rate + $rateGroup->additional_charge) * $touristCount;
					}
				} else {
					$groupTotal = $touristCount * (
						$rateGroup->rate +
						$rateGroup->additional_charge +
						($permitfeeAr[$index] ?? 0) + $partnerFees
					);
					if ($isPartnerPackage == 1) {
						$payoutAmount += $touristCount * ($rateGroup->rate +
							$rateGroup->additional_charge +
							($permitfeeAr[$index] ?? 0));
					}
				}

				$subtt += $groupTotal;
				$partnerFeesTotal += $partnerFees * $touristCount;
				// Calculate service commission only if no discount is applied. See
				// DiscountService::hasEffectiveDiscount() — a stray checked discount radio with
				// no value must not skip the correct per-person-rounded-then-summed commission.
				if (!DiscountService::hasEffectiveDiscount($request)) {
					if ($isGroupRate) {
						$serviceCommissionTotal = roundout(($rateGroup->rate + $rateGroup->additional_charge + $partnerFees + $affiliateFeesValue) * $natFeesPercentage / 100, 2);
					} else {
						$serviceCommissionPax = roundout(($rateGroup->rate + $rateGroup->additional_charge + ($permitfeeAr[$index] ?? 0) + $partnerFees + $affiliateFeesValue) * $natFeesPercentage / 100, 2);
						$serviceCommissionTotal += $touristCount * $serviceCommissionPax;
					}
				}

				// Apply processing charge percentage
				$groupTotal += ($groupTotal * $rateGroup->processing_charge_percentage) / 100;
				$total += $groupTotal;
			}

			// Apply discount if applicable
			if (DiscountService::hasEffectiveDiscount($request)) {
				$discountAmt = self::calculateDiscountForCreateBooking($request, $total, $info);
				$total -= $discountAmt;
			}

			// Determine the final service commission
			$serviceCommission = DiscountService::hasEffectiveDiscount($request)
				? roundout($total * $natFeesPercentage / 100, 2)
				: $serviceCommissionTotal;

			// Final rounding
			$total += $serviceCommission;
			$total = round($total, 2);
			$partnerFee = round($partnerFeesTotal, 2);
			$payoutAmount = round($payoutAmount, 2);

			// Process add-ons if provided
			if (!empty(trim($request->custom_fields))) {
				$addons = json_decode(trim($request->custom_fields), true);
				foreach ($addons['fields'] as $addon) {
					if ($addon['priceInfo']['enabled'] === 'true') {
						$addonPrice += $addon['priceInfo']['price'];
						$addonFee += $addon['priceInfo']['fee'];
						$addonTotal += $addon['priceInfo']['price'] + $addon['priceInfo']['fee'];
					}
				}
			}

			if (!empty(trim($request->pax_addons_data))) {
				$addons = json_decode(trim($request->pax_addons_data), true);
				$paxAddons = $addons['fields'] ?? [];

				foreach ($paxAddons as $rateId => $fields) {
					if (!isset($fields['addons'])) {
						continue; // skip if missing addons
					}

					foreach ($fields['addons'] as $fieldData) {
						foreach ($fieldData as $fieldId => $field) {
							if ($fieldId === 'uid' || !is_array($field)) continue; // skip uid key

							// Always compute — calculateFieldAmounts() safely returns zero when
							// neither the field nor its options are priced, AND recurses into
							// $field['children'] to pick up a priced CHILD even when the parent
							// itself carries no price (e.g. a free "Equipment Rental Needed?"
							// yes/no parent with a priced "Camera Rental Type" dropdown child).
							// Gating on the PARENT's own price flags before calling this skipped
							// that recursion entirely, silently dropping the child from the
							// PaymentIntent amount even though it's stored/charged correctly.
							$amounts = AddonBookingService::calculateFieldAmounts($field, $request->payment_method, 0, $natFeesPercentage);

							$paxAddonPrice += $amounts['price'];
							$paxAddonFee   += $amounts['fee'];
							$paxAddonTotal += $amounts['total'];
						}
					}
				}
			}

			// Process one_time / price_per_unit add-ons
			if (!empty(trim($request->addons_data ?? ''))) {
				$addons = json_decode(trim($request->addons_data), true);
				$addonsGroups = $addons['fields']['addons'] ?? [];
				foreach ($addonsGroups as $groupFields) {
					foreach ($groupFields as $fieldId => $fieldData) {
						if ($fieldId === 'uid' || !is_array($fieldData)) continue; // skip uid key

						// See identical comment above — always compute so a priced CHILD under
						// an unpriced parent (e.g. Camera Rental Type under Equipment Rental
						// Needed?) is never silently skipped.
						$amounts = AddonBookingService::calculateFieldAmounts($fieldData, $request->payment_method, 0, $natFeesPercentage);
						$otherAddonPrice += $amounts['price'];
						$otherAddonFee   += $amounts['fee'];
						$otherAddonTotal += $amounts['total'];
					}
				}
			}

			// Process custom charge if provided
			if (!empty($request->custom_charge_data)) {
				$customData = json_decode($request->custom_charge_data);
				$customChargeAmount = (float) $customData->custom_data->custom_charge_amount;
				if ($customData->custom_data->taxable) {
					$customChargeFee = roundout($customChargeAmount * $natFeesPercentage / 100, 2);
				}
				$totalCustomCharge = $customChargeAmount + $customChargeFee;
			}

			// Process tip data if provided
			if ($request->tip_data && trim($request->tip_data) != "") {
				$tipDetails = json_decode($request->tip_data, true);
				$tipAmount = ($tipDetails['tip_amount'] > 0) ? $tipDetails['tip_amount'] : 0;
				$tipFees = $tipDetails['tip_fees'];
				$totalTipAmount = round($tipAmount + $tipFees, 2);
			}
		}

		// Calculate the final Stripe charge
		$stripeTotal = $total + $totalCustomCharge + $addonTotal + $paxAddonTotal + $otherAddonTotal + $totalTipAmount;
		$stripeFee = $serviceCommission + $customChargeFee + $addonFee + $paxAddonFee + $otherAddonFee + $tipFees;

		return compact('total', 'stripeTotal', 'stripeFee', 'serviceCommission', 'partnerFee', 'payoutAmount');
	}
	/**
	 * Calculate discount based on type.
	 *
	 * @param Request $request
	 * @param float $total
	 * @param object $info
	 * @return float
	 */
	public static function calculateDiscountForCreateBooking(Request $request, float $total, object $info): float
	{
		switch ($request->discount_type) {
			case 'Percentage':
				$discountPercentage = (float) ($request->discount_percentage ?? 0);
				return round(($total * $discountPercentage) / 100, 2);
			case 'Fixed Money':
				$discountFixedMoney = (float) ($request->discount_fixed_money ?? 0);
				return round($discountFixedMoney, 2);
			case 'Promo Code':
				if ($request->is_applied) {
					$promoDetail = TourPromotion::where('code', $request->discount_promo_code)
						->where('tour_package_id', $info->tour_package_id)
						->first();

					if ($promoDetail) {
						return ($promoDetail->discount_value_type === 'Percent')
							? round(($total * $promoDetail->discount_value) / 100, 2)
							: round($promoDetail->discount_value, 2);
					}
				}
				break;
		}

		return 0;
	}
	/**
	 * Dispatch a booking notification.
	 *
	 * @param object $tourBooking
	 * @param object $request
	 * @param float $total
	 * @param string $paymentType
	 */
	public static function dispatchBookingNotification($tourBooking, $request, float $total, string $paymentType): void
	{
		// Determine background color class using the same logic as backgroundColourClass method
		$backgroundClass = self::backgroundColourClass($tourBooking);

		$booking = [
			'id' => $tourBooking->id,
			'prefixedId' => bookingPrefix($tourBooking->id),
			'cust_name' => $tourBooking->Name,
			'cust_id' => $tourBooking->customer_id,
			'pkg_name' => $tourBooking->package_name,
			'paid' => $request->split_value ?? $total,
			'total' => $total,
			'tour_d_t' => $tourBooking->Date . ' ' . $tourBooking->StartTime,
			'booking_d_t' => $tourBooking->created_at,
			't_groups' => self::getTourGroups($request->bookingID),
			'tour_operator_id' => $tourBooking->tour_operator_id,
			'is_affiliate' => $tourBooking->is_affiliate ?? 'No',
			'notif_for' => 'admin',
			'date' => $tourBooking->Date,
			'time_from' => $tourBooking->StartTime,
			'time_to' => $tourBooking->EndTime,
			'package_has_slots' => $tourBooking->package_has_slots,
			'background_colour_class' => $backgroundClass,
			'payment_method' => $paymentType,
			'broadcaster_user_id' => Auth::id(),
			'env' => config('app.env'),
			'invoiceStatus' => self::getInvoiceStatus($tourBooking->id),
			'BookingTotal' => $request->split_value ?? $total,
			'RefundAmount' => 0,
			'payment_total' => $request->split_value ?? $total,
			'paid_split' => $request->split_value ?? $total,
			'domain' => url('/')
		];

		BookingNew::dispatch($booking);
		$booking['notif_for'] = 'Tour Operator';
		BookingNew::dispatch($booking);
	}
	/**
	 * Prepare booking data for event dispatch and notify relevant users.
	 *
	 * @param mixed $booking        The created booking object.
	 * @param object $request        The request containing booking details.
	 * @param object $affiliate      The affiliate/customer details.
	 * @param object $info           The tour package details.
	 * @param float  $seatsDiff      The seat difference calculation.
	 * @param float  $updateTotal    The updated total amount if groupTotal is unavailable.
	 * @param float|null $groupTotal The total amount for the group booking.
	 * @param string|null $tGroupsChanged Any changes in tour groups.
	 */
	public static function dispatchAffiliateBookingNotification(
		mixed $booking,
		object $request,
		object $affiliate,
		object $info,
		float $seatsDiff,
		float $updateTotal,
		?float $groupTotal = null,
		?string $tGroupsChanged = ''
	): void {
		$totalAmount = $groupTotal ?? $updateTotal;

		// Prepare booking event data
		$bookingData = [
			'id' => $booking->id,
			'group_id' => $booking->group_id,
			'cust_name' => $affiliate->name,
			'cust_id' => $request->affiliate_id,
			'pkg_id' => $info->tour_package_id,
			'pkg_name' => $info->tour_package,
			'tour_slot_id' => $request->tour_slot_id,
			'tour_d_t' => $info->tour_d_t,
			'tourists' => $request->seats,
			'seats_diff' => $seatsDiff,
			't_groups' => $tGroupsChanged ?? '',
			'tour_operator_id' => $info->tour_operator_id,
			'booking_d_t' => $booking->created_at,
			'paid' => ($request->payment_method === 'Cash') ? $totalAmount : 0,
			'total' => $totalAmount,
			'notif_for' => 'admin',
			'is_affiliate' => 'Yes',
			'date' => $info->date,
			'time_from' => $info->time_from,
			'time_to' => $info->time_to,
			'seats' => $info->seats,
			'booked_seats' => $info->booked_seats,
			'broadcaster_user_id' => Auth::id(),
			'env' => config('app.env'),
			'paid_split' => null,
			'BookingTotal' => $totalAmount,
			'payment_total' => $totalAmount,
			'domain' => url('/'),
		];

		// Dispatch event for Admin
		BookingAffiliate::dispatch($bookingData);

		// Dispatch event for Tour Operator
		$bookingData['notif_for'] = 'Tour Operator';
		BookingAffiliate::dispatch($bookingData);
	}

	/**
	 * Fetch and format tour groups for a booking.
	 *
	 * @param int $bookingId
	 * @return string
	 */
	public static function getTourGroups(int $bookingId): string
	{
		$tGroups = DB::select(
			"SELECT GROUP_CONCAT( CONCAT(LEFT(tpr.rate_for, 1), ':', tbt.tourists,'^',tpr.rate_for) ORDER BY age_from DESC, age_to DESC SEPARATOR ' | ' ) AS t_groups 
            FROM tour_bookings_transact AS tbt 
            INNER JOIN tour_package_rates AS tpr ON tpr.id = tbt.tour_package_rate_id 
            WHERE tbt.deleted_by = 0 AND tbt.tour_booking_id = ?",
			[$bookingId]
		);

		return self::formatTourGroups($tGroups);
	}

	/**
	 * Format tour groups with custom naming.
	 *
	 * @param array $tGroups
	 * @return string
	 */
	private static function formatTourGroups(array $tGroups): string
	{
		$tGroupsChanged = '';
		if (isset($tGroups[0]->t_groups) && !empty($tGroups[0]->t_groups)) {
			$groupsArray = explode('|', $tGroups[0]->t_groups);
			foreach ($groupsArray as $key => $value) {
				$rateGroupWithTourists = explode('^', $value);
				if (!empty($rateGroupWithTourists)) {
					if (Str::contains($value, "Military Teachers")) {
						$tGroupsChanged .= Str::contains($value, "Adults") ? "AM" : "CM";
						$tGroupsChanged .= substr($rateGroupWithTourists[0], 2);
					} else {
						$tGroupsChanged .= $rateGroupWithTourists[0];
					}
					if ($key !== array_key_last($groupsArray)) {
						$tGroupsChanged .= " | ";
					}
				}
			}
		}
		return $tGroupsChanged;
	}

	/**
	 * Fetch invoice status for a booking.
	 *
	 * @param int $bookingId
	 * @return string
	 */
	public static function getInvoiceStatus(int $bookingId): string
	{
		$invoiced = DB::table('invoices AS i')
			->join('invoice_bookings AS ib', 'ib.invoice_id', '=', 'i.id')
			->where('ib.tour_booking_id', $bookingId)
			->first(['status']);

		return $invoiced->status ?? '0';
	}
	/**
	 * Calculates the total booking amount and prepares transactions for tourists for create booking.
	 *
	 * @param Request $request The booking request.
	 * @param array $rateGroups The available rate groups.
	 * @param float $natFeesPercentage National fee percentage.
	 * @param float $stripeFeesPercentage Stripe fee percentage.
	 * @param int $isGroupRate Indicates if the booking is a group rate.
	 * @param array $permitfeeAr Array of permit fees per rate group.
	 * @param string $userType The user type making the booking.
	 * @param boolean $isCart check requested using cart or not.
	 * @param int $isPartnerPackage Indicates if the package is partner package.
	 * @param int $isCashPrice Indicates if the cash price is enabled.
	 * @return array Contains total amount, service commission total, and transactions.
	 * @throws Exception If rate group is not found.
	 */
	public static function calculateBooking(
		$request,
		$rateGroups,
		$natFeesPercentage,
		$stripeFeesPercentage,
		$isGroupRate,
		$permitfeeAr,
		$userType,
		$isCart = false,
		$isPartnerPackage = 0,
		$isCashPrice = 0
	): array {
		$total = $subtt = $serviceCommissionTotal = $partnerFeesTotal = $affiliateFeesTotal = 0;
		$cashSubtt = $cashTotal = $cashServiceCommissionTotal = 0;
		$tbTransact = $touristGroups = $touristGroupsForCart = $tbTransaction = [];
		$isCashFees = cashFees();

		for ($a = 0, $ac = count($request->tourists); $a < $ac; $a++) {
			if (!$request->tourists[$a])
				continue;

			$tourists = $request->tourists[$a];
			$groupTotal = 0;
			$partnerFees = 0;
			for ($b = 0, $bc = count($rateGroups); $b < $bc; $b++) {
				if ($request->rate_group[$a] == $rateGroups[$b]->id) {
					$touristGroups[] = $rateGroups[$b]->rate_for[0] . ':' . $tourists;
					$touristGroupsForCart[] = $rateGroups[$b]->rate_for;
					break;
				}
			}

			if ($b >= $bc) { // rate group not found
				DB::rollBack();
				throw new Exception('Unable to book the tour package.');
			}

			$isInfantRate = $rateGroups[$b]->is_infant_rate;
			$isCashRate = $rateGroups[$b]->is_cash_rate;
			$infants = ($isInfantRate == 1) ? $request->tourists[$a] : 0;
			$partnerFeeType = ($isPartnerPackage == 1  && $rateGroups[$b]->partner_fee_type != '') ? $rateGroups[$b]->partner_fee_type : '';
			$partnerFeesValue = ($isPartnerPackage == 1  && $rateGroups[$b]->partner_fee_percentage > 0) ? $rateGroups[$b]->partner_fee_percentage : 0;
			$partnerFees = ($isPartnerPackage == 1) ? $rateGroups[$b]->partner_fee_amount : 0;

			// Calculate total based on group rate or individual rate
			$isCashPriceApplied = ($isCashPrice == 1 && $isCashRate == 1 && $request->payment_method == 'Cash' && !$isCart);
			$rate = ($isCashPriceApplied && $rateGroups[$b]->cash_rate > 0) ? $rateGroups[$b]->cash_rate : $rateGroups[$b]->rate;
			$permitFee =  $permitfeeAr[$b];
			$additionalCharge = ($isCashPriceApplied && $rateGroups[$b]->cash_rate > 0) ? $rateGroups[$b]->cash_tax : $rateGroups[$b]->additional_charge;
			$cashRate = ($isCashPrice == 1 && $isCashRate == 1 && $rateGroups[$b]->cash_rate > 0) ? $rateGroups[$b]->cash_rate : $rateGroups[$b]->rate;
			$cashAdditionalCharge = ($isCashPrice == 1 && $isCashRate == 1 && $rateGroups[$b]->cash_rate > 0) ? $rateGroups[$b]->cash_tax : $rateGroups[$b]->additional_charge;

			if ($isGroupRate == '1') {
				$groupTotal = $rate + $additionalCharge + $partnerFees;
			} else {
				$groupTotal = ($tourists * ($rate +
					$additionalCharge +
					$permitFee + $partnerFees
				));
			}
			$partnerFeesTotal += ($partnerFees * $tourists);

			$subtt += $groupTotal;
			// Calculate service commission and additional charges if applicable
			if (($request->payment_method === 'Card' || ($request->payment_method == 'Cash' && $isCashFees == 1)) || $isCart) {
				// See DiscountService::hasEffectiveDiscount() — a stray checked discount radio
				// with no value must not skip the correct per-person-rounded-then-summed commission.
				if (!DiscountService::hasEffectiveDiscount($request)) {
					if ($isGroupRate == '1') {
						$serviceCommissionTotal = roundout((($rate + $additionalCharge + $partnerFees) * $natFeesPercentage / 100), 2);
					} else {
						$serviceCommissionPax = roundout((($rate + $additionalCharge + $permitFee + $partnerFees) * $natFeesPercentage / 100), 2);
						$serviceCommissionPax = $tourists * $serviceCommissionPax;
						$serviceCommissionTotal += $serviceCommissionPax;
					}
				}
				$groupTotal += ($groupTotal * (float)$rateGroups[$b]->processing_charge_percentage) / 100;
			}

			//Calculate cash prices as per new pricing structure for cart only
			if ($isCart && $isCashPrice == 1) {
				if ($isGroupRate == '1') {
					$cashGroupTotal = $cashRate + $cashAdditionalCharge + $partnerFees;
				} else {
					$cashGroupTotal = ($tourists * ($cashRate +
						$cashAdditionalCharge +
						$permitFee + $partnerFees
					));
				}

				$cashSubtt += $cashGroupTotal;

				if (!DiscountService::hasEffectiveDiscount($request)) {
					if ($isGroupRate == '1') {
						$cashServiceCommissionTotal = roundout((($cashRate + $cashAdditionalCharge + $partnerFees) * $natFeesPercentage / 100), 2);
					} else {
						$cashServiceCommissionPax = roundout((($cashRate + $cashAdditionalCharge + $permitFee + $partnerFees) * $natFeesPercentage / 100), 2);
						$cashServiceCommissionPax = $tourists * $cashServiceCommissionPax;
						$cashServiceCommissionTotal += $cashServiceCommissionPax;
					}
				}
				$cashTotal += $cashGroupTotal;
			}

			$tbTransact[] = [
				'tour_package_rate_id' => $rateGroups[$b]->id,
				'tourists' => ($isInfantRate == 0) ? $tourists : 0,
				'infants' => $infants,
				'is_infant_rate' => $isInfantRate,
				'processing_charge_percentage' => (($request->payment_method !== 'Card') ? 0 : $rateGroups[$b]->processing_charge_percentage),
				'nat_fees_percentage' => $natFeesPercentage,
				'stripe_fees_percentage' => $stripeFeesPercentage,
				'partner_fees_type' => ($partnerFeesValue > 0) ? $partnerFeeType : '',
				'partner_fees_percentage' => $partnerFeesValue,
				'partner_fees_amount' => $partnerFees,
				'additional_tax_percentage' => ($isGroupRate == '1') ? 0 : ($rateGroups[$b]->additional_tax_percentage),
				'additional_charge' => $additionalCharge,
				'permit_fee' => ($isGroupRate == '1') ? 0 : $permitfeeAr[$b],
				'rate' => $rate,
				'total' => $groupTotal,
				'is_group_rate' => ($isGroupRate == '1') ? 1 : 0,
				'is_cash_rate' => (($isCashPriceApplied && $rateGroups[$b]->cash_rate > 0) || ($isCart && $isCashRate == 1)) ? 1 : 0,
				'created_by' => Auth::id(),
				'created_by_user_type' => $userType,
				'created_at' => Carbon::now(),
			];
			$tbTransaction[] = [
				'tourists' => $tourists,
				'rate_for' => $rateGroups[$b]->rate_for
			];
			if ($isCart) {
				$tbTransaction[] = [
					'cash_rate' => $rateGroups[$b]->cash_rate,
					'cash_tax' => $rateGroups[$b]->cash_tax,
				];
			}
			$total += $groupTotal;
		}

		// if ($isCart && $isCashPrice == 1) {
		// 	$cashTotal = $cashSubtt + ($isCashFees ? $cashServiceCommissionTotal : 0);
		// }

		return [
			'subtt' => $subtt,
			'total' => $total,
			'serviceCommissionTotal' => $serviceCommissionTotal,
			'partnerFeesTotal' => $partnerFeesTotal,
			'cashSubtt' => $cashSubtt,
			'cashTotal' => $cashTotal,
			'cashServiceCommissionTotal' => $cashServiceCommissionTotal,
			'tbTransact' => $tbTransact,
			'touristGroups' => $touristGroups,
			'touristGroupsForCart' => $touristGroupsForCart,
			'tbTransaction' => $tbTransaction
		];
	}
	/**
	 * Calculate the cash payment details.
	 *
	 * @param object $request
	 * @param float $customChargeAmount
	 * @param float $customChargeFees
	 * @param float $addonPrice
	 * @param float $addonFee
	 * @param float $tt
	 * @param float $subTotal
	 * @param int $isCashFees
	 * @param float $stripeFeesPercentage
	 * @param float $serviceCommission
	 * @return array
	 */
	public static function calculateCashPaymentForCreateBooking(
		object $request,
		float $customChargeAmount,
		float $customChargeFees,
		float $addonPrice,
		float $addonFee,
		float $tt,
		float $subTotal,
		int $isCashFees,
		float $stripeFeesPercentage,
		float $serviceCommission,
		float $tipAmount = 0,
		float $tipFee = 0,
	): array {
		$cashPaidAmount = $request->has('cash_paid_amount') ? $request->cash_paid_amount : '';

		// Determine the payment amount based on cash fee settings
		if ($isCashFees) {
			$paymentAmountCash = !empty($request->split_value) ? $request->split_value : $tt;
			if ($customChargeAmount > 0 || $addonPrice > 0 || $tipAmount > 0) {
				$paymentAmountCash += $customChargeAmount + $customChargeFees + $addonPrice + $addonFee + $tipAmount + $tipFee;
			}
		} else {
			$paymentAmountCash = !empty($request->split_value) ? $request->split_value : $subTotal;
			if ($customChargeAmount > 0 || $addonPrice > 0 || $tipAmount > 0) {
				$paymentAmountCash += $customChargeAmount + $addonPrice + $tipAmount;
			}
		}

		// Calculate change amount if cash paid is greater than required
		$changeAmount = ($cashPaidAmount !== '' && $cashPaidAmount > 0 && $cashPaidAmount > $paymentAmountCash)
			? round($cashPaidAmount - $paymentAmountCash, 2)
			: 0;

		// Set cash amount and fee amount based on cash fee settings
		$cashAmount = $isCashFees ? $tt : $subTotal;
		$feeAmountCash = $isCashFees
			? (!empty($request->split_value) ? round(($request->split_value * $stripeFeesPercentage / 100), 2) : $serviceCommission)
			: 0;

		return [
			'cashPaidAmount' => $cashPaidAmount,
			'paymentAmountCash' => $paymentAmountCash,
			'changeAmount' => $changeAmount,
			'cashAmount' => $cashAmount,
			'feeAmountCash' => $feeAmountCash
		];
	}

	/**
	 * Calculate the total partial refund amount based on provided partial refunds for booking.
	 *
	 * @param object $partialRefunds List of partial refund records.
	 * @return float The total partial refund amount.
	 */
	public static function calculatePartialRefundAmount(object $partialRefunds): float
	{
		$partialRefundAmount = 0;

		// Loop through each partial refund entry
		foreach ($partialRefunds as $partialRefund) {
			$details = json_decode($partialRefund->details);

			// Handle 'Partial Refund' transactions specifically
			if ($partialRefund->payment_method === 'Partial Refund') {
				// Only include amounts from deleted tourists or valid payment/refund totals
				if ((isset($details->deleted) && $details->deleted !== '') || (isset($details->paymenttotal) && isset($details->refundtotal))) {
					$partialRefundAmount += $details->amount;
				}
			} else {
				// For other payment methods, include the amount directly
				$partialRefundAmount += $details->amount;
			}

			// Adjust the amount if the refund type requires keeping a portion
			if (
				in_array($partialRefund->payment_method, ['Partial Refund', 'Full Refund minus Fees']) &&
				isset($details->deleted) && $details->deleted !== '' &&
				isset($details->refund_keep_us) && $details->refund_keep_us > 0
			) {
				$partialRefundAmount += $details->refund_keep_us;
			}
		}

		return $partialRefundAmount;
	}

	/**
	 * Calculate the booking total amount with fees, refunds, and tourists adjustments for change people.
	 *
	 * @param object $request The incoming request object.
	 * @param object $bookingTransactions object of booking transactions.
	 * @param float $natFeesPercentage National fee percentage.
	 * @param int $oldBookingID Old booking identifier.
	 * @param array $rateGroupWiseAddedPeopleArray Array of added people per rate group.
	 * @param array $rateGroupWiseDeletedPeopleArray Array of deleted people per rate group.
	 * @param bool $isCashFees Flag for cash fees.
	 * @param string $paymentMethod Payment method type.
	 * @param bool $isGroupBooking Group booking flag.
	 * @return array Contains booking total, refunded amounts, and service commission.
	 */
	public static function calculateBookingAmountChangePeople(
		$request,
		$bookingTransactions,
		$natFeesPercentage,
		$oldBookingID,
		$rateGroupWiseAddedPeopleArray,
		$rateGroupWiseDeletedPeopleArray,
		$isCashFees,
		$paymentMethod,
		$isGroupBooking
	): array {
		$bookingTotal = 0;
		$refundedPermitFeesTotalAmount = 0;
		$refundedProcessingFeesTotal = 0;
		$serviceCommissionOldTotal = 0;

		foreach ($bookingTransactions as $tbTransaction) {
			$rate = $tbTransaction->rate;
			$permitFee = $tbTransaction->permit_fee;
			$tourists = $tbTransaction->tourists;
			$partnerFees = $tbTransaction->partner_fees_amount;

			// Determine processing fee
			if ($request->payment_method == 'Card' || $paymentMethod == 'Card' || ($isCashFees == '1' && ($request->payment_method == 'Cash' || $paymentMethod == 'Cash'))) {
				$procFee = ($request->bookingID < $oldBookingID) ? 2.9 : $tbTransaction->packageRate->processing_charge_percentage;
			} else {
				$procFee = 0;
			}

			// Adjust tourist count based on added/deleted people
			if (isset($rateGroupWiseAddedPeopleArray[$tbTransaction->tour_package_rate_id]) && $rateGroupWiseAddedPeopleArray[$tbTransaction->tour_package_rate_id] > 0) {
				$tourists += $rateGroupWiseAddedPeopleArray[$tbTransaction->tour_package_rate_id];
			}

			if (isset($rateGroupWiseDeletedPeopleArray[$tbTransaction->tour_package_rate_id]) && $rateGroupWiseDeletedPeopleArray[$tbTransaction->tour_package_rate_id] > 0 && $tourists >= $rateGroupWiseDeletedPeopleArray[$tbTransaction->tour_package_rate_id]) {
				$tourists -= $rateGroupWiseDeletedPeopleArray[$tbTransaction->tour_package_rate_id];
			}

			// Handle refunds and calculate subtotal
			[$refundedPermitFeesTotal, $subTotal] = self::calculateSubTotalChangePeople($request, $tbTransaction, $rate, $permitFee, $partnerFees, $tourists, $isGroupBooking, $oldBookingID);

			$refundedPermitFeesTotalAmount += $refundedPermitFeesTotal;

			// Calculate service commission for newer bookings
			if ($request->bookingID > $oldBookingID) {
				$serviceCommissionOldTotal += round($subTotal * $natFeesPercentage / 100, 2);
			}

			// Calculate final booking total with processing fees
			$bookingTotal += $procFee ? $subTotal + $subTotal * ($procFee / 100) : $subTotal;
		}

		return [
			'bookingTotal' => $bookingTotal,
			'refundedPermitFeesTotalAmount' => $refundedPermitFeesTotalAmount,
			'refundedProcessingFeesTotal' => $refundedProcessingFeesTotal,
			'serviceCommissionOldTotal' => $serviceCommissionOldTotal
		];
	}
	/**
	 * Calculate the subtotal and any refunded permit fees for change people.
	 */
	private static function calculateSubTotalChangePeople($request, $transaction, $rate, $permitFee, $partnerFees = 0, $tourists, $isGroupBooking, $oldBookingID, $isPartnerPackage = 0): array
	{
		$refundedPermitFeesTotal = 0;
		$refundPermitFeeStatus = $transaction->refund_permitfee_status ? json_decode($transaction->refund_permitfee_status) : '';

		if (!empty($refundPermitFeeStatus) && $permitFee > 0 && !$isGroupBooking) {
			foreach ($refundPermitFeeStatus as $refund) {
				if (!isset($refund->is_pax_added) && $refund->status === 'Y') {
					$refundedPermitFeesTotal += $refund->amount ?? 0;
				}
			}

			$subTotal = ($rate + $permitFee + $transaction->additional_charge + $partnerFees) * $tourists - $refundedPermitFeesTotal;
		} else {
			$subTotal = $isGroupBooking ? $rate + $transaction->additional_charge + $partnerFees : ($rate + $permitFee + $transaction->additional_charge + $partnerFees) * $tourists;
		}

		return [$refundedPermitFeesTotal, $subTotal];
	}

	/**
	 * View and print the invoice of combo bookings
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public static function comboBookingInvoiceDetails($id): array
	{
		$tourBooking = TourBooking::where('cart_id', $id)->first(['id', 'cart_id']);
		if (!$tourBooking) {
			throw new Exception("Tour booking not found.");
		}
		if ($tourBooking->cart_id != '') {
			$comboBookings = [];
			$comboSubtotal = 0;
			$comboBookingFeesTotal = 0;
			$comboBookingTotal = 0;
			$comboRefundTotal = 0;
			$comboNonRefundableFeesTotal = 0;
			$comboPaymentTotal = 0;
			$logo = $tourOpPhone = $tourOpEmail = $tourOpPhoneEmail = $custPhoneEmail = $custPhone = $custEmail = '';
			$cartBookings = TourBooking::where('cart_id', $tourBooking->cart_id)->get(['id']);
			if ($cartBookings) {
				foreach ($cartBookings as $cartBooking) {
					$bookingM = DB::table('tour_bookings_master AS tbm')
						->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
						->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
						->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
						->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
						->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
						->leftJoin('tour_promotions AS promo', 'promo.id', '=', 'tbm.tour_promotion_id')
						->where('tbm.id', $cartBooking->id)
						->first([
							'tbm.id',
							'tbm.tourists',
							'tbm.infants',
							'tbm.status',
							'tbm.total',
							'tbm.note',
							'tbm.discount',
							'tbm.discount2_value',
							'tbm.discount2_percentage',
							'tbm.service_commission',
							'tbm.created_at',
							'tp.name AS tour_package',
							'tp.package_has_slots',
							'tp.package_has_waiver',
							'tp.package_has_permit',
							't_o.name AS tour_operator',
							't_o.email AS tour_op_email',
							't_o.phone_number AS tour_op_phone',
							't_o.logo',
							't_o.tenant_id',
							'tsd.date',
							'tst.time_from',
							'tst.time_to',
							'tbm.name AS customer',
							'tbm.email AS cust_email',
							'tbm.phone_number AS cust_phone',
							'promo.discount_value',
							'promo.discount_value_type',
							'tbm.booking_total',
							'tbm.is_group_booking',
							'tbm.is_partner_booking'
						]);
					$bookingT = DB::table('tour_bookings_transact AS tbt')
						->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
						->where('tbt.tour_booking_id', $cartBooking->id)
						->where('tbt.deleted_by', 0)
						->orderBy('tpr.orderby', 'asc')
						->get([
							'tbt.id',
							'tbt.tourists',
							'tbt.infants',
							'tbt.processing_charge_percentage',
							'tbt.additional_tax_percentage',
							'tbt.nat_fees_percentage',
							'tbt.permit_fee',
							'tbt.partner_fees_amount',
							'tbt.additional_charge',
							'tbt.state_tax',
							'tbt.rate',
							'tbt.total',
							'tpr.rate_for'
						]);

					$trans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'DESC')->first();
					// orderBy('id', 'asc') is required — without it, ->first() can resolve to ANY
					// of the booking's Payment rows instead of the true first one, and the
					// operation_at > ... filter below then silently drops every Refund/Cancelled
					// row that happened before whichever later payment got picked, understating
					// both Refund Amount and Payment Total.
					$HistoryPaymentTime = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->orderBy('id', 'asc')->first();

					if (!empty($HistoryPaymentTime)) {
						$TourBookingHistory = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation_at', '>', $HistoryPaymentTime->operation_at)
							->where(function ($query) {
								return $query->where('operation', '=', 'Refund')
									->orWhere('operation', '=', 'Cancelled');
							})
							->get();
					}

					// No charge_type filter here, deliberately: Payment Total must reflect every dollar
					// actually collected on the booking — the base payment (charge_type NULL) plus the
					// separate charge_type='addons'/'custom_charge'/'tip' Payment rows those charges write.
					// Filtering to whereNull('charge_type') understates Payment Total by exactly the add-on
					// amount, and diverges from the single-booking invoice (bookingInvoicePrint), which sums
					// these rows unfiltered. Add-on refunds net out below via $TourBookingHistory, whose
					// charge_type='addons' Refund rows are likewise unfiltered ('dont_refund' rows carry $0).
					$paymentOnly = TourBookingHistory::where('tour_booking_id', $cartBooking->id)->where('operation', 'Payment')->get();
					$paymentTrans = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->get(['id', 'tour_booking_id', 'amount', 'fee_amount', 'transaction_number', 'application_fee_id', 'via', 'payment_type', 'status']);

					$paymentTransCount = count($paymentTrans);
					$paymentOnlyCheck = 0;
					foreach ($paymentOnly as $value) {
						$details = json_decode($value->details);
						$paymentOnlyCheck += $details->amount;
					}

					$transP = Transaction::where('tour_booking_id', $cartBooking->id)->where('status', 'Completed')->orderBy('id', 'ASC')->first();


					$promoDiscount = 0;

					if ($bookingM->discount2_percentage > 0) {
						$promoDiscount = str_replace('.00', '', $bookingM->discount2_value) . '%';
					} elseif ($bookingM->discount2_value > 0) {
						$promoDiscount = currencyFormation(false, true) . $bookingM->discount2_value;
					}

					$totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;
					$serviceCommissionPercentage = 0;
					if ($totalBeforeServiceCommission != 0) {
						$serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
					}

					$amount = 0;
					$refund_keep_us = 0;
					$deletedAgeGroup = [];

					if (!empty($trans)) {
						$paidF = $paymentOnlyCheck;
					} else {
						$paidF = 0;
					}

					if (!empty($TourBookingHistory)) {
						foreach ($TourBookingHistory as $BookingHistory) {
							$details = json_decode($BookingHistory->details);

							if (isset($details->refund_type) && !in_array(strtolower($details->refund_type), ["don't refund"])) {
								$amount += $details->amount;
							}
							if (isset($details->deleted)) {
								if (is_array($details->deleted)) {
									foreach ($details->deleted as $look) {
										$deletedAgeGroup[] = $look;
									}
								} else {
									$deletedAgeGroup[] = $details->deleted;
								}
							}
							$details->refund_keep_us = $details->refund_keep_us ?? 0;
							if (isset($details->refund_type) && strtolower($details->refund_type) !== "partial refund") {
								$refund_keep_us += $details->refund_keep_us;
							}


							$paidF = $paymentOnlyCheck - $amount;
							if (!empty($HistoryPaymentTime)) {
								$checkAmount = json_decode($HistoryPaymentTime->details);
								if ($checkAmount->amount == $amount) {
									$paidF = 0;
								}
							}
						}
					}

					$resultArray = [];
					if (count($deletedAgeGroup)) {
						$personCount = [];
						foreach ($deletedAgeGroup as $entry) {
							// Get the prefix text "Adults (Ages 13 & Up) person"
							$prefix = substr($entry, 0, strpos($entry, "person"));

							// Extract the person number from the entry and convert to integer
							$personNumber = intval(substr($entry, strrpos($entry, " ") + 1));

							// Check if the entry with the same prefix exists in the person count array
							if (!isset($personCount[$prefix])) {
								// Create a new entry in the person count array
								$personCount[$prefix] = 0;
							}

							// Increment the sum of person numbers for the existing entry
							$personCount[$prefix] += $personNumber;
						}

						// Convert the person count array to the desired format
						foreach ($personCount as $prefix => $count) {
							$resultArray[] = $prefix . " " . $count;
						}
					}

					if ($bookingM->status == "Cancelled") {
						if (count($resultArray) > 0) {
							foreach ($resultArray as $key => $ar) {
								$rate = substr($ar, 0, strripos($ar, " "));
								$rateNumber = intval(substr($ar, strrpos($ar, " ") + 1));
								$ratespacetrim = rtrim($rate);

								foreach ($bookingT as $bt) {
									if ($ratespacetrim == $bt->rate_for) {
										$totaltourists = $rateNumber + ($bt->infants > 0 ? $bt->infants : $bt->tourists);
										unset($resultArray[$key]);
									}

									if (!empty($totaltourists)) {
										$resultArray[] = $bt->rate_for . " " . $totaltourists;
									} else {
										$resultArray[] = $bt->rate_for . " " . ($bt->infants > 0 ? $bt->infants : $bt->tourists);
									}
								}
							}
						} else {
							foreach ($bookingT as $bt) {
								$resultArray[] = $bt->rate_for . " " . ($bt->infants > 0 ? $bt->infants : $bt->tourists);
							}
						}
					}

					$ageGroup = (!empty($resultArray)) ? implode("<br>", $resultArray) : null;

					$bookingTotal = 0;
					$refundKeepUs = 0;
					$refundAmount = 0;
					$paymentTotal = 0;
					if ((!empty($trans) && $transP->payment_type == 1) || (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type == '') && $paymentTransCount > 1)) {
						$bookingTotal = $bookingM->booking_total;
						$comboBookingTotal += $bookingM->booking_total;
					} elseif (!empty($checkAmount)) {
						$bookingTotal = $checkAmount->amount;
						$comboBookingTotal += $checkAmount->amount;
					} else {
						$bookingTotal = $bookingM->total;
						$comboBookingTotal += $bookingM->total;
					}

					if ($refund_keep_us) {
						$refundKeepUs = $refund_keep_us;
						$comboNonRefundableFeesTotal += $refund_keep_us;
					}
					if ($amount) {
						$refundAmount = $amount;
						$comboRefundTotal += $amount;
					}
					$paymentTotal = ($paidF > 0) ? $paidF : 0.00;

					$comboPaymentTotal += $paidF;
					$comboSubtotal += ($bookingM->total - $bookingM->service_commission);
					$comboBookingFeesTotal += $bookingM->service_commission;

					// Add-on 
					$pricedAddons = BookingService::fetchAddonsWithPrice($bookingM->id);
					$payLaterAddons = BookingService::fetchPayLaterAddonsWithPrice($bookingM->id, $bookingT[0]->nat_fees_percentage, $trans);

					// Custom charge service to calculate custom charge if applied
					$customChargePrice = CustomChargeBookingService::customChargePrice($bookingM->id, $paymentTrans);
					$tipAmounts = TipService::getTipAmounts($bookingM->id, $paymentTrans);
					$comboBookings[] = [
						'bookingM' => $bookingM,
						'bookingT' => $bookingT,
						'ageGroup' => $ageGroup,
						'customChargePrice' => $customChargePrice,
						'pricedAddons' => $pricedAddons,
						'payLaterAddons' => $payLaterAddons,
						'tipAmounts' => $tipAmounts,
						'bookingTotal' => number_format((float)$bookingTotal, 2, '.', ''),
						'refundKeepUs' => number_format((float)$refundKeepUs, 2, '.', ''),
						'refundAmount' => number_format((float)$refundAmount, 2, '.', ''),
						'paymentTotal' => number_format((float)$paymentTotal, 2, '.', '')
					];
				}
			}

			$bookingM = $comboBookings[0]['bookingM'];

			if ($bookingM->logo != '')
				$logo = tourOperatorLogo($bookingM->tenant_id, $bookingM->logo);

			if ($bookingM->tour_op_phone != '' && $bookingM->tour_op_email != '')
				$tourOpPhoneEmail = e($bookingM->tour_op_phone) . '<br>' . e($bookingM->tour_op_email);
			elseif ($bookingM->tour_op_email != '')
				$tourOpPhoneEmail = e($bookingM->tour_op_email);
			elseif ($bookingM->tour_op_phone != '')
				$tourOpPhoneEmail = e($bookingM->tour_op_phone);

			if ($bookingM->cust_phone != '' && $bookingM->cust_email != '')
				$custPhoneEmail = e($bookingM->cust_phone) . '<br>' . e($bookingM->cust_email);
			elseif ($bookingM->cust_email != '')
				$custPhoneEmail = e($bookingM->cust_email);
			elseif ($bookingM->cust_phone != '')
				$custPhoneEmail = e($bookingM->cust_phone);

			$tourOpPhone = $bookingM->tour_op_phone;
			$tourOpEmail = $bookingM->tour_op_email;
			$custPhone = $bookingM->cust_phone;
			$custEmail = $bookingM->cust_email;

			if (request()->has('api') && request()->api == '1') {
				$vars = [
					'comboBookings' => $comboBookings,
					'logo' => $logo,
					'tourOpPhone' => $tourOpPhone,
					'tourOpEmail' => $tourOpEmail,
					'custPhone' => $custPhone,
					'custEmail' => $custEmail,
					'tourOperator' => $bookingM->tour_operator,
					'comboSubtotal' => number_format((float)$comboSubtotal, 2, '.', ''),
					'comboBookingFeesTotal' => number_format((float)$comboBookingFeesTotal, 2, '.', ''),
					'comboBookingTotal' => number_format((float)$comboBookingTotal, 2, '.', ''),
					'comboNonRefundableFeesTotal' => number_format((float)$comboNonRefundableFeesTotal, 2, '.', ''),
					'comboRefundTotal' => number_format((float)$comboRefundTotal, 2, '.', ''),
					'comboPaymentTotal' => number_format((float)$comboPaymentTotal, 2, '.', ''),
				];
			} else {
				$vars = [
					'page_title' => 'Tour Booking Info',
					'comboBookings' => $comboBookings,
					'logo' => $logo,
					'tourOpPhoneEmail' => $tourOpPhoneEmail,
					'custPhoneEmail' => $custPhoneEmail,
					'tourOperator' => $bookingM->tour_operator,
					'comboSubtotal' => $comboSubtotal,
					'comboBookingFeesTotal' => $comboBookingFeesTotal,
					'comboBookingTotal' => $comboBookingTotal,
					'comboNonRefundableFeesTotal' => $comboNonRefundableFeesTotal,
					'comboRefundTotal' => $comboRefundTotal,
					'comboPaymentTotal' => $comboPaymentTotal,
				];
			}
		}
		return $vars;
	}
	public static function getBookingDetails(int $id, string $userType)
	{
		$query = DB::table('tour_bookings_master AS tbm')
			->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tbm.tour_package_id')
			->join('customers AS cus', 'cus.id', '=', 'tbm.customer_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->where('tbm.id', $id);

		if (in_array($userType, ['Tour Operator', 'Affiliate'])) {
			$query->where('tp.tour_operator_id', Auth::user()->tour_operator_id);
		}

		return $query->first([
			'tbm.tour_package_id AS pkg_id',
			'tbm.tourists',
			'tbm.infants',
			'tbm.status',
			'tbm.customer_id',
			'tst.id AS tour_slot_id',
			'tp.tour_operator_id',
			'tbm.email',
			'tbm.name',
			'tbm.group_id',
			'tbm.phone_number',
			'cus.is_affiliate',
			'tp.service_commission_percentage',
			DB::raw("CONCAT(tsd.date, ' ', tst.time_from) AS tour_d_t"),
			'tst.time_from',
			'tst.time_to',
			'tsd.date',
			'tst.time_slot_seats AS seats',
			'tp.name AS package_name',
			'tp.id AS tour_package_id',
			'tp.things_to_bring',
			'tp.important_notes',
			't_o.cancellation_policy',
			't_o.id AS tour_operator_id',
			't_o.name AS tour_operator',
			't_o.email AS tour_op_email',
			't_o.phone_number AS tour_op_phone',
			't_o.website AS tour_op_website',
			't_o.logo AS tour_op_logo',
			'tp.service_commission_percentage',
			'cus.affiliate_processing_percentage',
			'cus.stripe_affiliate_percentage',
			'tp.stripe_commission_percentage',
			't_o.tenant_id',
			'tp.email AS packageEmail',
			'tp.from_name AS packageFromName',
			'tp.from_email AS packageFromEmail',
			'tp.api_key AS packageApiKey',
			'tbm.service_commission',
			'tbm.is_group_booking',
			DB::raw("(SELECT COUNT(tbm.tourists)
                      FROM tour_bookings_master AS tbm
                      WHERE tbm.tour_slot_id = tst.id
                        AND tbm.status != 'Cancelled'
                      GROUP BY tbm.tour_slot_id) AS booked_seats"),
		]);
	}

	/**
	 * Calculate the totals of rategroup if cash prices enabled
	 */
	public static function calculateBookingCashAmounts($bookingM, $bookingT, $trans)
	{
		$bookingId = $bookingM->id;
		$cashPriceEnabled = cashPriceEnabled();

		$cashPriceSubtotal = 0;
		$cashPriceCommission = 0;
		$cashPriceTotal = 0;
		$isCashFees = cashFees();

		if ((empty($trans) || (!empty($trans) && $trans->via == 'Invoiced') && ($trans->payment_type == 0 || $trans->payment_type == '')) && $bookingM->statusm != 'Cancelled' && $cashPriceEnabled == 1 && !empty($bookingT)) {
			foreach ($bookingT as $bt) {
				$tourists = ($bt->is_infant_rate == 1) ? (int)$bt->infants : (int)$bt->tourists;
				$isGroupRate = ((int)$bt->is_group_rate == 1);
				$isCashRate = ((int)$bt->is_cash_rate == 1);

				$rate = $isCashRate ? (float)$bt->cash_rate : (float)$bt->rate;
				$additionalCharge = $isCashRate ? (float)$bt->cash_tax : (float)$bt->additional_charge;
				$permitFee = (float)$bt->permit_fee;
				$partnerFee = (float)$bt->partner_fees_amount;

				if ($isGroupRate) {
					$groupBase = $rate + $additionalCharge + $partnerFee;
					$groupSubtotal = $groupBase;
					$commissionBase = $groupBase;
				} else {
					$groupBase = $tourists * ($rate + $additionalCharge + $permitFee + $partnerFee);
					$groupSubtotal = $groupBase;
					$commissionBase = ($rate + $additionalCharge + $permitFee + $partnerFee) * $tourists;
				}

				$additionalTaxPercentage = (float)$bt->additional_tax_percentage;
				if ($additionalTaxPercentage != 0) {
					$groupSubtotal += roundout(($groupBase * $additionalTaxPercentage / 100), 2);
				}

				$cashPriceSubtotal += $groupSubtotal;

				$natFeesPercentage = (float)$bt->nat_fees_percentage;
				if ($natFeesPercentage > 0) {
					if ($isGroupRate) {
						$cashPriceCommission += roundout(($commissionBase * $natFeesPercentage / 100), 2);
					} else {
						$per = roundout((($rate + $additionalCharge + $permitFee + $partnerFee) * ($natFeesPercentage) / 100), 2);
						$cashPriceCommission += $tourists * $per;
					}
				}
			}
			//Calculate service commission if discount applied
			if ($bookingM->discount2_percentage > 0) {
				$discountPercentage = $bookingM->discount2_percentage;
				$discountForCashPrice = roundout(($cashPriceSubtotal * $discountPercentage / 100), 2);
				$cashPriceSubtotal = $cashPriceSubtotal - $discountForCashPrice;
				$cashPriceCommission = roundout(($cashPriceSubtotal * $natFeesPercentage / 100), 2);
			} else if ($bookingM->discount2_value > 0 && isset($bookingT[0]->nat_fees_percentage)) {
				$cashPriceSubtotal = $cashPriceSubtotal - $bookingM->discount2_value;
				$cashPriceCommission = roundout(($cashPriceSubtotal * $natFeesPercentage / 100), 2);
			}
		}

		$cashPriceTotal = $cashPriceSubtotal + ($isCashFees ? $cashPriceCommission : 0);

		return [
			'cashPriceSubtotal' => $cashPriceSubtotal,
			'cashPriceCommission' => $isCashFees ? $cashPriceCommission : 0,
			'cashPriceTotal' => $cashPriceTotal,
		];
	}
	/**
	 * Calculate the card totals of rategroup if cash prices enabled
	 */
	public static function calculateBookingCardAmounts($bookingM, $bookingT, $trans)
	{
		$cashPriceEnabled = cashPriceEnabled();

		$cardPriceSubtotal = 0;
		$cardPriceCommission = 0;
		$cardPriceTotal = 0;

		if ((empty($trans) || (!empty($trans) && ($trans->via == 'Cash' || $trans->via == 'Invoiced')) && ($trans->payment_type == 0 || $trans->payment_type == '')) && $bookingM->statusm != 'Cancelled' && $cashPriceEnabled == 1 && !empty($bookingT)) {
			foreach ($bookingT as $bt) {
				$tourists = ($bt->is_infant_rate == 1) ? (int)$bt->infants : (int)$bt->tourists;
				$isGroupRate = ((int)$bt->is_group_rate == 1);

				$rate = (float)$bt->tpr_rate;
				$additionalCharge = (float)$bt->tpr_additional_charge;
				$permitFee = (float)$bt->permit_fee;
				$partnerFee = (float)$bt->partner_fees_amount;

				if ($isGroupRate) {
					$groupBase = $rate + $additionalCharge + $partnerFee;
					$groupSubtotal = $groupBase;
					$commissionBase = $groupBase;
				} else {
					$groupBase = $tourists * ($rate + $additionalCharge + $permitFee + $partnerFee);
					$groupSubtotal = $groupBase;
					$commissionBase = ($rate + $additionalCharge + $permitFee + $partnerFee) * $tourists;
				}

				$additionalTaxPercentage = (float)$bt->tpr_additional_tax_percentage;
				if ($additionalTaxPercentage != 0) {
					$groupSubtotal += roundout(($groupBase * $additionalTaxPercentage / 100), 2);
				}

				$cardPriceSubtotal += $groupSubtotal;

				$natFeesPercentage = (float)$bt->nat_fees_percentage;
				if ($natFeesPercentage > 0) {
					if ($isGroupRate) {
						$cardPriceCommission += roundout(($commissionBase * $natFeesPercentage / 100), 2);
					} else {
						$per = roundout((($rate + $additionalCharge + $permitFee + $partnerFee) * ($natFeesPercentage) / 100), 2);
						$cardPriceCommission += $tourists * $per;
					}
				}
			}
			//Calculate service commission if discount applied
			if ($bookingM->discount2_percentage > 0) {
				$discountPercentage = $bookingM->discount2_percentage;
				$discountForCardPrice = roundout(($cardPriceSubtotal * $discountPercentage / 100), 2);
				$cardPriceSubtotal = $cardPriceSubtotal - $discountForCardPrice;
				$cardPriceCommission = roundout(($cardPriceSubtotal * $natFeesPercentage / 100), 2);
			} else if ($bookingM->discount2_value > 0 && isset($bookingT[0]->nat_fees_percentage)) {
				$cardPriceSubtotal = $cardPriceSubtotal - $bookingM->discount2_value;
				$cardPriceCommission = roundout(($cardPriceSubtotal * $natFeesPercentage / 100), 2);
			}
		}

		$cardPriceTotal = $cardPriceSubtotal + $cardPriceCommission;

		return [
			'cardPriceSubtotal' => $cardPriceSubtotal,
			'cardPriceCommission' => $cardPriceCommission,
			'cardPriceTotal' => $cardPriceTotal,
		];
	}
}
