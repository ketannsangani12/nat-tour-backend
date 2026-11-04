<?php

namespace Tests\Unit\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Repositories\Pos\CardReaderRepository;
use App\Services\Payments\PaymentModeResolver;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: CardReaderRepository::getPaymentIntent() (the web/admin Stripe Terminal flow --
 * "Payout Simplification" Phase B) never resolved settlement mode at all -- buildConnectedPaymentData()
 * was called with no `mode` argument, always defaulting to DESTINATION regardless of tour date, and
 * `application_fee_amount` was separately hardcoded onto the base payload BEFORE buildConnectedPaymentData()
 * was spread in, which would have silently survived and re-added itself even after mode support was wired
 * in (exactly the bug the original plan flagged). Fixed via resolveModeFromInput(), which reads either
 * metadata.dates (an array of tour dates, for a not-yet-created booking where there's no row to look up)
 * or metadata.booking_id (an existing booking, looked up from the real tour_slot_times row) -- falling
 * back to DESTINATION, matching PaymentModeResolver's own fail-closed design, when neither is present
 * (an older client during the rollout window).
 */
class CardReaderPlatformHeldModeTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => 0]);
        parent::tearDown();
    }

    private function setKillSwitch(bool $enabled): void
    {
        DB::table('settings')->where('key', PaymentModeResolver::KILL_SWITCH_SETTING_KEY)->update(['value' => $enabled ? 1 : 0]);
    }

    private function fakeGatewayCapturing(&$capturedPayload): PaymentGatewayInterface
    {
        $fakeGateway = $this->createMock(PaymentGatewayInterface::class);
        $fakeGateway->method('buildConnectedPaymentData')
            ->willReturnCallback(function (string $destinationId, int $feeAmountCents, ?string $statementDescriptor = null, string $mode = PaymentModeResolver::DESTINATION) {
                return (new \App\Services\Payments\StripePaymentGateway())->buildConnectedPaymentData($destinationId, $feeAmountCents, $statementDescriptor, $mode);
            });
        $fakeGateway->method('createPayment')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return (object) ['id' => 'pi_test_' . uniqid(), 'client_secret' => 'secret_test'];
            });
        return $fakeGateway;
    }

    public function test_metadata_dates_with_a_2027_plus_date_resolves_platform_held(): void
    {
        $this->setKillSwitch(true);

        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $repository = new CardReaderRepository();
        $repository->getPaymentIntent([
            'amount' => 10000,
            'fees' => 500,
            'metadata' => ['dates' => ['2027-06-01']],
        ]);

        $this->assertArrayNotHasKey('transfer_data', $capturedPayload);
        $this->assertArrayNotHasKey('application_fee_amount', $capturedPayload);
    }

    public function test_metadata_dates_with_only_pre_2027_dates_stays_destination(): void
    {
        $this->setKillSwitch(true);

        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $repository = new CardReaderRepository();
        $repository->getPaymentIntent([
            'amount' => 10000,
            'fees' => 500,
            'metadata' => ['dates' => ['2026-06-01']],
        ]);

        $this->assertSame(500, $capturedPayload['application_fee_amount']);
        $this->assertArrayHasKey('transfer_data', $capturedPayload);
    }

    public function test_metadata_booking_id_looks_up_the_real_tour_date(): void
    {
        $this->setKillSwitch(true);

        $booking = (new CreateABooking())
            ->withTourists(1)
            ->withRate(86.02)
            ->withPaymentMethod('Card')
            ->withDate('2027-06-01')
            ->create();

        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        try {
            $repository = new CardReaderRepository();
            $repository->getPaymentIntent([
                'amount' => 10000,
                'fees' => 500,
                'metadata' => ['booking_id' => $booking->bookingId],
            ]);

            $this->assertArrayNotHasKey('transfer_data', $capturedPayload);
            $this->assertArrayNotHasKey('application_fee_amount', $capturedPayload);
        } finally {
            $booking->cleanup();
        }
    }

    public function test_no_booking_context_at_all_falls_back_to_destination(): void
    {
        $this->setKillSwitch(true);

        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $repository = new CardReaderRepository();
        $repository->getPaymentIntent([
            'amount' => 10000,
            'fees' => 500,
        ]);

        $this->assertSame(500, $capturedPayload['application_fee_amount']);
        $this->assertArrayHasKey('transfer_data', $capturedPayload);
    }

    /**
     * Regression: metadata.dates is consumed internally by resolveModeFromInput() above, but was
     * then forwarded UNCHANGED into the actual Stripe-bound payload alongside it — Stripe rejects
     * any metadata value that isn't a flat string, so a real (not-yet-created) booking's Card
     * Reader payment failed outright with "Metadata values must be strings, but for key `dates`
     * you passed in a value of type `hash`." dates must never reach the outgoing payload at all,
     * regardless of whether it resolved a real date or (see backend-card-reader.js's own
     * empty-slot-date fix) a stray value some caller still manages to send.
     */
    public function test_dates_metadata_key_never_reaches_the_stripe_payload(): void
    {
        $this->setKillSwitch(true);

        $capturedPayload = null;
        $this->app->instance(PaymentGatewayInterface::class, $this->fakeGatewayCapturing($capturedPayload));

        $repository = new CardReaderRepository();
        $repository->getPaymentIntent([
            'amount' => 10000,
            'fees' => 500,
            'metadata' => ['dates' => ['2027-06-01']],
        ]);

        $this->assertArrayNotHasKey(
            'dates',
            $capturedPayload['metadata'],
            'dates is an internal mode-resolution signal, not real Stripe metadata — Stripe requires all metadata values to be strings and rejects an array outright.'
        );
    }
}
