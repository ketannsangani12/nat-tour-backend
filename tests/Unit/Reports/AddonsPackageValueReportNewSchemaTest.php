<?php

namespace Tests\Unit\Reports;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Repositories\Report\ReportRepository;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Covers the "Add-ons Package Value Report" per-instance drill-down
 * (addonsPackageValueReportFromNewSchema), which reads
 * tour_booking_addon_transactions with no status filter and no
 * tour_booking_id scoping on the join. Both gaps were confirmed to inflate
 * the "Price" column on real production data - a single $15 paid field
 * showed as $45 for booking #D-253583 (2 unrelated transaction rows
 * incorrectly summed alongside the real $15 paid charge).
 */
class AddonsPackageValueReportNewSchemaTest extends TestCase
{
    protected static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            $addonRowIds = DB::table('tour_booking_addon_fields')->whereIn('field_id', $fieldIds)->pluck('id');
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_addon_id', $addonRowIds)->delete();
            DB::table('tour_booking_addon_fields')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }

        parent::tearDownAfterClass();
    }

    /** Calls the protected new-schema value-report query method directly via reflection. */
    protected function invokeValueReport(ReportRepository $repo, $request, string $fromDate, string $toDate)
    {
        $method = new ReflectionMethod($repo, 'addonsPackageValueReportFromNewSchema');
        $method->setAccessible(true);

        return $method->invoke($repo, $request, $fromDate, $toDate);
    }

    public function test_price_ignores_open_and_unrelated_status_rows_on_the_same_instance(): void
    {
        // Regression case for the exact production bug: a single $15 "paid"
        // field-priced add-on instance also had an unrelated "open" ledger row
        // pointing at the same tour_booking_addon_id (e.g. left behind by a
        // pax-swap/edit flow). Summing field_price across every status
        // (the previous behavior) gave $30 instead of the real $15.
        $testDate = '2029-07-15';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Value Report Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package ' . uniqid(),
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 15.00,
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
                'value' => 'checked',
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);

            DB::table('tour_booking_addon_transactions')->insert([
                [
                    'tour_booking_addon_id' => $instance->id,
                    'tour_booking_id' => $bookingId,
                    'status' => 'paid',
                    'operation' => 'charge',
                    'field_price' => 15.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                // Stale/unrelated "open" row against the same addon instance -
                // must be excluded from the Price total entirely.
                [
                    'tour_booking_addon_id' => $instance->id,
                    'tour_booking_id' => $bookingId,
                    'status' => 'open',
                    'operation' => 'charge',
                    'field_price' => 15.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->fieldId = $field->id;
            $request->selectedPackage = $packageId;

            $rows = $this->invokeValueReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59');
            $row = collect($rows)->firstWhere('tour_booking_id', $bookingId);

            $this->assertNotNull($row);
            $this->assertEquals(15.00, $row['price'], 'Only the paid row may contribute to Price - the open row must not inflate it to $30');
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    public function test_price_excludes_orphaned_transaction_rows_from_a_different_booking(): void
    {
        // Regression case for the second gap behind the same production bug:
        // the join on tbat.tour_booking_addon_id = tbaf.id had no
        // tbat.tour_booking_id check, so a stale transaction row left pointing
        // at this instance's id from an unrelated booking (e.g. after a
        // pax-swap ledger repointing) would still be summed in.
        $testDate = '2029-07-16';
        $bookingA = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $bookingB = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $bookingA->packageId;
        $bookingIdA = $bookingA->bookingId;
        $bookingIdB = $bookingB->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Value Report Orphan Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Orphan Guard Field ' . uniqid(),
            'type' => 'checkbox',
            'unit_type' => 'one_time',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            $instanceA = TourBookingAddonField::create([
                'tour_booking_id' => $bookingIdA,
                'form_id' => $form->id,
                'field_id' => $field->id,
                'field_label' => $field->label,
                'value' => 'checked',
                'unit_type' => 'one_time',
                'unit' => 1,
                'operation_at' => now(),
            ]);

            DB::table('tour_booking_addon_transactions')->insert([
                [
                    'tour_booking_addon_id' => $instanceA->id,
                    'tour_booking_id' => $bookingIdA,
                    'status' => 'paid',
                    'operation' => 'charge',
                    'field_price' => 15.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                // Orphaned row: same tour_booking_addon_id, but stamped with a
                // DIFFERENT booking's id - simulates the stale-pointer scenario
                // the tour_booking_id column was added to guard against.
                [
                    'tour_booking_addon_id' => $instanceA->id,
                    'tour_booking_id' => $bookingIdB,
                    'status' => 'paid',
                    'operation' => 'charge',
                    'field_price' => 15.00,
                    'option_price' => 0,
                    'operation_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->fieldId = $field->id;
            $request->selectedPackage = $packageId;

            $rows = $this->invokeValueReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59');
            $row = collect($rows)->firstWhere('tour_booking_id', $bookingIdA);

            $this->assertNotNull($row);
            $this->assertEquals(15.00, $row['price'], 'The orphaned row stamped with a different tour_booking_id must not be summed into this booking\'s Price');
        } finally {
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_id', [$bookingIdA, $bookingIdB])->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingIdA)->delete();
            $bookingB->cleanup();
            $bookingA->cleanup();
        }
    }

    public function test_rows_for_the_same_booking_are_ordered_by_traveler_number(): void
    {
        // Regression case for a real production report: a 6-instance
        // price_per_pax selection on one booking rendered as
        // "Traveler 4, Traveler 5, Traveler 3, Traveler 2, Traveler 1, Yes"
        // because the query had no secondary ORDER BY at all beyond
        // tour_booking_id - GROUP BY's internal ordering decided row order,
        // not person_ordinal. Inserted here out of order and with a NULL
        // ordinal thrown in, to prove the query itself now sorts them,
        // rather than relying on insertion order accidentally matching.
        $testDate = '2029-07-17';
        $booking = (new CreateABooking())->withNewPackage()->withDate($testDate)->create();
        $packageId = $booking->packageId;
        $bookingId = $booking->bookingId;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Value Report Order Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Per Pax Order Field ' . uniqid(),
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 10.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            // Inserted deliberately out of order, plus one un-ordinaled row.
            foreach ([4, 5, 3, 2, 1, null] as $ordinal) {
                TourBookingAddonField::create([
                    'tour_booking_id' => $bookingId,
                    'form_id' => $form->id,
                    'field_id' => $field->id,
                    'field_label' => $field->label,
                    'value' => 'Yes',
                    'person_ordinal' => $ordinal,
                    'unit_type' => 'price_per_pax',
                    'unit' => 1,
                    'operation_at' => now(),
                ]);
            }

            $repo = new ReportRepository();
            $request = new \stdClass();
            $request->fieldId = $field->id;
            $request->selectedPackage = $packageId;

            $rows = $this->invokeValueReport($repo, $request, $testDate . ' 00:00:00', $testDate . ' 23:59:59');
            $values = collect($rows)->where('tour_booking_id', $bookingId)->pluck('value')->values()->all();

            $this->assertEquals(
                ['Yes (Traveler 1)', 'Yes (Traveler 2)', 'Yes (Traveler 3)', 'Yes (Traveler 4)', 'Yes (Traveler 5)', 'Yes'],
                $values,
                'Rows must be ordered by traveler number ascending, with the un-ordinaled row last'
            );
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $bookingId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
