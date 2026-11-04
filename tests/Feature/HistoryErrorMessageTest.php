<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Booking\HistoryService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\InvalidRequestException;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Two things get checked here for both storeNormalErrorInHistory() and
 * storeCardPaymentErrorInHistory(): (1) the real actor is now recorded and displayable
 * (not the hardcoded "Customer" fallback), and (2) Stripe's own developer-facing API
 * error text gets translated into plain language, while everything else (including
 * card-decline messages, and our own already-plain-English exceptions) passes through
 * unchanged.
 */
class HistoryErrorMessageTest extends TestCase
{
    private ?CreateABooking $booking = null;

    protected function tearDown(): void
    {
        try {
            $this->booking?->cleanup();
        } finally {
            $this->booking = null;
            parent::tearDown();
        }
    }

    private function setUpBookingAsAdmin(): void
    {
        $this->booking = (new CreateABooking())->withoutTransaction()->withoutHistory()->create();

        /** @var Admin $admin */
        $admin = Admin::factory()->create(['name' => 'Test Admin Actor']);
        $this->actingAs($admin, 'admin');
        $this->assertSame($admin->id, Auth::id());
    }

    private function latestHistoryDetails(): array
    {
        $row = DB::table('tour_booking_history')
            ->where('tour_booking_id', $this->booking->bookingId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($row, 'Expected a history row to have been inserted.');

        return json_decode($row->details, true);
    }

    public function test_stripe_lock_timeout_error_is_translated_to_plain_language(): void
    {
        $this->setUpBookingAsAdmin();

        $exception = InvalidRequestException::factory(
            'This object cannot be accessed right now because another API request or Stripe process is currently accessing it. If you see this error intermittently, retry the request. If you see this error frequently and are making multiple concurrent requests to a single object, make your requests serially or at a lower rate.',
            400,
            null,
            null,
            null,
            \Stripe\ErrorObject::CODE_LOCK_TIMEOUT
        );

        HistoryService::storeNormalErrorInHistory($this->booking->bookingId, $exception);

        $details = $this->latestHistoryDetails();
        $this->assertStringNotContainsString('API request', $details['message']);
        $this->assertStringContainsString('already in progress', $details['message']);
        $this->assertStringContainsString('Test Admin Actor', $details['created_by_with_name']);
    }

    public function test_stripe_payment_intent_unexpected_state_error_is_translated(): void
    {
        $this->setUpBookingAsAdmin();

        $exception = InvalidRequestException::factory(
            'You cannot confirm this PaymentIntent because it has already succeeded after being previously confirmed.',
            400,
            null,
            null,
            null,
            \Stripe\ErrorObject::CODE_PAYMENT_INTENT_UNEXPECTED_STATE
        );

        HistoryService::storeCardPaymentErrorInHistory($this->booking->bookingId, $exception);

        $details = $this->latestHistoryDetails();
        $this->assertSame('This payment had already been completed before this attempt could go through.', $details['message']);
        $this->assertStringContainsString('Test Admin Actor', $details['created_by_with_name']);
    }

    public function test_unrecognized_stripe_error_code_passes_through_unchanged(): void
    {
        $this->setUpBookingAsAdmin();

        // A card decline: Stripe already writes these in plain language - must not be altered.
        $exception = InvalidRequestException::factory(
            'Your card was declined.',
            402,
            null,
            null,
            null,
            'card_declined'
        );

        HistoryService::storeCardPaymentErrorInHistory($this->booking->bookingId, $exception);

        $details = $this->latestHistoryDetails();
        $this->assertSame('Your card was declined.', $details['message']);
    }

    public function test_our_own_plain_exception_passes_through_unchanged(): void
    {
        $this->setUpBookingAsAdmin();

        HistoryService::storeNormalErrorInHistory($this->booking->bookingId, new Exception('This booking has already been fully paid.'));

        $details = $this->latestHistoryDetails();
        $this->assertSame('This booking has already been fully paid.', $details['message']);
        $this->assertStringContainsString('Test Admin Actor', $details['created_by_with_name']);
    }
}
