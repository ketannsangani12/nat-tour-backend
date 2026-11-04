<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\DisputeResponseTemplate;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature tests for Dispute Response Template management
 * 
 * Tests the CRUD operations, template categorization, and variable substitution
 * functionality as required by Task 12.1
 * 
 * Requirements: 11.1, 11.3, 11.6
 */
class DisputeResponseTemplateTest extends TestCase
{
    use WithFaker;

    protected $admin;


    protected function setUp(): void
    {
        parent::setUp();

        // Clean up templates from previous tests since tearDown() doesn't call parent
        DisputeResponseTemplate::query()->delete();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-payment-dispute', 'guard_name' => 'admin']);

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view-payment-dispute']);

        $this->actingAs($this->admin, 'admin');

        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\DisputeSecurityMiddleware::class,
        ]);

        \Illuminate\Support\Facades\RateLimiter::clear('dispute_view|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('dispute_modify|127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('evidence_submit|127.0.0.1');
    }

    /**
     * tests/TestCase.php runs against the real tenant DB with no
     * transaction/rollback wrapper. DisputeResponseTemplateFactory's
     * `created_by` defaults to a random faker id (not $this->admin->id) for
     * any call that doesn't explicitly override it, and every test method
     * here creates templates without that override, so tracking by
     * created_by would miss them. setUp() already wipes the whole table
     * unconditionally before each test (see above) - mirror that here so the
     * table (and the admin created in setUp()) don't leak permanent rows
     * after the final test in the suite runs, since no subsequent setUp()
     * exists to clean it up.
     */
    protected function tearDown(): void
    {
        DisputeResponseTemplate::query()->delete();

        // Admin uses SoftDeletes - a normal ->delete() only sets deleted_at
        // and leaves the row in place, so use the query builder for a real
        // hard delete (same approach as TemplateAvailabilityPropertyTest.php).
        if (isset($this->admin)) {
            \Illuminate\Support\Facades\DB::table('admins')->where('id', $this->admin->id)->delete();
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_display_template_index_page()
    {
        // Create some test templates
        DisputeResponseTemplate::factory()->count(3)->create();

        $response = $this->get(route('admin.dispute-templates.index'));

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.dispute-templates.index');
        $response->assertViewHas(['templates', 'reasonStats', 'availableReasons']);
    }

    /** @test */
    public function it_can_create_a_new_template()
    {
        $templateData = [
            'name' => 'Test Fraudulent Response Template',
            'dispute_reason' => 'fraudulent',
            'template_content' => 'Dear {{customer_name}}, regarding your dispute for booking {{booking_reference}}...',
            'is_active' => true
        ];

        $response = $this->post(route('admin.dispute-templates.store'), $templateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('dispute_response_templates', [
            'name' => 'Test Fraudulent Response Template',
            'dispute_reason' => 'fraudulent',
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_template()
    {
        $response = $this->post(route('admin.dispute-templates.store'), []);

        $response->assertSessionHasErrors(['name', 'dispute_reason', 'template_content']);
    }

    /** @test */
    public function it_can_show_template_details()
    {
        $template = DisputeResponseTemplate::factory()->create();

        $response = $this->get(route('admin.dispute-templates.show', $template));

        $response->assertStatus(200);
        $response->assertViewIs('users.admin.dispute-templates.show');
        $response->assertViewHas('template');
        $response->assertSee($template->name);
    }

    /** @test */
    public function it_can_update_an_existing_template()
    {
        $template = DisputeResponseTemplate::factory()->create([
            'name' => 'Original Name',
            'dispute_reason' => 'fraudulent'
        ]);

        $updateData = [
            'name' => 'Updated Template Name',
            'dispute_reason' => 'product_not_received',
            'template_content' => 'Updated content for dispute response with {{customer_name}} template variable.',
            'is_active' => false
        ];

        $response = $this->put(route('admin.dispute-templates.update', $template), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('dispute_response_templates', [
            'id' => $template->id,
            'name' => 'Updated Template Name',
            'dispute_reason' => 'product_not_received',
            'is_active' => false
        ]);
    }

    /** @test */
    public function it_can_delete_a_template()
    {
        $template = DisputeResponseTemplate::factory()->create();

        $response = $this->delete(route('admin.dispute-templates.destroy', $template));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dispute_response_templates', [
            'id' => $template->id
        ]);
    }

    /** @test */
    public function it_can_activate_a_template()
    {
        $template = DisputeResponseTemplate::factory()->create(['is_active' => false]);

        $response = $this->post(route('admin.dispute-templates.activate', $template));

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('dispute_response_templates', [
            'id' => $template->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_deactivate_a_template()
    {
        $template = DisputeResponseTemplate::factory()->create(['is_active' => true]);

        $response = $this->post(route('admin.dispute-templates.deactivate', $template));

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('dispute_response_templates', [
            'id' => $template->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function it_can_get_templates_for_specific_dispute_reason()
    {
        // Create templates for different reasons
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'fraudulent', 'is_active' => true]);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'fraudulent', 'is_active' => true]);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'product_not_received', 'is_active' => true]);

        $response = $this->get(route('admin.dispute-templates.for-reason', ['dispute_reason' => 'fraudulent']));

        $response->assertJson(['success' => true]);
        $responseData = $response->json('data');
        $this->assertEquals('fraudulent', $responseData['dispute_reason']);
        $this->assertCount(2, $responseData['templates']);
    }

    /** @test */
    public function it_can_preview_template_with_sample_data()
    {
        $template = DisputeResponseTemplate::factory()->create([
            'template_content' => 'Hello {{customer_name}}, your booking {{booking_reference}} has been processed.'
        ]);

        $response = $this->get(route('admin.dispute-templates.preview', $template));

        $response->assertJson(['success' => true]);
        $responseData = $response->json('data');
        $this->assertArrayHasKey('preview_content', $responseData);
        $this->assertArrayHasKey('sample_data', $responseData);
        
        // Check that variables were replaced
        $previewContent = $responseData['preview_content'];
        $this->assertStringContainsString('John Smith', $previewContent); // Sample customer name
        $this->assertStringContainsString('BK-2024-001234', $previewContent); // Sample booking reference
    }

    /** @test */
    public function it_applies_variable_substitution_correctly()
    {
        $template = DisputeResponseTemplate::factory()->create([
            'template_content' => 'Dear {{customer_name}}, regarding booking {{booking_reference}} for {{tour_name}} on {{tour_date}}.'
        ]);

        $variables = [
            'customer_name' => 'Jane Doe',
            'booking_reference' => 'BK-2024-567890',
            'tour_name' => 'Grand Canyon Tour',
            'tour_date' => '2024-03-20'
        ];

        $result = $template->applyVariables($variables);

        $expected = 'Dear Jane Doe, regarding booking BK-2024-567890 for Grand Canyon Tour on 2024-03-20.';
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_categorizes_templates_by_dispute_reason()
    {
        // Create templates for different dispute reasons
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'fraudulent']);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'fraudulent']);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'product_not_received']);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'duplicate']);

        $response = $this->get(route('admin.dispute-templates.index'));

        $response->assertStatus(200);
        $reasonStats = $response->viewData('reasonStats');
        
        $this->assertEquals(2, $reasonStats['fraudulent']['total_count']);
        $this->assertEquals(1, $reasonStats['product_not_received']['total_count']);
        $this->assertEquals(1, $reasonStats['duplicate']['total_count']);
        $this->assertEquals(0, $reasonStats['general']['total_count']);
    }

    /** @test */
    public function it_filters_templates_by_dispute_reason()
    {
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'fraudulent']);
        DisputeResponseTemplate::factory()->create(['dispute_reason' => 'product_not_received']);

        $response = $this->get(route('admin.dispute-templates.index', ['dispute_reason' => ['fraudulent']]));

        $response->assertStatus(200);
        $templates = $response->viewData('templates');
        $this->assertCount(1, $templates);
        $this->assertEquals('fraudulent', $templates->first()->dispute_reason);
    }

    /** @test */
    public function it_filters_templates_by_active_status()
    {
        DisputeResponseTemplate::factory()->create(['is_active' => true]);
        DisputeResponseTemplate::factory()->create(['is_active' => false]);

        $response = $this->get(route('admin.dispute-templates.index', ['is_active' => '1']));

        $response->assertStatus(200);
        $templates = $response->viewData('templates');
        $this->assertCount(1, $templates);
        $this->assertTrue($templates->first()->is_active);
    }

    /** @test */
    public function it_searches_templates_by_name_and_content()
    {
        DisputeResponseTemplate::factory()->create([
            'name' => 'Fraudulent Charge Template',
            'template_content' => 'Standard response for fraudulent charges'
        ]);
        DisputeResponseTemplate::factory()->create([
            'name' => 'Product Not Received Template',
            'template_content' => 'Response for undelivered products'
        ]);

        $response = $this->get(route('admin.dispute-templates.index', ['search' => 'fraudulent']));

        $response->assertStatus(200);
        $templates = $response->viewData('templates');
        $this->assertCount(1, $templates);
        $this->assertStringContainsString('Fraudulent', $templates->first()->name);
    }
}