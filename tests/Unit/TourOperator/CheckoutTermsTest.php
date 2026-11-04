<?php

namespace Tests\Unit\TourOperator;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\TourOperator;
use App\Http\Controllers\Admin\TourOperatorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutTermsTest extends TestCase
{
    protected ?int $tourOperatorId = null;

    public function setUp(): void
    {
        parent::setUp();
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function getTourOperator(): TourOperator
    {
        $tourOperator = TourOperator::first();
        $this->assertNotNull($tourOperator, 'Tour operator should exist');
        $this->tourOperatorId = $tourOperator->id;
        return $tourOperator;
    }

    /**
     * Test that checkout terms can be saved successfully.
     */
    public function testCanSaveCheckoutTerms(): void
    {
        $this->authAsAdmin();
        $tourOperator = $this->getTourOperator();

        $terms = [
            [
                'key' => 'test_term_1',
                'label' => 'I agree to the test terms and conditions.',
                'required' => '1',
                'status' => 'active',
            ],
            [
                'key' => 'test_term_2',
                'label' => 'I acknowledge the cancellation policy.',
                'required' => '0',
                'status' => 'inactive',
            ],
        ];

        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => $terms]);

        $response = $controller->updateCheckoutTerms($request, $tourOperator->id);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify terms were saved
        $updatedOperator = TourOperator::find($tourOperator->id);
        $savedTerms = $updatedOperator->custom_checkout_terms;

        $this->assertIsArray($savedTerms);
        $this->assertCount(2, $savedTerms);
        $this->assertEquals('test_term_1', $savedTerms[0]['key']);
        $this->assertEquals('I agree to the test terms and conditions.', $savedTerms[0]['label']);
        $this->assertTrue($savedTerms[0]['required']);
        $this->assertEquals('active', $savedTerms[0]['status']);
        $this->assertEquals('test_term_2', $savedTerms[1]['key']);
        $this->assertFalse($savedTerms[1]['required']);
        $this->assertEquals('inactive', $savedTerms[1]['status']);
    }

    /**
     * Test that checkout terms key is sanitized (only lowercase, numbers, underscores).
     */
    public function testCheckoutTermsKeySanitization(): void
    {
        $this->authAsAdmin();
        $tourOperator = $this->getTourOperator();

        $terms = [
            [
                'key' => 'Test-Key With SPACES!@#',
                'label' => 'Test label',
                'required' => '1',
            ],
        ];

        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => $terms]);

        $response = $controller->updateCheckoutTerms($request, $tourOperator->id);

        $this->assertEquals(200, $response->getStatusCode());

        $updatedOperator = TourOperator::find($tourOperator->id);
        $savedTerms = $updatedOperator->custom_checkout_terms;

        // Key should be sanitized to lowercase with only allowed characters
        $this->assertEquals('testkeywithspaces', $savedTerms[0]['key']);
    }

    /**
     * Test that empty terms array clears existing terms.
     */
    public function testCanClearCheckoutTerms(): void
    {
        $this->authAsAdmin();
        $tourOperator = $this->getTourOperator();

        // First save some terms
        $tourOperator->custom_checkout_terms = [
            ['key' => 'existing_term', 'label' => 'Existing', 'required' => true],
        ];
        $tourOperator->save();

        // Now clear them
        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => []]);

        $response = $controller->updateCheckoutTerms($request, $tourOperator->id);

        $this->assertEquals(200, $response->getStatusCode());

        $updatedOperator = TourOperator::find($tourOperator->id);
        $this->assertEmpty($updatedOperator->custom_checkout_terms);
    }

    /**
     * Test that terms with empty key or label are filtered out.
     */
    public function testEmptyTermsAreFiltered(): void
    {
        $this->authAsAdmin();
        $tourOperator = $this->getTourOperator();

        $terms = [
            [
                'key' => '',
                'label' => 'Label without key',
                'required' => '1',
            ],
            [
                'key' => 'valid_key',
                'label' => '',
                'required' => '1',
            ],
            [
                'key' => 'complete_term',
                'label' => 'Complete term with all fields',
                'required' => '1',
            ],
        ];

        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => $terms]);

        $response = $controller->updateCheckoutTerms($request, $tourOperator->id);

        $this->assertEquals(200, $response->getStatusCode());

        $updatedOperator = TourOperator::find($tourOperator->id);
        $savedTerms = $updatedOperator->custom_checkout_terms;

        // Only the complete term should be saved
        $this->assertCount(1, $savedTerms);
        $this->assertEquals('complete_term', $savedTerms[0]['key']);
    }

    /**
     * Test that non-existent tour operator returns 404.
     */
    public function testUpdateCheckoutTermsReturns404ForInvalidOperator(): void
    {
        $this->authAsAdmin();

        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => []]);

        $response = $controller->updateCheckoutTerms($request, 999999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test that duplicate keys are rejected.
     */
    public function testDuplicateKeysAreRejected(): void
    {
        $this->authAsAdmin();
        $tourOperator = $this->getTourOperator();

        $terms = [
            [
                'key' => 'same_key',
                'label' => 'First term',
                'required' => '1',
                'status' => 'active',
            ],
            [
                'key' => 'same_key',
                'label' => 'Second term with same key',
                'required' => '0',
                'status' => 'active',
            ],
        ];

        $controller = new TourOperatorController();
        $request = new Request();
        $request->merge(['checkout_terms' => $terms]);

        $response = $controller->updateCheckoutTerms($request, $tourOperator->id);

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * Clean up test data after each test.
     */
    public function tearDown(): void
    {
        if ($this->tourOperatorId) {
            // Reset checkout terms to empty
            try {
                DB::table('tour_operators')
                    ->where('id', $this->tourOperatorId)
                    ->update(['custom_checkout_terms' => null]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        // Call parent tearDown which handles DB disconnection
        parent::tearDown();
    }
}
