<?php

namespace App\Services\UnitTest;

use App\Http\Requests\Common\TourPackageRequest;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class UnitTest
{
	/**
	 * Get the tour package params to get the params to store package
	 *
	 *
	 * @return object
	 */
	public static function CreateTourPackageParams()
	{
		$request = new TourPackageRequest();
		$importantNotes = '<h2>IMPORTANT INFORMATION</h2>
<strong>TIME ZONE</strong>

<p>
We follow Arizona Standard Time (MST). We share the same time as the City of Page, Flagstaff, Grand Canyon, and Phoenix. During Daylight Savings guest will experience a time change driving through the state of Utah and the Navajo Reservation.
</p>

<p>
You are more than welcome to take pictures on this tour. One camera limited per person. Lower Antelope Canyon offers great lighting all day long. The canyon runs north to south, as the sun moves across the sky, different parts of the canyon gets highlighted. No matter when you visit Lower Antelope Canyon it will be spectacular.
</p>

<strong>Pregnant Women are HIGHLY discouraged due to the strenuous nature of the tour.</strong>
<p> Strollers and Wheel based devices will not be allowed on the tour.</p>

<strong>Infants and Toddlers</strong>
<p>If your child cannot walk during the tour. It is required that you carry your child in a front chest carrier or a back pack carrier.</p>';

		$request->merge([
			'name' => 'Test Package',
			'short_description' => 'dfsfdsf',
			'long_description' => 'dsfdsfdsf',
			'hours' => '0',
			'minutes' => '30',
			'duration' => '1',
			'day_hours' => '1',
			'things_to_bring' => '[{"value":"Bottled Water"},{"value":"Closed-Toed Shoes"}]',
			'important_notes' => $importantNotes,
			'seo_title' => 'sadasd',
			'seo_description' => 'asdasd',
			'seo_keywords' => 'sadasdasd asdasd asdasd',
			'scheduling_year' => '2024',
			'rate_for' => ['Adult', 'Infant'],
			'age_from' => ['8', '0'],
			'age_to' => ['13', '3'],
			'rate' => ['55', '0'],
			'permit_fee' => ['8', '0'],
			'processing_charge_percentage' => ['0', '0'],
			'additional_tax_percentage' => ['0', '0'],
			'additional_charge' => ['3.30', '0'],
			'min_pax_allowed' => ['0', '0'],
			'max_pax_allowed' => ['0', '0'],
			'description' => ['sdsfdsfdsf', ''],
			'orderby' => ['0', '0'],
			'rate_status' => ['Active', 'Active'],
			'Order_by' => '123',
			'stripe_destination_id' => 'acct_dsfdsfdsf',
			'service_commission_percentage' => '9.800000',
			'stripe_commission_percentage' => '8.928200',
			'from_name' => 'Kens Tour Book',
			'from_email' => 'book@lowerantelope.com',
			'is_combo_package' => '1',
			'latitude' => '15.2232',
			'longitude' => '11.6565',
			'travel_duration' => '02:30:00',
			'package_has_slots' => 1,
			'package_has_waiver' => 1,
			'package_has_permit' => 1,
			'overbook_seats' => '20',
			'frontend_enabled' => '0',
			'category_id' => '2',
			'productCode' => '36456456546',
			'status' => 'Active'
		]);

		return $request;
	}

	/**
	 * Get the tour package params to update general details of the package
	 *
	 *
	 * @return object
	 */
	public static function UpdateTourPackageGeneralDetailsParams()
	{
		$request = new TourPackageRequest();
		$importantNotes = '<h2>IMPORTANT INFORMATION</h2>
<strong>TIME ZONE</strong>

<p>
We follow Arizona Standard Time (MST). We share the same time as the City of Page, Flagstaff, Grand Canyon, and Phoenix. During Daylight Savings guest will experience a time change driving through the state of Utah and the Navajo Reservation.
</p>

<p>
You are more than welcome to take pictures on this tour. One camera limited per person. Lower Antelope Canyon offers great lighting all day long. The canyon runs north to south, as the sun moves across the sky, different parts of the canyon gets highlighted. No matter when you visit Lower Antelope Canyon it will be spectacular.
</p>

<strong>Pregnant Women are HIGHLY discouraged due to the strenuous nature of the tour.</strong>
<p> Strollers and Wheel based devices will not be allowed on the tour.</p>

<strong>Infants and Toddlers</strong>
<p>If your child cannot walk during the tour. It is required that you carry your child in a front chest carrier or a back pack carrier.</p>';

		$request->merge([
			'name' => 'Test Package',
			'short_description' => 'dfsfdsf',
			'long_description' => 'dsfdsfdsf',
			'hours' => '0',
			'minutes' => '30',
			'duration' => '1',
			'day_hours' => '1',
			'things_to_bring' => '[{"value":"Bottled Water"},{"value":"Closed-Toed Shoes"}]',
			'important_notes' => $importantNotes,
			'Order_by' => '123',
			'stripe_destination_id' => 'acct_dsfdsfdsf',
			'service_commission_percentage' => '9.800000',
			'stripe_commission_percentage' => '8.928200',
			'from_name' => 'Kens Tour Book',
			'from_email' => 'book@lowerantelope.com',
			'is_combo_package' => '1',
			'latitude' => '15.2232',
			'longitude' => '11.6565',
			'travel_duration' => '02:30:00',
			'package_has_slots' => 1,
			'package_has_waiver' => 1,
			'package_has_permit' => 1,
			'overbook_seats' => '20',
			'frontend_enabled' => '0',
			'category_id' => '2',
			'productCode' => '36456456546',
			'status' => 'Active'
		]);
		return $request;
	}

	/**
	 * Get the tour package params to update seo details of the package
	 *
	 *
	 * @return object
	 */
	public static function UpdateTourPackageSeoDetailsParams()
	{
		$request = new Request;
		$request->merge([
			"seo_title" => '',
			"seo_description" => '',
			"seo_keywords" => ''
		]);
		return $request;
	}

	/**
	 * Get the tour package params to update seo details of the package
	 *
	 *
	 * @return object
	 */
	public static function UpdateTourPackageWaiverDetailsParams()
	{
		$request = new Request;
		$request->merge([
			"waiver_content" => '<p>Kens Tours, LLC and the Navajo Nation are not responsible for any injuries, accidents or thefts of personal property during my visit. Kens Tours, LLC and the Navajo Nation cannot prevent me from being exposed to, contracting, or spreading COVID-19 while visiting Kens Tours, LLC and the Navajo Nation. Therefore, if I choose to utilize Kens Tours, LLC services and/or enter onto Kens Tours, LLC premises, I may be exposing myself to and/or increasing my risk of contracting or spreading COVID-19.
         I have and do hereby assume all the risk(s), legal responsibility for injury to myself or loss of property resulting from participation of this tour. This form shall serve as release and assumption of risk(s) for my heirs, personal representatives, executors, administrators and for all members of my family.</p>'
		]);
		return $request;
	}

	/**
	 * Get the params to add/update rate group details of the package
	 *
	 *
	 * @return object
	 */
	public static function AddUpdateRateGroupParams()
	{
		$request = new Request;
		$request->merge([
			"tour_package_rate_id" => "",//Empty to create new rate group
			"scheduling_year" => "2024",
			"rate_for" => "Teenager",
			"age_from" => "0",
			"age_to" => "0",
			"rate" => "20",
			"permit_fee" => "0",
			"processing_charge_percentage" => "0",
			"additional_tax_percentage" => "0",
			"additional_charge" => "0",
			"min_pax_allowed" => "0",
			"max_pax_allowed" => "0",
			"description" => "",
			"orderby" => "5",
			"status" => "Active"]);
		return $request;
	}

	/**
	 * Get the params to add relation of the package
	 *
	 *
	 * @return object
	 */
	public static function AddRelationParams()
	{
		$request = new Request;
		$request->merge([
			"tour_operator_id" => "1",
			"parent_package_id" => "1",
			"tour_package_id" => "1",
			"time_from" => "00:00:00",
			"group_index" => "1",
			"is_parent" => "1"
		]);
		return $request;
	}

	/**
	 * Get the params to add hotel for the package
	 *
	 *
	 * @return object
	 */
	public static function AddHotelParams()
	{
		Storage::fake('hotels');
		$request = new Request;
		$request->merge([
			"tour_operator_id" => "1",
			"tour_package_id" => "1",
			"name" => "dasd",
			"email" => "anil@glorywebs.com",
			"phone_number" => "09925787515",
			"status" => "Active",
			"address" => "Testing address",
			//"hotel_image" => UploadedFile::fake()->image('hotel.jpg')
		]);
		return $request;
	}


	/**
	 * Get the params to add category for the package
	 *
	 *
	 * @return object
	 */
	public static function AddCategoryParams()
	{
		$request = new Request;
		$request->merge([
			"tour_operator_id" => "1",
			"tour_package_id" => "1",
			"caption" => "dasd",
			"category" => "anil@glorywebs.com",
			"category_image" => ""
		]);
		return $request;
	}

	/**
	 * Get the params to add category for the package
	 *
	 *
	 * @return object
	 */
	public static function UpdateIframeUrlParams()
	{
		$request = new Request;
		$request->merge([
			"tour_operator_id" => "1",
			"tour_package_id" => "1",
			"iframe_url" => "https://book.testingnat.com/?iframe=true&tid=kens&oid=1&pid=1"
		]);
		return $request;
	}

	/**
	 * Get the params to assign affiliate for the package
	 *
	 *
	 * @return object
	 */
	public static function AssignAffiliateParams()
	{
		$request = new Request;
		$request->merge([
			"tour_operator_id" => "1",
			"tour_package_id" => "1",
			"affiliate_id" => "4",
			"show_all_package_bookings" => "1"
		]);
		return $request;
	}

	/**
	 * Get the params to update assign affiliate for the package
	 *
	 *
	 * @return object
	 */
	public static function UpdateAssignAffiliateParams()
	{
		$request = new Request;
		$request->merge([
			"id" => "1",
			"show_all_package_bookings" => "1"
		]);
		return $request;
	}

	/**
	 * Get params to switch package if payment
	 *
	 *
	 * @return object
	 */
	public static function SwitchTourPackagePaymentParams()
	{
		$request = new Request;
		$request->merge([
			'bookingID' => '36015',
			'payment_method' => 'Cash',//Card, Pay Later
			'cardnumber' => '',
			'nameoncard' => '',
			'expiration' => '',
			'cvv' => '',
			'tour_package_switch' => '2',
			'tour_slot_time_switch' => '60430',
			'validation_check' => 'false',
		]);
		return $request;
	}

	/**
	 * Get params to switch package if refund
	 *
	 *
	 * @return object
	 */
	public static function SwitchTourPackageRefundParams()
	{
		$request = new Request;
		$request->merge([
			'refund_type' => '100_refund',//full_refund, partial_refund, dont_refund
			'bookingID' => '36016',
			'tour_package_switch' => '1',
			'tour_slot_time_switch' => '52657',
			'validation_check' => 'false',
			'partial_refund_value' => '',
			'is_cash' => '', //Wire Transfer,Cash
		]);
		return $request;
	}
}
