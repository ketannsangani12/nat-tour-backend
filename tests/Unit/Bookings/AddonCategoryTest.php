<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonCategory;
use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Repositories\Addon\AddonFormRepository;
use App\Services\Frontend\TourBookingAddonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Covers the new add-on "Category" field: static/creatable categories (addon_categories),
 * inheritance from a top-level add-on field down to its children when the form tree is
 * read back, and the resulting snapshot persisted onto tour_booking_addon_fields at
 * booking time for category-wise reporting.
 */
class AddonCategoryTest extends TestCase
{
    protected static array $createdFormIds = [];
    protected static array $createdCategoryIds = [];

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

        if (!empty(static::$createdCategoryIds)) {
            DB::table('addon_categories')->whereIn('id', static::$createdCategoryIds)->delete();
        }

        parent::tearDownAfterClass();
    }

    protected function makePackageId(): int
    {
        return (int) (DB::table('tour_packages')->where('status', 'Active')->value('id')
            ?? (new CreateABooking())->create()->packageId);
    }

    public function test_save_addon_category_is_idempotent_across_casing_and_whitespace(): void
    {
        $repository = new AddonFormRepository();
        $name = 'Water Sports ' . uniqid();

        $first = json_decode($repository->saveAddonCategory("  {$name}  ")->getContent(), true);

        try {
            $second = json_decode($repository->saveAddonCategory(strtoupper($name))->getContent(), true);

            $this->assertTrue($first['success']);
            $this->assertTrue($second['success']);
            $this->assertEquals($first['category']['id'], $second['category']['id'], 'Retyping the same name with different casing/whitespace must not create a duplicate');
            $this->assertEquals(1, AddonCategory::withTrashed()->where('name', $name)->count());
        } finally {
            static::$createdCategoryIds[] = $first['category']['id'];
        }
    }

    public function test_category_persists_on_top_level_field_and_inherits_to_children_when_fetched(): void
    {
        $packageId = $this->makePackageId();
        $category = AddonCategory::create(['name' => 'Test Category ' . uniqid(), 'status' => 'Active']);
        static::$createdCategoryIds[] = $category->id;

        $repository = new AddonFormRepository();

        $request = Request::create('/', 'POST', [
            'tour_package_id' => $packageId,
            'name' => 'Category Test Form ' . uniqid(),
            'status' => 'Active',
            'fields' => [
                [
                    'label' => 'Parent Field',
                    'type' => 'radio',
                    'unit_type' => 'one_time',
                    'category_id' => $category->id,
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
                                    // no category_id here — must inherit the parent's category
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

        $this->assertEquals($category->id, AddonField::where('form_id', $formId)->whereNull('parent_field_id')->value('category_id'), 'Top-level field must persist its own category_id');

        $fetchedForm = $repository->getAddonForm($packageId, $formId, 1);
        $parentNode = $fetchedForm->fields[0];

        $this->assertEquals($category->id, $parentNode['category_id']);
        $this->assertEquals($category->name, $parentNode['category_name']);

        $childNode = $parentNode['rules'][0]['children'][0];
        $this->assertEquals($category->id, $childNode['category_id'], 'Child field must inherit the parent field category when read back');
        $this->assertEquals($category->name, $childNode['category_name']);
    }

    public function test_category_snapshot_is_persisted_on_booking_addon_fields(): void
    {
        $packageId = $this->makePackageId();
        $category = AddonCategory::create(['name' => 'Test Category ' . uniqid(), 'status' => 'Active']);
        static::$createdCategoryIds[] = $category->id;

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Category Booking Test Form ' . uniqid(),
            'status' => 'Active',
        ]);
        static::$createdFormIds[] = $form->id;

        $parentField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Parent Field',
            'type' => 'text',
            'unit_type' => 'one_time',
            'category_id' => $category->id,
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
                'category_id' => $category->id,
                'category_name' => $category->name,
                'priceInfo' => ['enabled' => false],
                'children' => [
                    [
                        'form_id' => $form->id,
                        'field_id' => $childField->id,
                        'field_label' => 'Child Field',
                        'type' => 'text',
                        'value' => 'child value',
                        'priceInfo' => ['enabled' => false],
                        // no category_id/category here — must inherit from parent
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
            $this->assertEquals($category->id, $parentRow->category_id);
            $this->assertEquals($category->name, $parentRow->category_name);

            $childRow = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $childField->id)
                ->first();
            $this->assertNotNull($childRow);
            $this->assertEquals($category->id, $childRow->category_id, 'Child row must inherit the parent add-on category so category-wise reports need no parent_id joins');
            $this->assertEquals($category->name, $childRow->category_name);
        } finally {
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
