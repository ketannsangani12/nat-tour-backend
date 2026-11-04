<?php

namespace Tests\Unit\Disputes;

use Tests\TestCase;
use App\Services\Dispute\StripeErrorHandler;
use App\Services\Dispute\PciComplianceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Mockery;

/**
 * ErrorHandlingUnitTest
 * 
 * Unit tests for error handling and security measures without tenant dependencies.
 * 
 * Requirements: 3.6, 3.7, 7.7, 9.3, 9.7
 */
class ErrorHandlingUnitTest extends TestCase
{
    protected $tenancy = false; // Disable tenancy for unit tests

    /**
     * Test exponential backoff calculation.
     */
    public function test_exponential_backoff_calculation(): void
    {
        $errorHandler = new StripeErrorHandler();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($errorHandler);
        $method = $reflection->getMethod('calculateExponentialBackoff');
        $method->setAccessible(true);

        // Test backoff calculation
        $delay1 = $method->invoke($errorHandler, 1);
        $delay2 = $method->invoke($errorHandler, 2);
        $delay3 = $method->invoke($errorHandler, 3);

        $this->assertGreaterThan(0, $delay1);
        $this->assertGreaterThan($delay1, $delay2);
        $this->assertGreaterThan($delay2, $delay3);
        
        // Test maximum delay cap
        $delayMax = $method->invoke($errorHandler, 10);
        $this->assertLessThanOrEqual(300, $delayMax); // Max 5 minutes
    }

    /**
     * Test PCI compliance sensitive data detection.
     */
    public function test_pci_sensitive_data_detection(): void
    {
        $pciService = new PciComplianceService();
        
        // Test card number detection
        $textWithCard = 'Payment with card 4111111111111111';
        $assessment = $pciService->scanForSensitiveData($textWithCard);
        
        $this->assertEquals('non_compliant', $assessment['compliance_status']);
        $this->assertEquals('critical', $assessment['overall_risk_level']);
        $this->assertTrue($assessment['requires_remediation']);
        
        // Test CVV detection
        $textWithCvv = 'CVV: 123';
        $assessment2 = $pciService->scanForSensitiveData($textWithCvv);
        
        $this->assertEquals('non_compliant', $assessment2['compliance_status']);
        $this->assertEquals('critical', $assessment2['overall_risk_level']);
        
        // Test clean text
        $cleanText = 'This is a normal receipt without sensitive data';
        $assessment3 = $pciService->scanForSensitiveData($cleanText);
        
        $this->assertEquals('compliant', $assessment3['compliance_status']);
        $this->assertEquals('low', $assessment3['overall_risk_level']);
        $this->assertFalse($assessment3['requires_remediation']);
    }

    /**
     * Test PAN redaction according to PCI DSS requirements.
     */
    public function test_pan_redaction(): void
    {
        $pciService = new PciComplianceService();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($pciService);
        $method = $reflection->getMethod('redactPan');
        $method->setAccessible(true);

        // Test Visa card redaction
        $visaCard = '4111111111111111';
        $redacted = $method->invoke($pciService, $visaCard);
        
        $this->assertEquals('411111******1111', $redacted);
        $this->assertStringStartsWith('411111', $redacted);
        $this->assertStringEndsWith('1111', $redacted);
        $this->assertStringContainsString('*', $redacted);
        
        // Test shorter number
        $shortNumber = '123456789';
        $redactedShort = $method->invoke($pciService, $shortNumber);
        
        $this->assertStringEndsWith('6789', $redactedShort);
        $this->assertStringContainsString('*', $redactedShort);
    }

    /**
     * Test sensitive data redaction.
     */
    public function test_sensitive_data_redaction(): void
    {
        $pciService = new PciComplianceService();
        
        $originalText = 'Card: 4111111111111111, CVV: 123, SSN: 123-45-6789';
        $result = $pciService->redactSensitiveData($originalText);
        
        $this->assertNotEquals($originalText, $result['redacted_text']);
        $this->assertStringNotContainsString('4111111111111111', $result['redacted_text']);
        $this->assertStringNotContainsString('CVV: 123', $result['redacted_text']);
        $this->assertStringNotContainsString('123-45-6789', $result['redacted_text']);
        $this->assertGreaterThan(0, $result['redaction_count']);
        $this->assertEquals('redacted_for_compliance', $result['compliance_status']);
    }

    /**
     * Test error handler retry logic determination.
     */
    public function test_should_retry_api_error(): void
    {
        $errorHandler = new StripeErrorHandler();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($errorHandler);
        $method = $reflection->getMethod('shouldRetryApiError');
        $method->setAccessible(true);

        // Mock retryable exception
        $retryableException = Mockery::mock(\Stripe\Exception\ApiErrorException::class);
        $retryableException->shouldReceive('getStripeCode')->andReturn('rate_limit');
        
        $shouldRetry = $method->invoke($errorHandler, $retryableException);
        $this->assertTrue($shouldRetry);
        
        // Mock non-retryable exception
        $nonRetryableException = Mockery::mock(\Stripe\Exception\ApiErrorException::class);
        $nonRetryableException->shouldReceive('getStripeCode')->andReturn('card_declined');
        
        $shouldNotRetry = $method->invoke($errorHandler, $nonRetryableException);
        $this->assertFalse($shouldNotRetry);
    }

    /**
     * Test compliance score calculation.
     */
    public function test_compliance_score_calculation(): void
    {
        $pciService = new PciComplianceService();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($pciService);
        $method = $reflection->getMethod('calculateComplianceScore');
        $method->setAccessible(true);

        // Test perfect score
        $perfectReport = [
            'security_incidents' => ['critical_incidents' => 0],
            'access_violations' => ['total_violations' => 0]
        ];
        
        $perfectScore = $method->invoke($pciService, $perfectReport);
        $this->assertEquals(100.0, $perfectScore);
        
        // Test score with incidents
        $reportWithIncidents = [
            'security_incidents' => ['critical_incidents' => 2],
            'access_violations' => ['total_violations' => 5]
        ];
        
        $reducedScore = $method->invoke($pciService, $reportWithIncidents);
        $this->assertEquals(55.0, $reducedScore); // 100 - (2*10) - (5*5) = 55
        
        // Test minimum score
        $badReport = [
            'security_incidents' => ['critical_incidents' => 20],
            'access_violations' => ['total_violations' => 50]
        ];
        
        $minScore = $method->invoke($pciService, $badReport);
        $this->assertEquals(0.0, $minScore); // Should not go below 0
    }

    /**
     * Test compliance status determination.
     */
    public function test_compliance_status_determination(): void
    {
        $pciService = new PciComplianceService();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($pciService);
        $method = $reflection->getMethod('determineComplianceStatus');
        $method->setAccessible(true);

        $this->assertEquals('fully_compliant', $method->invoke($pciService, 98.0));
        $this->assertEquals('mostly_compliant', $method->invoke($pciService, 85.0));
        $this->assertEquals('partially_compliant', $method->invoke($pciService, 70.0));
        $this->assertEquals('non_compliant', $method->invoke($pciService, 50.0));
    }

    /**
     * Test rate limit key generation.
     */
    public function test_rate_limit_key_generation(): void
    {
        $errorHandler = new StripeErrorHandler();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($errorHandler);
        $method = $reflection->getMethod('getCurrentTenantId');
        $method->setAccessible(true);

        // Test tenant ID retrieval
        session(['tenant_id' => 'test_tenant']);
        $tenantId = $method->invoke($errorHandler);
        
        $this->assertEquals('test_tenant', $tenantId);
        
        // Test default when no tenant
        session()->forget('tenant_id');
        $defaultTenantId = $method->invoke($errorHandler);
        
        $this->assertEquals('default', $defaultTenantId);
    }

    /**
     * Test security risk assessment.
     */
    public function test_security_risk_assessment(): void
    {
        $auditLogger = app(\App\Services\Dispute\DisputeAuditLogger::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($auditLogger);
        $method = $reflection->getMethod('assessSecurityRisk');
        $method->setAccessible(true);

        // Test high risk context
        $highRiskContext = [
            'repeated_attempts' => 5,
            'unusual_ip' => true,
            'off_hours' => true
        ];
        
        $riskLevel = $method->invoke($auditLogger, $highRiskContext);
        $this->assertEquals('high', $riskLevel);
        
        // Test medium risk context
        $mediumRiskContext = [
            'repeated_attempts' => 2,
            'unusual_ip' => true, // This should make it medium risk
            'off_hours' => false
        ];
        
        $riskLevel2 = $method->invoke($auditLogger, $mediumRiskContext);
        $this->assertEquals('medium', $riskLevel2);
        
        // Test low risk context
        $lowRiskContext = [
            'repeated_attempts' => 1,
            'unusual_ip' => false,
            'off_hours' => false
        ];
        
        $riskLevel3 = $method->invoke($auditLogger, $lowRiskContext);
        $this->assertEquals('low', $riskLevel3);
    }

    /**
     * Test sensitive data masking.
     */
    public function test_sensitive_data_masking(): void
    {
        $auditLogger = app(\App\Services\Dispute\DisputeAuditLogger::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($auditLogger);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        // Test normal length data
        $normalData = 'sensitive123';
        $masked = $method->invoke($auditLogger, $normalData);
        
        $this->assertEquals('se********23', $masked);
        $this->assertStringStartsWith('se', $masked);
        $this->assertStringEndsWith('23', $masked);
        $this->assertStringContainsString('*', $masked);
        
        // Test short data
        $shortData = 'abc';
        $maskedShort = $method->invoke($auditLogger, $shortData);
        
        $this->assertEquals('***', $maskedShort);
        
        // Test very short data
        $veryShortData = 'ab';
        $maskedVeryShort = $method->invoke($auditLogger, $veryShortData);
        
        $this->assertEquals('**', $maskedVeryShort);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}