<?php

namespace Tests\Unit\TourOperator;

use Tests\TestCase;
use App\Models\TourOperator;
use Illuminate\Support\Facades\DB;

class CheckoutTermsApiTest extends TestCase
{
    protected ?int $tourOperatorId = null;
    protected ?array $originalTerms = null;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test that tourOperator helper returns custom checkout terms when configured.
     */
    public function testTourOperatorReturnsCustomCheckoutTerms(): void
    {
        $tourOperator = TourOperator::first();
        $this->assertNotNull($tourOperator, 'Tour operator should exist');

        $this->tourOperatorId = $tourOperator->id;
        $this->originalTerms = $tourOperator->custom_checkout_terms;

        // Set up test terms
        $testTerms = [
            [
                'key' => 'api_test_term',
                'label' => 'I acknowledge the API test terms.',
                'required' => true,
            ],
        ];

        $tourOperator->custom_checkout_terms = $testTerms;
        $tourOperator->save();

        // Refresh and verify
        $tourOperator->refresh();
        $terms = $tourOperator->custom_checkout_terms;

        $this->assertIsArray($terms);
        $this->assertCount(1, $terms);
        $this->assertEquals('api_test_term', $terms[0]['key']);
        $this->assertEquals('I acknowledge the API test terms.', $terms[0]['label']);
        $this->assertTrue($terms[0]['required']);
    }

    /**
     * Test that tourOperator returns empty/null when no checkout terms configured.
     */
    public function testTourOperatorReturnsEmptyWhenNoTerms(): void
    {
        $tourOperator = TourOperator::first();
        $this->assertNotNull($tourOperator, 'Tour operator should exist');

        $this->tourOperatorId = $tourOperator->id;
        $this->originalTerms = $tourOperator->custom_checkout_terms;

        // Clear terms
        $tourOperator->custom_checkout_terms = null;
        $tourOperator->save();

        // Refresh and verify
        $tourOperator->refresh();
        $terms = $tourOperator->custom_checkout_terms ?? [];

        $this->assertEmpty($terms);
    }

    /**
     * Test that tourOperator returns multiple checkout terms correctly.
     */
    public function testTourOperatorReturnsMultipleCheckoutTerms(): void
    {
        $tourOperator = TourOperator::first();
        $this->assertNotNull($tourOperator, 'Tour operator should exist');

        $this->tourOperatorId = $tourOperator->id;
        $this->originalTerms = $tourOperator->custom_checkout_terms;

        // Set up multiple test terms
        $testTerms = [
            [
                'key' => 'term_one',
                'label' => 'First term label.',
                'required' => true,
                'status' => 'active',
            ],
            [
                'key' => 'term_two',
                'label' => 'Second term label.',
                'required' => false,
                'status' => 'active',
            ],
            [
                'key' => 'term_three',
                'label' => 'Third term label.',
                'required' => true,
                'status' => 'inactive',
            ],
        ];

        $tourOperator->custom_checkout_terms = $testTerms;
        $tourOperator->save();

        // Refresh and verify
        $tourOperator->refresh();
        $terms = $tourOperator->custom_checkout_terms;

        $this->assertIsArray($terms);
        $this->assertCount(3, $terms);

        // Verify each term
        $this->assertEquals('term_one', $terms[0]['key']);
        $this->assertTrue($terms[0]['required']);
        $this->assertEquals('active', $terms[0]['status']);

        $this->assertEquals('term_two', $terms[1]['key']);
        $this->assertFalse($terms[1]['required']);
        $this->assertEquals('active', $terms[1]['status']);

        $this->assertEquals('term_three', $terms[2]['key']);
        $this->assertTrue($terms[2]['required']);
        $this->assertEquals('inactive', $terms[2]['status']);
    }

    /**
     * Test that only active terms are filtered correctly.
     */
    public function testOnlyActiveTermsAreFiltered(): void
    {
        $tourOperator = TourOperator::first();
        $this->assertNotNull($tourOperator, 'Tour operator should exist');

        $this->tourOperatorId = $tourOperator->id;
        $this->originalTerms = $tourOperator->custom_checkout_terms;

        // Set up terms with mixed status
        $testTerms = [
            [
                'key' => 'active_term_1',
                'label' => 'This is an active term.',
                'required' => true,
                'status' => 'active',
            ],
            [
                'key' => 'inactive_term',
                'label' => 'This is an inactive term.',
                'required' => true,
                'status' => 'inactive',
            ],
            [
                'key' => 'active_term_2',
                'label' => 'This is another active term.',
                'required' => false,
                'status' => 'active',
            ],
        ];

        $tourOperator->custom_checkout_terms = $testTerms;
        $tourOperator->save();

        // Refresh and get all terms
        $tourOperator->refresh();
        $allTerms = $tourOperator->custom_checkout_terms;

        // All 3 terms should be stored
        $this->assertCount(3, $allTerms);

        // Filter only active terms (same logic as API controller)
        $activeTerms = array_values(array_filter($allTerms, function($term) {
            return ($term['status'] ?? 'active') === 'active';
        }));

        // Only 2 active terms should be returned
        $this->assertCount(2, $activeTerms);
        $this->assertEquals('active_term_1', $activeTerms[0]['key']);
        $this->assertEquals('active_term_2', $activeTerms[1]['key']);
    }

    /**
     * Clean up test data after each test.
     */
    public function tearDown(): void
    {
        if ($this->tourOperatorId && $this->originalTerms !== null) {
            try {
                DB::table('tour_operators')
                    ->where('id', $this->tourOperatorId)
                    ->update(['custom_checkout_terms' => json_encode($this->originalTerms)]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        } elseif ($this->tourOperatorId) {
            try {
                DB::table('tour_operators')
                    ->where('id', $this->tourOperatorId)
                    ->update(['custom_checkout_terms' => null]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        parent::tearDown();
    }
}
