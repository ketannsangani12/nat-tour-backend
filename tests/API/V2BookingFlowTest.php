<?php

namespace Tests\API;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Constants\Constants;
use Carbon\Carbon;

class V2BookingFlowTest extends TestCase
{
    /**
     * Stripe PaymentIntent IDs created during tests, cancelled in tearDownAfterClass.
     */
    protected static array $paymentIntentIds = [];

    /**
     * Stripe secret key, captured once from config during the first test.
     */
    protected static ?string $stripeSecretKey = null;

    /**
     * Middleware to bypass -- tenancy is already initialized by TestCase::setUp().
     */
    protected array $tenantMiddleware = [
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware($this->tenantMiddleware);

        if (static::$stripeSecretKey === null) {
            static::$stripeSecretKey = config('constants.stripe_keys.' . config('app.env') . '.0.secret_key');
        }
    }

    /**
     * Cancel any PaymentIntents created during the test run.
     */
    public static function tearDownAfterClass(): void
    {
        if (!empty(static::$paymentIntentIds) && static::$stripeSecretKey) {
            $stripe = new \Stripe\StripeClient(static::$stripeSecretKey);
            foreach (static::$paymentIntentIds as $piId) {
                try {
                    $stripe->paymentIntents->cancel($piId);
                } catch (\Exception $e) {
                    // PI may already be cancelled or captured -- ignore
                }
            }
        }

        static::$paymentIntentIds = [];
        static::$stripeSecretKey = null;

        parent::tearDownAfterClass();
    }

    /**
     * Find a valid package and future slot time from the dixies tenant DB.
     * Does NOT pre-select a rate -- the controller applies date-dependent rate
     * logic (custom date rates, surcharges, etc.) that we should not replicate.
     *
     * @return object{package: object, slot: object}
     */
    protected function findTestBookingData(): object
    {
        $packages = DB::table('tour_packages')
            ->where('status', 'Active')
            ->orderBy('id')
            ->get();

        $this->assertNotEmpty($packages, 'No active tour package found in dixies DB');

        foreach ($packages as $package) {
            $slot = $this->findBookableFutureSlot($package);
            if ($slot !== null) {
                return (object) compact('package', 'slot');
            }
        }

        $this->fail('No active tour package has a slot far enough in the future to satisfy its minimum advance-booking window.');
    }

    /**
     * Find a future slot for the given package that is far enough ahead to
     * clear its minimum advance-booking window (mirrors the lead-time check
     * in TourBookingController::createPaymentIntent), so the test doesn't
     * flake depending on when it happens to run relative to seeded slots.
     */
    protected function findBookableFutureSlot(object $package): ?object
    {
        $durationHours = (int) $package->duration;
        if ($package->day_hours == Constants::STATUS_ONE) {
            $durationHours *= 24;
        }
        $earliestBookable = Carbon::now()->addHours($durationHours);

        return DB::table('tour_slot_times')
            ->where('tour_package_id', $package->id)
            ->where('date', '>=', $earliestBookable->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('time_from')
            ->get()
            ->first(fn ($slot) => Carbon::parse($slot->date . ' ' . $slot->time_from)->gte($earliestBookable));
    }

    /**
     * Test creating a Stripe PaymentIntent via the /api/v2/create-payment-intent endpoint.
     * The test finds a valid package and future slot from the DB, constructs a payload without
     * a selectedRateId (letting the controller apply rate logic), and asserts that a valid
     * PaymentIntent is created and returned.
     */
    public function testCreateAPaymentIntentViaTheV2Api(): void
    {
        $data = $this->findTestBookingData();

        // Don't set selectedRateId -- let the controller pick the applicable rates
        // for this date (handles custom date rates, surcharges, etc. automatically).
        // Provide enough people_group entries for up to 10 rate tiers.
        $payload = [
            'items' => [
                $data->slot->id => [
                    'package_id'      => $data->package->id,
                    'affiliate_id'    => 0,
                    'infant_count'    => 0,
                    'selectedRateId'  => null,
                    'discount2_value' => 0,
                    'addons_total'    => 0,
                    'addons_fee'      => 0,
                    'people_group'    => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                ],
            ],
        ];

        $response = $this->postJson('/api/v2/create-payment-intent', $payload);

        $this->assertEquals(200, $response->status(),
            'create-payment-intent failed: ' . $response->getContent()
        );

        $response->assertJsonStructure(['clientSecret', 'paymentIntentId']);

        $piId = $response->json('paymentIntentId');
        $this->assertStringStartsWith('pi_', $piId);

        static::$paymentIntentIds[] = $piId;
    }
}
