<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Repositories\Addon\AddonFormRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: a field's `visibility` ('both'/'frontend'/'backend') controls whether it should be
 * offered on the customer-facing site (tour-frontend, via API V2's getAddonForm -> here with
 * forFrontend=true) versus admin/staff/affiliate booking screens (Common\AddonFormController ->
 * here with forFrontend=false, the default). Both are reached with $builder=0 — the only signal
 * distinguishing them is $forFrontend — so before this fix, a 'frontend'-only field was silently
 * dropped from EVERY non-builder caller, including the customer site it was meant for, because
 * the single shared filter only ever allowed ('both','backend').
 */
class AddonFormVisibilityTest extends TestCase
{
    protected static array $createdFormIds = [];

    public static function tearDownAfterClass(): void
    {
        if (!empty(static::$createdFormIds)) {
            $fieldIds = DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->pluck('id');
            DB::table('addon_field_options')->whereIn('field_id', $fieldIds)->delete();
            DB::table('addon_fields')->whereIn('form_id', static::$createdFormIds)->delete();
            DB::table('addon_forms')->whereIn('id', static::$createdFormIds)->delete();
        }

        parent::tearDownAfterClass();
    }

    protected function makePackageId(): int
    {
        return (int) (DB::table('tour_packages')->where('status', 'Active')->value('id')
            ?? (new CreateABooking())->create()->packageId);
    }

    public function test_frontend_and_backend_only_fields_are_shown_to_the_matching_caller_only(): void
    {
        $packageId = $this->makePackageId();
        $repository = new AddonFormRepository();

        $request = Request::create('/', 'POST', [
            'tour_package_id' => $packageId,
            'name' => 'Visibility Test Form ' . uniqid(),
            'status' => 'Active',
            'fields' => [
                [
                    'label' => 'Frontend Only Field',
                    'type' => 'text',
                    'unit_type' => 'one_time',
                    'status' => 'Active',
                    'visibility' => 'frontend',
                ],
                [
                    'label' => 'Backend Only Field',
                    'type' => 'text',
                    'unit_type' => 'one_time',
                    'status' => 'Active',
                    'visibility' => 'backend',
                ],
                [
                    'label' => 'Both Field',
                    'type' => 'text',
                    'unit_type' => 'one_time',
                    'status' => 'Active',
                    'visibility' => 'both',
                ],
            ],
        ]);

        $response = json_decode($repository->saveAddonForm($request)->getContent(), true);
        $formId = $response['form']['id'];
        static::$createdFormIds[] = $formId;

        $adminView = $repository->getAddonForm($packageId, $formId, 0, forFrontend: false);
        $adminLabels = collect($adminView->fields)->pluck('label')->all();
        $this->assertContains('Backend Only Field', $adminLabels, 'Admin/staff booking screens must still see backend-only fields');
        $this->assertContains('Both Field', $adminLabels);
        $this->assertNotContains('Frontend Only Field', $adminLabels, 'Admin/staff booking screens must not see frontend-only fields');

        $customerView = $repository->getAddonForm($packageId, $formId, 0, forFrontend: true);
        $customerLabels = collect($customerView->fields)->pluck('label')->all();
        $this->assertContains('Frontend Only Field', $customerLabels, 'The customer-facing site must see frontend-only fields — this is the reported bug');
        $this->assertContains('Both Field', $customerLabels);
        $this->assertNotContains('Backend Only Field', $customerLabels, 'The customer-facing site must not see backend-only fields');
    }

    public function test_forfrontend_defaults_to_admin_behavior_when_omitted(): void
    {
        $packageId = $this->makePackageId();
        $repository = new AddonFormRepository();

        $request = Request::create('/', 'POST', [
            'tour_package_id' => $packageId,
            'name' => 'Visibility Default Test Form ' . uniqid(),
            'status' => 'Active',
            'fields' => [
                [
                    'label' => 'Frontend Only Field',
                    'type' => 'text',
                    'unit_type' => 'one_time',
                    'status' => 'Active',
                    'visibility' => 'frontend',
                ],
            ],
        ]);

        $response = json_decode($repository->saveAddonForm($request)->getContent(), true);
        $formId = $response['form']['id'];
        static::$createdFormIds[] = $formId;

        // Common\AddonFormController (admin/staff/affiliate) calls getAddonForm() without ever
        // passing $forFrontend — must keep resolving to the admin-side visibility pair.
        $defaultView = $repository->getAddonForm($packageId, $formId, 0);
        $this->assertEmpty($defaultView->fields, 'Omitting $forFrontend must preserve existing admin/staff/affiliate behavior (frontend-only field stays hidden)');
    }
}
