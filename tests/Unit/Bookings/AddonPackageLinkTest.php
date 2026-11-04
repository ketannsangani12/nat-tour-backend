<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourPackage;
use App\Repositories\Addon\AddonFormRepository;
use App\Services\Frontend\TourBookingAddonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Covers linking an add-on field to another whole tour package ("sell package B
 * as an add-on to package A"): the eligible-packages dropdown query (only
 * include_in_addons=1 + Active, excluding the package being edited), persistence
 * + inheritance on the add-on definition, and the booking-time snapshot on
 * tour_booking_addon_fields for category-wise/package-wise reporting.
 */
class AddonPackageLinkTest extends TestCase
{
    protected static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            DB::table('addon_field_rule_children')->whereIn('child_field_id', $fieldIds)->delete();
            DB::table('addon_field_rules')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_field_options')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }

        parent::tearDownAfterClass();
    }

    protected function makeActivePackageId(): int
    {
        return (int) (DB::table('tour_packages')->where('status', 'Active')->value('id')
            ?? (new CreateABooking())->create()->packageId);
    }

    public function test_get_addon_packages_only_returns_include_in_addons_flagged_active_packages_excluding_self(): void
    {
        $packageA = TourPackage::findOrFail($this->makeActivePackageId());
        $originalFlag = $packageA->include_in_addons;

        $bookingB = (new CreateABooking())->withNewPackage()->create();
        $packageB = TourPackage::findOrFail($bookingB->packageId);

        try {
            $packageA->fill(['include_in_addons' => 1])->save();
            $packageB->fill(['include_in_addons' => 1])->save();

            $repository = new AddonFormRepository();
            $response = json_decode($repository->getAddonPackages($packageA->id)->getContent(), true);
            $ids = collect($response['packages'])->pluck('id')->all();

            $this->assertTrue($response['success']);
            $this->assertContains($packageB->id, $ids, 'An eligible, different, include_in_addons package must appear');
            $this->assertNotContains($packageA->id, $ids, 'The package currently being edited must never link to itself');
        } finally {
            $packageA->fill(['include_in_addons' => $originalFlag])->save();
            $bookingB->cleanup();
        }
    }

    public function test_package_link_persists_on_top_level_field_and_inherits_to_children_when_fetched(): void
    {
        $packageId = $this->makeActivePackageId();
        $linkedPackageId = $packageId; // any valid tour_packages row works for the FK

        $repository = new AddonFormRepository();

        $request = Request::create('/', 'POST', [
            'tour_package_id' => $packageId,
            'name' => 'Package Link Test Form ' . uniqid(),
            'status' => 'Active',
            'fields' => [
                [
                    'label' => 'Parent Field',
                    'type' => 'radio',
                    'unit_type' => 'one_time',
                    'package_id' => $linkedPackageId,
                    'status' => 'Active',
                    'visibility' => 'both',
                    'options' => [
                        ['label' => 'Yes', 'value' => 'yes'],
                    ],
                    'rules' => [
                        [
                            'operator' => 'equals',
                            'value' => 'yes',
                            'children' => [
                                [
                                    'label' => 'Child Field',
                                    'type' => 'text',
                                    // no package_id here — must inherit the parent's linked package
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = json_decode($repository->saveAddonForm($request)->getContent(), true);
        $formId = $response['form']['id'];
        static::$createdFormIds[] = $formId;

        $this->assertEquals($linkedPackageId, AddonField::where('form_id', $formId)->whereNull('parent_field_id')->value('package_id'), 'Top-level field must persist its own package_id');

        $fetchedForm = $repository->getAddonForm($packageId, $formId, 1);
        $parentNode = $fetchedForm->fields[0];

        $this->assertEquals($linkedPackageId, $parentNode['package_id']);
        $this->assertNotNull($parentNode['package_name']);

        $childNode = $parentNode['rules'][0]['children'][0];
        $this->assertEquals($linkedPackageId, $childNode['package_id'], 'Child field must inherit the parent field linked package when read back');
        $this->assertEquals($parentNode['package_name'], $childNode['package_name']);
    }

    public function test_package_link_snapshot_is_persisted_on_booking_addon_fields(): void
    {
        $packageId = $this->makeActivePackageId();
        $linkedPackage = TourPackage::findOrFail($packageId);

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Package Link Booking Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $parentField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Parent Field',
            'type' => 'text',
            'unit_type' => 'one_time',
            'package_id' => $linkedPackage->id,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        $childField = AddonField::create([
            'form_id' => $form->id,
            'parent_field_id' => $parentField->id,
            'label' => 'Child Field',
            'type' => 'text',
            'sort_order' => 0,
        ]);

        $booking = (new CreateABooking())->create();
        $bookingId = $booking->bookingId;

        $formPayload = [
            [
                'form_id' => $form->id,
                'field_id' => $parentField->id,
                'field_label' => 'Parent Field',
                'type' => 'text',
                'value' => 'yes',
                'field_unit_type' => 'one_time',
                'package_id' => $linkedPackage->id,
                'package_name' => $linkedPackage->name,
                'priceInfo' => ['enabled' => false],
                'children' => [
                    [
                        'form_id' => $form->id,
                        'field_id' => $childField->id,
                        'field_label' => 'Child Field',
                        'type' => 'text',
                        'value' => 'child value',
                        'priceInfo' => ['enabled' => false],
                        // no package_id/package_name here — must inherit from parent
                    ],
                ],
            ],
        ];

        try {
            TourBookingAddonService::saveFormAddons($formPayload, $bookingId);

            $parentRow = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $parentField->id)
                ->first();
            $this->assertNotNull($parentRow);
            $this->assertEquals($linkedPackage->id, $parentRow->package_id);
            $this->assertEquals($linkedPackage->name, $parentRow->package_name);

            $childRow = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $childField->id)
                ->first();
            $this->assertNotNull($childRow);
            $this->assertEquals($linkedPackage->id, $childRow->package_id, 'Child row must inherit the linked package so package-wise reports need no parent_id joins');
            $this->assertEquals($linkedPackage->name, $childRow->package_name);
        } finally {
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
