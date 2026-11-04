<?php

namespace Tests\Unit\Reports;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Repositories\Report\ReportRepository;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Covers the Add-ons Package Report / Details Report rebuild against the
 * relational add-ons schema (addon_fields/tour_booking_addon_fields/
 * tour_booking_addon_transactions), which the report never queried before -
 * it only read the legacy tour_package_form/tour_booking_forms JSON tables.
 *
 * Specifically verifies:
 *  - parent/child (nested) add-ons are grouped, not flattened
 *  - a field is badged "priced" if either its own additional_fee flag is set,
 *    OR it actually generated money in range (covers option-level pricing on
 *    a field whose own additional_fee is false)
 *  - Paid/Refunded/Net are computed correctly even when a refund transaction
 *    row stores its amount with a negative sign (a real, inconsistent
 *    convention confirmed against production data - some refund rows are
 *    negative deltas, others are positive magnitudes)
 */
class AddonsPackageReportNewSchemaTest extends TestCase
{
    protected static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            $addonRowIds = DB::table('tour_booking_addon_fields')->whereIn('field_id', $fieldIds)->pluck('id');
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_addon_id', $addonRowIds)->delete();
            DB::table('tour_booking_addon_fields')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_field_options')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }

        parent::tearDownAfterClass();
    }

    /** Calls the protected new-schema query method directly via reflection. */
    protected function invokeNewSchemaReport(ReportRepository $repo, $request, string $fromDate, string $toDate, bool $includeBookingIds)
    {
        $method = new ReflectionMethod($repo, 'addonsPackageReportFromNewSchema');
        $method->setAccessible(true);

        return $method->invoke($repo, $request, $fromDate, $toDate, $includeBookingIds);
    }

    public function test_summary_report_groups_nested_addons_and_computes_paid_refunded_net(): void
    {
        // An isolated future date + a fresh package keeps this test's totals
        // completely separate from any real production data in the same DB.
        $testDate = '2029-06-15';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Report Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $parentField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Report Test Parent (Priced) ' . uniqid(),
            'type' => 'text',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 40.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $childField = AddonField::create([
            'form_id' => $form->id,
            'parent_field_id' => $parentField->id,
            'label' => 'Report Test Child (Free) ' . uniqid(),
            'type' => 'text',
            'unit_type' => 'one_time',
            'additional_fee' => false,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            // Parent instance 1: a single $40 paid charge.
            $parentInstance1 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $parentField->id,
                'field_label' => $parentField->label,
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $parentInstance1->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 40.00,
                'option_price' => 0,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Parent instance 2: a $40 paid charge, partially refunded $15 -
            // stored as a NEGATIVE field_price on the refund row, matching one
            // of the two sign conventions confirmed in production data.
            $parentInstance2 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $parentField->id,
                'field_label' => $parentField->label,
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                [
                    'tour_booking_addon_id' => $parentInstance2->id,
                    'tour_booking_id' => $bookingId,
                    'status' => 'paid',
                    'operation' => 'charge',
                    'field_price' => 40.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'tour_booking_addon_id' => $parentInstance2->id,
                    'tour_booking_id' => $bookingId,
                    'status' => 'refunded',
                    'operation' => 'refund',
                    'field_price' => -15.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Child instance: selected once, never priced, no transaction row at all.
            TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $childField->id,
                'parent_id' => $parentInstance1->id,
                'field_label' => $childField->label,
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);

            $parentRow = collect($rows)->firstWhere('field_id', $parentField->id);
            $this->assertNotNull($parentRow, 'Parent field must appear as a top-level row');

            $this->assertTrue($parentRow['is_priced'], 'additional_fee=true must badge as priced');
            $this->assertEquals(1, $parentRow['booking_count'], 'Both instances belong to the same booking - booking_count counts distinct bookings, not instances');
            $this->assertEquals(80.00, $parentRow['paid_total'], 'Two $40 paid charges must sum to $80');
            $this->assertEquals(15.00, $parentRow['refunded_total'], 'A negative-signed refund row must be normalized to a positive magnitude, not left negative');
            $this->assertEquals(65.00, $parentRow['net_total'], 'Net must be paid minus the refund MAGNITUDE (80 - 15 = 65), never inflated by a negative-signed refund (which would wrongly give 80 - (-15) = 95)');
            $this->assertEquals(40.00, $parentRow['price'], 'Average per-instance price (80 / 2 instances) must match the configured $40 price');

            $this->assertCount(1, $parentRow['children'], 'The child field must be nested under its parent, not listed as a separate top-level row');
            $childRow = $parentRow['children'][0];
            $this->assertEquals($childField->id, $childRow['field_id']);
            $this->assertFalse($childRow['is_priced'], 'additional_fee=false with zero observed revenue must badge as free');
            $this->assertEquals(0.0, $childRow['paid_total']);
            $this->assertEquals(0.0, $childRow['net_total']);

            // The child field must never appear again as its own top-level row.
            $this->assertNull(collect($rows)->firstWhere('field_id', $childField->id), 'Child field must not also appear as a top-level row');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_price_averages_only_paid_instances_not_uncharged_ones(): void
    {
        // Regression case for a real report bug: a $25 flat-priced add-on had
        // 15 selections but only 14 were ever actually charged (one instance had
        // no transaction row at all - comped/never paid). Averaging paid_total
        // over ALL 15 instances gave a wrong $23.33 instead of the real $25.
        $testDate = '2029-06-17';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Uncharged Instance Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Flat Priced Field ' . uniqid(),
            'type' => 'text',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 25.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            // Instance 1: charged the full $25.
            $paidInstance = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $paidInstance->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 25.00,
                'option_price' => 0,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Instance 2: selected but never charged - no transaction row at all.
            TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);
            $row = collect($rows)->firstWhere('field_id', $field->id);

            $this->assertNotNull($row);
            $this->assertEquals(25.00, $row['paid_total'], 'Only the one paid instance contributes to paid_total');
            $this->assertEquals(25.00, $row['price'], 'Price must equal the true $25 charge, not paid_total (25) divided by all 2 instances (=12.50) nor diluted in any other way by the uncharged instance');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_price_shows_configured_unit_price_not_an_average_skewed_by_quantity(): void
    {
        // Regression case for a real report bug: a price_per_unit add-on
        // configured at $3/unit ("Extra Water Bottles") was showing $6.64
        // because paid_total (which scales with quantity: unit_price * units
        // purchased) was averaged over paid instance COUNT, blending in
        // whatever quantities customers happened to buy - not the per-unit
        // price at all. The field's own configured price must be used
        // directly instead of any derived average.
        $testDate = '2029-06-18';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Per Unit Pricing Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Per Unit Priced Field ' . uniqid(),
            'type' => 'number',
            'unit_type' => 'price_per_unit',
            'additional_fee' => true,
            'price' => 3.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            // Instance 1: bought 1 unit -> charged $3.
            $instance1 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'unit_type' => 'price_per_unit',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance1->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 3.00,
                'option_price' => 0,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Instance 2: bought 4 units -> charged $12 (still $3/unit, but a
            // naive dollars-per-instance average would be dragged toward this
            // larger purchase).
            $instance2 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'unit_type' => 'price_per_unit',
                'unit' => 4,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance2->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 12.00,
                'option_price' => 0,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);
            $row = collect($rows)->firstWhere('field_id', $field->id);

            $this->assertNotNull($row);
            $this->assertEquals(15.00, $row['paid_total'], 'Both instances (3 + 12) sum correctly regardless of the Price column bug');
            $this->assertEquals(3.00, $row['price'], 'Price must be the configured $3/unit price, not paid_total (15) divided by 2 paid instances (=7.50)');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_is_priced_reflects_actual_option_level_revenue_even_when_field_flag_is_false(): void
    {
        // Mirrors a real case found on production data: a field with
        // additional_fee=false whose radio/dropdown OPTIONS carry their own
        // price, so the field genuinely generates revenue despite its own
        // flag saying "not priced".
        $testDate = '2029-06-16';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Option Priced Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Field With Priced Options ' . uniqid(),
            'type' => 'dropdown',
            'unit_type' => 'one_time',
            'additional_fee' => false,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            $instance = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'option_label' => 'Large',
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 0,
                'option_price' => 22.00,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);
            $row = collect($rows)->firstWhere('field_id', $field->id);

            $this->assertNotNull($row);
            $this->assertTrue($row['is_priced'], 'A field with additional_fee=false must still badge as priced once its priced option actually generated revenue');
            $this->assertEquals(22.00, $row['paid_total']);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_price_shows_uniform_option_price_when_every_priced_option_costs_the_same(): void
    {
        // Regression case for a real report bug: "For transportation service,
        // please select hotel/lodging" prices every hotel option at exactly
        // $20, yet the report showed $20.56 - one booking's transaction
        // happened to cover 2 travelers ($40) in a single row, which dragged
        // a dollars-charged average away from the real, uniform $20 price.
        // The field's options all configuring the same price must be trusted
        // over any transaction-derived average.
        $testDate = '2029-06-19';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Uniform Option Pricing Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Hotel Selection ' . uniqid(),
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => false,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Self Transfer', 'value' => 'self', 'additional_fee' => false, 'price' => null, 'sort_order' => 0]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Hotel A', 'value' => 'a', 'additional_fee' => true, 'price' => 20.00, 'sort_order' => 1]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Hotel B', 'value' => 'b', 'additional_fee' => true, 'price' => 20.00, 'sort_order' => 2]);

        try {
            // A regular single-traveler selection: $20.
            $instance1 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'option_label' => 'Hotel A',
                'unit_type' => 'price_per_pax',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance1->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 0,
                'option_price' => 20.00,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // A row that bundled 2 travelers' selections into one charge: $40
            // total, still $20/traveler - exactly the real-world case found.
            $instance2 = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'option_label' => 'Hotel B',
                'unit_type' => 'price_per_pax',
                'unit' => 2,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance2->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 0,
                'option_price' => 40.00,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);
            $row = collect($rows)->firstWhere('field_id', $field->id);

            $this->assertNotNull($row);
            $this->assertEquals(60.00, $row['paid_total'], 'Both instances (20 + 40) sum correctly');
            $this->assertEquals(20.00, $row['price'], 'Price must be the uniform $20 configured on every priced option, not paid_total (60) divided by 2 paid instances (=30)');
            $this->assertNull($row['price_label'], 'A single uniform price needs no range label');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_price_shows_range_label_when_options_are_priced_differently(): void
    {
        // "Guided Language Preference" (Spanish $5, Navajo $10) has no single
        // correct price - showing a blended average (e.g. $7.14) would be a
        // number matching no real charge, exactly the class of bug already
        // fixed for uniform-priced fields. A range label is the honest option.
        $testDate = '2029-06-20';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Varying Option Pricing Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Language Preference ' . uniqid(),
            'type' => 'dropdown',
            'unit_type' => 'one_time',
            'additional_fee' => false,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'English', 'value' => 'en', 'additional_fee' => false, 'price' => null, 'sort_order' => 0]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Spanish', 'value' => 'es', 'additional_fee' => true, 'price' => 5.00, 'sort_order' => 1]);
        AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Navajo', 'value' => 'nv', 'additional_fee' => true, 'price' => 10.00, 'sort_order' => 2]);

        try {
            $instance = TourBookingAddonField::create([
                'tour_booking_id' => $bookingId,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'option_label' => 'Navajo',
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);
            DB::table('tour_booking_addon_transactions')->insert([
                'tour_booking_addon_id' => $instance->id,
                'tour_booking_id' => $bookingId,
                'status' => 'paid',
                'operation' => 'charge',
                'field_price' => 0,
                'option_price' => 10.00,
                'operation_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->selectedPackage = $packageId;
            $request->addOnName = '';

            $rows = $this->invokeNewSchemaReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59', false);
            $row = collect($rows)->firstWhere('field_id', $field->id);

            $this->assertNotNull($row);
            $this->assertEquals('$5.00 – $10.00', $row['price_label'], 'Differently-priced options must show a range, not a blended average matching neither real price');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
