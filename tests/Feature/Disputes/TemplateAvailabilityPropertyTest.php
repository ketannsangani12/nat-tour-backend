<?php

namespace Tests\Feature\Disputes;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\DisputeResponseTemplate;
use App\Services\Dispute\EvidenceCompilerService;
use Illuminate\Support\Collection;

/**
 * Property 8: Template Availability
 * 
 * For any dispute reason, the system should provide appropriate response templates 
 * for evidence submission.
 * 
 * **Validates: Requirements 11.1**
 */
class TemplateAvailabilityPropertyTest extends TestCase
{

    protected EvidenceCompilerService $evidenceCompilerService;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evidenceCompilerService = app(EvidenceCompilerService::class);
        $this->admin = Admin::factory()->create();
    }

    protected function tearDown(): void
    {
        if (isset($this->admin)) {
            DisputeResponseTemplate::where('created_by', $this->admin->id)->delete();
            $this->admin->delete();
        }
        parent::tearDown();
    }

    /**
     * Property-based test: For any dispute reason, the system should provide 
     * appropriate response templates for evidence submission.
     * 
     * This test validates that:
     * 1. Templates are available for all supported dispute reasons
     * 2. The template retrieval system works correctly for each reason
     * 3. Templates can be properly applied and customized
     * 4. Template categorization works correctly by dispute reason
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_templates_are_available_for_all_dispute_reasons(): void
    {
        // Get all supported dispute reasons from the system
        $disputeReasons = $this->getAllSupportedDisputeReasons();
        
        // Create at least one active template for each dispute reason
        foreach ($disputeReasons as $reason => $label) {
            DisputeResponseTemplate::factory()->create([
                'dispute_reason' => $reason,
                'is_active' => true,
                'name' => "Template for {$label}",
                'template_content' => "Standard response template for {$label} disputes. Booking: {{booking_reference}}, Customer: {{customer_name}}",
                'created_by' => $this->admin->id,
                'created_by_user_type' => 'Admin'
            ]);
        }

        // Test that templates are available for each dispute reason
        foreach ($disputeReasons as $reason => $label) {
            $templates = $this->evidenceCompilerService->getTemplatesForReason($reason);
            
            $this->assertInstanceOf(Collection::class, $templates, 
                "Template retrieval should return a Collection for reason: {$reason}");
            
            $this->assertGreaterThan(0, $templates->count(), 
                "At least one template should be available for dispute reason: {$reason} ({$label})");
            
            // Verify all returned templates are for the correct reason and are active
            foreach ($templates as $template) {
                $this->assertEquals($reason, $template->dispute_reason, 
                    "Template should be categorized for the correct dispute reason: {$reason}");
                
                $this->assertTrue($template->is_active, 
                    "Only active templates should be returned for dispute reason: {$reason}");
            }
        }
    }

    /**
     * Property-based test: For any dispute reason with multiple templates,
     * the system should return all active templates in a consistent order.
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_multiple_templates_per_reason_are_properly_organized(): void
    {
        // Clean up all templates for this dispute reason to ensure isolation
        DisputeResponseTemplate::where('dispute_reason', 'fraudulent')->delete();
        
        $disputeReason = 'fraudulent';
        
        // Create multiple templates for the same dispute reason
        $templateNames = ['Template A', 'Template B', 'Template C'];
        foreach ($templateNames as $name) {
            DisputeResponseTemplate::factory()->create([
                'dispute_reason' => $disputeReason,
                'is_active' => true,
                'name' => $name,
                'created_by' => $this->admin->id,
                'created_by_user_type' => 'Admin'
            ]);
        }
        
        // Create an inactive template that should not be returned
        DisputeResponseTemplate::factory()->create([
            'dispute_reason' => $disputeReason,
            'is_active' => false,
            'name' => 'Inactive Template',
            'created_by' => $this->admin->id,
            'created_by_user_type' => 'Admin'
        ]);

        $templates = $this->evidenceCompilerService->getTemplatesForReason($disputeReason);
        
        $this->assertEquals(3, $templates->count(), 
            "Should return exactly 3 active templates for the dispute reason");
        
        // Verify templates are ordered by name (as per the service implementation)
        $retrievedNames = $templates->pluck('name')->toArray();
        sort($templateNames); // Expected order
        $this->assertEquals($templateNames, $retrievedNames, 
            "Templates should be returned in alphabetical order by name");
        
        // Verify no inactive templates are returned
        foreach ($templates as $template) {
            $this->assertTrue($template->is_active, 
                "Only active templates should be returned");
        }
    }

    /**
     * Property-based test: For any template with variable placeholders,
     * the system should properly apply template variables.
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_templates_can_be_properly_applied_and_customized(): void
    {
        // Clean up any templates created by this test's admin
        DisputeResponseTemplate::where('created_by', $this->admin->id)->delete();
        
        $disputeReason = 'product_not_received';
        
        // Create a template with variable placeholders
        $template = DisputeResponseTemplate::factory()->create([
            'dispute_reason' => $disputeReason,
            'is_active' => true,
            'name' => 'Product Not Received Template',
            'template_content' => 'Dear {{customer_name}}, regarding your booking {{booking_reference}} for {{tour_name}} on {{tour_date}}. We have received your dispute regarding {{dispute_reason}}.',
            'created_by' => $this->admin->id,
            'created_by_user_type' => 'Admin'
        ]);

        // Test template variable application
        $testVariables = [
            'customer_name' => 'John Doe',
            'booking_reference' => 'BK123456',
            'tour_name' => 'City Walking Tour',
            'tour_date' => '2024-01-15',
            'dispute_reason' => 'Product Not Received'
        ];

        $appliedContent = $template->applyVariables($testVariables);
        
        // Verify all variables were replaced
        foreach ($testVariables as $key => $value) {
            $this->assertStringContainsString($value, $appliedContent, 
                "Applied template should contain the variable value for {$key}");
            
            $placeholder = '{{' . $key . '}}';
            $this->assertStringNotContainsString($placeholder, $appliedContent, 
                "Applied template should not contain the placeholder {$placeholder}");
        }
        
        // Verify the template structure is maintained
        $this->assertStringContainsString('Dear John Doe, regarding your booking BK123456', $appliedContent,
            "Template structure should be maintained after variable application");
    }

    /**
     * Property-based test: For any dispute reason without templates,
     * the system should handle the absence gracefully.
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_handles_dispute_reasons_without_templates_gracefully(): void
    {
        $disputeReason = 'general';
        
        // Ensure no templates exist for this reason
        DisputeResponseTemplate::where('dispute_reason', $disputeReason)->delete();
        
        $templates = $this->evidenceCompilerService->getTemplatesForReason($disputeReason);
        
        $this->assertInstanceOf(Collection::class, $templates, 
            "Template retrieval should return a Collection even when no templates exist");
        
        $this->assertEquals(0, $templates->count(), 
            "Should return empty collection when no templates exist for the dispute reason");
        
        $this->assertTrue($templates->isEmpty(), 
            "Collection should be empty when no templates are available");
    }

    /**
     * Property-based test: For any template categorization scenario,
     * the system should maintain proper isolation between dispute reasons.
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_template_categorization_maintains_proper_isolation(): void
    {
        // Clean up all templates for these reasons to ensure isolation
        DisputeResponseTemplate::whereIn('dispute_reason', ['fraudulent', 'duplicate', 'product_unacceptable'])->delete();
        
        // Create templates for different dispute reasons
        $templateData = [
            'fraudulent' => 'Fraudulent Charge Template',
            'duplicate' => 'Duplicate Payment Template',
            'product_unacceptable' => 'Product Quality Template'
        ];
        
        foreach ($templateData as $reason => $name) {
            DisputeResponseTemplate::factory()->create([
                'dispute_reason' => $reason,
                'is_active' => true,
                'name' => $name,
                'created_by' => $this->admin->id,
                'created_by_user_type' => 'Admin'
            ]);
        }

        // Test isolation - each reason should only return its own templates
        foreach ($templateData as $reason => $expectedName) {
            $templates = $this->evidenceCompilerService->getTemplatesForReason($reason);
            
            $this->assertEquals(1, $templates->count(), 
                "Should return exactly one template for dispute reason: {$reason}");
            
            $template = $templates->first();
            $this->assertEquals($reason, $template->dispute_reason, 
                "Template should belong to the requested dispute reason");
            
            $this->assertEquals($expectedName, $template->name, 
                "Should return the correct template for the dispute reason");
            
            // Verify no templates from other reasons are included
            foreach ($templateData as $otherReason => $otherName) {
                if ($otherReason !== $reason) {
                    $this->assertNotEquals($otherReason, $template->dispute_reason, 
                        "Template should not belong to other dispute reasons");
                }
            }
        }
    }

    /**
     * Property-based test: For any template availability check,
     * the system should provide comprehensive template information.
     * 
     * **Validates: Requirements 11.1**
     */
    public function test_template_system_provides_comprehensive_information(): void
    {
        // Clean up any templates created by this test's admin
        DisputeResponseTemplate::where('created_by', $this->admin->id)->delete();
        
        $disputeReason = 'credit_not_processed';
        
        // Create a comprehensive template
        $template = DisputeResponseTemplate::factory()->create([
            'dispute_reason' => $disputeReason,
            'is_active' => true,
            'name' => 'Credit Processing Issue Template',
            'template_content' => 'Comprehensive template with {{customer_name}} and {{booking_reference}}',
            'created_by' => $this->admin->id,
            'created_by_user_type' => 'Admin'
        ]);

        $templates = $this->evidenceCompilerService->getTemplatesForReason($disputeReason);
        $retrievedTemplate = $templates->first();
        
        // Verify all essential template information is available
        $this->assertNotNull($retrievedTemplate->id, 
            "Template should have a valid ID");
        
        $this->assertNotEmpty($retrievedTemplate->name, 
            "Template should have a name");
        
        $this->assertNotEmpty($retrievedTemplate->template_content, 
            "Template should have content");
        
        $this->assertEquals($disputeReason, $retrievedTemplate->dispute_reason, 
            "Template should be properly categorized");
        
        $this->assertTrue($retrievedTemplate->is_active, 
            "Retrieved template should be active");
        
        // Verify template has proper relationships and metadata
        $this->assertNotNull($retrievedTemplate->created_by, 
            "Template should have creator information");
        
        $this->assertNotNull($retrievedTemplate->created_by_user_type, 
            "Template should have creator type information");
        
        // Verify template methods work correctly
        $this->assertIsString($retrievedTemplate->getReasonLabelAttribute(), 
            "Template should provide human-readable reason label");
        
        // Verify available variables can be retrieved
        $availableVariables = DisputeResponseTemplate::getAvailableVariables();
        $this->assertIsArray($availableVariables, 
            "System should provide available template variables");
        
        $this->assertArrayHasKey('customer_name', $availableVariables, 
            "Available variables should include customer_name");
        
        $this->assertArrayHasKey('booking_reference', $availableVariables, 
            "Available variables should include booking_reference");
    }

    /**
     * Get all supported dispute reasons from the system.
     * This mirrors the controller's getAvailableDisputeReasons method.
     */
    private function getAllSupportedDisputeReasons(): array
    {
        return [
            'fraudulent' => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable' => 'Product Unacceptable',
            'credit_not_processed' => 'Credit Not Processed',
            'duplicate' => 'Duplicate',
            'product_not_received' => 'Product Not Received',
            'unrecognized' => 'Unrecognized',
            'general' => 'General'
        ];
    }
}