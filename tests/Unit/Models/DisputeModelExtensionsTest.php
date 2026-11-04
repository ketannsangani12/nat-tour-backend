<?php

namespace Tests\Unit\Models;

use App\Models\Dispute;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Models\Admin;
use Tests\TestCase;

class DisputeModelExtensionsTest extends TestCase
{
    public function test_tour_booking_has_dispute_methods()
    {
        $booking = new TourBooking();
        
        // Test that the methods exist
        $this->assertTrue(method_exists($booking, 'disputes'));
        $this->assertTrue(method_exists($booking, 'hasActiveDispute'));
        $this->assertTrue(method_exists($booking, 'getActiveDispute'));
        $this->assertTrue(method_exists($booking, 'isDisputed'));
        $this->assertTrue(method_exists($booking, 'requiresDisputeAttention'));
        $this->assertTrue(method_exists($booking, 'getDisputeStatusCssClass'));
        $this->assertTrue(method_exists($booking, 'getDisputeSummary'));
    }

    public function test_transaction_has_dispute_methods()
    {
        $transaction = new Transaction();
        
        // Test that the methods exist
        $this->assertTrue(method_exists($transaction, 'dispute'));
        $this->assertTrue(method_exists($transaction, 'isDisputed'));
        $this->assertTrue(method_exists($transaction, 'getDisputeStatus'));
        $this->assertTrue(method_exists($transaction, 'getDisputeAmount'));
        $this->assertTrue(method_exists($transaction, 'getDisputeReason'));
        $this->assertTrue(method_exists($transaction, 'requiresDisputeAttention'));
        $this->assertTrue(method_exists($transaction, 'getDisputeInfo'));
    }

    public function test_dispute_model_exists_and_has_required_methods()
    {
        $dispute = new Dispute();
        
        // Test that key methods exist
        $this->assertTrue(method_exists($dispute, 'booking'));
        $this->assertTrue(method_exists($dispute, 'transaction'));
        $this->assertTrue(method_exists($dispute, 'evidence'));
        $this->assertTrue(method_exists($dispute, 'timeline'));
        $this->assertTrue(method_exists($dispute, 'isOverdue'));
        $this->assertTrue(method_exists($dispute, 'getDaysUntilDeadline'));
        $this->assertTrue(method_exists($dispute, 'requiresAttention'));
    }

    public function test_admin_has_roles_trait()
    {
        $admin = new Admin();

        $this->assertTrue(method_exists($admin, 'hasPermissionTo'));
        $this->assertTrue(method_exists($admin, 'hasRole'));
        $this->assertTrue(method_exists($admin, 'assignRole'));
    }
}