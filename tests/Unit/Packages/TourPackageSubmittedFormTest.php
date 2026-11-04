<?php

namespace Tests\Unit\Packages;

use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use App\Models\TourPackageForm;
use App\Models\TourBookingForms;
use App\Models\AddonForm;
use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourPackage\TourPackageRepository;
use Carbon\Carbon;

/**
 * Covers TourPackageRepository::getSubmittedCustomForm() -- the data source for
 * v1/get-package-form-submission/{bookingId} -- across all four combinations of
 * old-system (tour_booking_forms) and new-system (tour_booking_addon_fields)
 * add-ons data, so the merged has_old_addons/has_new_addons/new_addons keys
 * stay correct without breaking the pre-existing form_id/booking_id/fields shape.
 */
class TourPackageSubmittedFormTest extends TestCase
{
    protected TourPackageRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourPackageRepository::class);
    }

    public function testNeitherOldNorNewAddonsReturnsAllNulls(): void
    {
        $booking = (new CreateABooking())->create();

        try {
            $result = $this->repo->getSubmittedCustomForm($booking->bookingId);

            $this->assertNull($result['form_id']);
            $this->assertNull($result['booking_id']);
            $this->assertNull($result['fields']);
            $this->assertNull($result['description']);
            $this->assertFalse($result['has_old_addons']);
            $this->assertFalse($result['has_new_addons']);
            $this->assertNull($result['new_addons']);
        } finally {
            $booking->cleanup();
        }
    }

    public function testOldAddonsOnlyPopulatesLegacyFieldsAndFlags(): void
    {
        $booking = (new CreateABooking())->create();
        $now = Carbon::now();

        $packageForm = new TourPackageForm();
        $packageForm->form_name = 'Legacy Checkout Form ' . uniqid();
        $packageForm->form_package_id = $booking->packageId;
        $packageForm->status = 'active';
        $packageForm->description = 'Optional lunch and gear add-ons';
        $packageForm->form_fields = json_encode([]);
        $packageForm->save();

        $submission = new TourBookingForms();
        $submission->tour_package_form_id = $packageForm->id;
        $submission->tour_booking_id = $booking->bookingId;
        $submission->form_submission = json_encode([
            ['form_id' => $packageForm->id, 'priceInfo' => ['price' => '15', 'fee' => 0.75]],
        ]);
        $submission->save();

        try {
            $result = $this->repo->getSubmittedCustomForm($booking->bookingId);

            $this->assertEquals($packageForm->id, $result['form_id']);
            $this->assertEquals($booking->bookingId, $result['booking_id']);
            $this->assertEquals('Optional lunch and gear add-ons', $result['description']);
            $this->assertCount(1, $result['fields']);
            $this->assertEquals('15', $result['fields'][0]->priceInfo->price);
            $this->assertEquals(0.75, $result['fields'][0]->priceInfo->fee);
            $this->assertEquals(0, $result['fields'][0]->booking_addon_id);

            $this->assertTrue($result['has_old_addons']);
            $this->assertFalse($result['has_new_addons']);
            $this->assertNull($result['new_addons']);
        } finally {
            TourBookingForms::where('tour_booking_id', $booking->bookingId)->delete();
            TourPackageForm::where('id', $packageForm->id)->delete();
            $booking->cleanup();
        }
    }

    public function testNewAddonsOnlyPopulatesNewAddonsAndFlags(): void
    {
        $booking = (new CreateABooking())->create();
        $now = Carbon::now();

        $addonForm = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'New Add-ons Form ' . uniqid(),
            'description' => 'New system add-ons',
            'status' => 'active',
        ]);

        $addonField = AddonField::create([
            'form_id' => $addonForm->id,
            'label' => 'T-Shirt',
            'type' => 'checkbox',
            'required' => false,
            'unit_type' => 'one_time',
            'status' => 'active',
            'additional_fee' => true,
            'price' => 20.00,
            'sort_order' => 1,
        ]);

        $bookingAddonField = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId,
            'form_id' => $addonForm->id,
            'field_id' => $addonField->id,
            'field_label' => 'T-Shirt',
            'unit_type' => 'one_time',
            'unit' => 1,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        $transaction = TourBookingAddonTransaction::create([
            'tour_booking_addon_id' => $bookingAddonField->id,
            'tour_booking_id' => $booking->bookingId,
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'field_price' => 20.00,
            'field_fee' => 1.00,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        try {
            $result = $this->repo->getSubmittedCustomForm($booking->bookingId);

            $this->assertNull($result['form_id']);
            $this->assertNull($result['booking_id']);
            $this->assertNull($result['fields']);
            $this->assertNull($result['description']);

            $this->assertFalse($result['has_old_addons']);
            $this->assertTrue($result['has_new_addons']);
            $this->assertNotNull($result['new_addons']);

            $addons = $result['new_addons']['addons'];
            $this->assertCount(1, $addons);
            $this->assertEquals('T-Shirt', $addons[0]['field_label']);
            $this->assertEquals(20.00, (float) $addons[0]['transaction']['field_price']);
            $this->assertEquals(1.00, (float) $addons[0]['transaction']['field_fee']);

            $this->assertCount(0, $result['new_addons']['pax_addons']);
            $this->assertEquals(0.0, $result['new_addons']['retained_fees']);
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $bookingAddonField->id)->forceDelete();
            TourBookingAddonField::where('tour_booking_id', $booking->bookingId)->forceDelete();
            AddonField::where('id', $addonField->id)->forceDelete();
            AddonForm::where('id', $addonForm->id)->forceDelete();
            $booking->cleanup();
        }
    }

    public function testBothOldAndNewAddonsArePresentTogether(): void
    {
        $booking = (new CreateABooking())->create();
        $now = Carbon::now();

        // -- old system --
        $packageForm = new TourPackageForm();
        $packageForm->form_name = 'Legacy Checkout Form ' . uniqid();
        $packageForm->form_package_id = $booking->packageId;
        $packageForm->status = 'active';
        $packageForm->description = 'Legacy add-ons';
        $packageForm->form_fields = json_encode([]);
        $packageForm->save();

        $submission = new TourBookingForms();
        $submission->tour_package_form_id = $packageForm->id;
        $submission->tour_booking_id = $booking->bookingId;
        $submission->form_submission = json_encode([
            ['form_id' => $packageForm->id, 'priceInfo' => ['price' => '10', 'fee' => 0.5]],
        ]);
        $submission->save();

        // -- new system --
        $addonForm = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'New Add-ons Form ' . uniqid(),
            'description' => 'New system add-ons',
            'status' => 'active',
        ]);

        $addonField = AddonField::create([
            'form_id' => $addonForm->id,
            'label' => 'Camera Rental',
            'type' => 'checkbox',
            'required' => false,
            'unit_type' => 'one_time',
            'status' => 'active',
            'additional_fee' => true,
            'price' => 40.00,
            'sort_order' => 1,
        ]);

        $bookingAddonField = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId,
            'form_id' => $addonForm->id,
            'field_id' => $addonField->id,
            'field_label' => 'Camera Rental',
            'unit_type' => 'one_time',
            'unit' => 1,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_addon_id' => $bookingAddonField->id,
            'tour_booking_id' => $booking->bookingId,
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'field_price' => 40.00,
            'field_fee' => 2.00,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        try {
            $result = $this->repo->getSubmittedCustomForm($booking->bookingId);

            // Old-system fields are unaffected by the new-system data being present.
            $this->assertEquals($packageForm->id, $result['form_id']);
            $this->assertEquals($booking->bookingId, $result['booking_id']);
            $this->assertCount(1, $result['fields']);
            $this->assertTrue($result['has_old_addons']);

            // New-system data is present alongside it, cleanly separated.
            $this->assertTrue($result['has_new_addons']);
            $this->assertCount(1, $result['new_addons']['addons']);
            $this->assertEquals('Camera Rental', $result['new_addons']['addons'][0]['field_label']);
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $bookingAddonField->id)->forceDelete();
            TourBookingAddonField::where('tour_booking_id', $booking->bookingId)->forceDelete();
            AddonField::where('id', $addonField->id)->forceDelete();
            AddonForm::where('id', $addonForm->id)->forceDelete();
            TourBookingForms::where('tour_booking_id', $booking->bookingId)->delete();
            TourPackageForm::where('id', $packageForm->id)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Each pax group must carry three complementary identifiers: pax_id (the exact
     * tour_bookings_transact.id -- lets the app join this group to its rate-group row in
     * v1/booking's bookingT[]), person_ordinal (which numbered traveler within that rate
     * group -- "Adult #2"), and pax_index (an opaque per-person token, not a reliable
     * ordinal on its own). pax_id/person_ordinal must also be present on each addon node.
     */
    public function testPaxAddonsIncludePaxIdAndPersonOrdinalAlongsidePaxIndex(): void
    {
        $booking = (new CreateABooking())->withTourists(2)->create();
        $now = Carbon::now();

        $addonForm = AddonForm::create([
            'tour_package_id' => $booking->packageId,
            'name' => 'Pax Add-ons Form ' . uniqid(),
            'description' => 'Per-traveler add-ons',
            'status' => 'active',
        ]);

        $addonField = AddonField::create([
            'form_id' => $addonForm->id,
            'label' => 'T-Shirt Size',
            'type' => 'dropdown',
            'required' => false,
            'unit_type' => 'price_per_pax',
            'status' => 'active',
            'additional_fee' => true,
            'price' => 20.00,
            'sort_order' => 1,
        ]);

        // Second traveler (person_ordinal = 1, 0-based) in the same rate group picks a size.
        $bookingAddonField = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId,
            'form_id' => $addonForm->id,
            'field_id' => $addonField->id,
            'field_label' => 'T-Shirt Size',
            'pax_id' => $booking->transactId,
            'pax_index' => 'row-uuid-second-pax',
            'person_ordinal' => 1,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        TourBookingAddonTransaction::create([
            'tour_booking_addon_id' => $bookingAddonField->id,
            'tour_booking_id' => $booking->bookingId,
            'payment_method' => 'card',
            'status' => 'paid',
            'operation' => 'charge',
            'field_price' => 20.00,
            'field_fee' => 1.00,
            'option_price' => 0,
            'option_fee' => 0,
            'operation_at' => $now,
            'operation_by' => 1,
            'operation_by_user_type' => 'Admin',
        ]);

        try {
            $result = $this->repo->getSubmittedCustomForm($booking->bookingId);

            $this->assertTrue($result['has_new_addons']);
            $paxAddons = $result['new_addons']['pax_addons'];
            $this->assertCount(1, $paxAddons);

            $group = $paxAddons[0];
            $this->assertEquals($booking->transactId, $group['pax_id']);
            $this->assertEquals('row-uuid-second-pax', $group['pax_index']);
            $this->assertEquals(1, $group['person_ordinal']);

            $node = $group['addons'][0];
            $this->assertEquals($booking->transactId, $node['pax_id']);
            $this->assertEquals('row-uuid-second-pax', $node['pax_index']);
            $this->assertEquals(1, $node['person_ordinal']);
        } finally {
            TourBookingAddonTransaction::where('tour_booking_addon_id', $bookingAddonField->id)->forceDelete();
            TourBookingAddonField::where('tour_booking_id', $booking->bookingId)->forceDelete();
            AddonField::where('id', $addonField->id)->forceDelete();
            AddonForm::where('id', $addonForm->id)->forceDelete();
            $booking->cleanup();
        }
    }
}
