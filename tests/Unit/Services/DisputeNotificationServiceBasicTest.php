<?php

namespace Tests\Unit\Services;

use App\Services\Dispute\DisputeNotificationService;
use Tests\TestCase;

class DisputeNotificationServiceBasicTest extends TestCase
{
    protected $tenancy = false; // Disable tenancy for this test

    public function test_service_can_be_instantiated()
    {
        $service = app(DisputeNotificationService::class);
        
        $this->assertInstanceOf(DisputeNotificationService::class, $service);
    }

    public function test_gets_notification_channels()
    {
        $service = app(DisputeNotificationService::class);
        
        $channels = $service->getNotificationChannels();
        
        $this->assertIsArray($channels);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_gets_deadline_warning_days()
    {
        $service = app(DisputeNotificationService::class);
        
        $days = $service->getDeadlineWarningDays();
        
        $this->assertIsArray($days);
        $this->assertContains(7, $days);
        $this->assertContains(3, $days);
        $this->assertContains(1, $days);
    }

    public function test_checks_notification_enabled_status()
    {
        $service = app(DisputeNotificationService::class);
        
        // Test default enabled status
        $this->assertTrue($service->isNotificationEnabled('deadline'));
        $this->assertTrue($service->isNotificationEnabled('created'));
        $this->assertTrue($service->isNotificationEnabled('status_change'));
    }

    public function test_identifies_significant_status_changes()
    {
        $service = app(DisputeNotificationService::class);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isSignificantStatusChange');
        $method->setAccessible(true);

        // Test significant changes
        $this->assertTrue($method->invoke($service, 'warning_needs_response', 'under_review'));
        $this->assertTrue($method->invoke($service, 'under_review', 'won'));
        $this->assertTrue($method->invoke($service, 'under_review', 'lost'));
        $this->assertTrue($method->invoke($service, 'warning_needs_response', 'lost'));
        $this->assertTrue($method->invoke($service, 'warning_needs_response', 'charge_refunded'));

        // Test non-significant changes
        $this->assertFalse($method->invoke($service, 'under_review', 'under_review'));
        $this->assertFalse($method->invoke($service, 'warning_needs_response', 'warning_needs_response'));
        $this->assertFalse($method->invoke($service, 'won', 'won'));
    }
}