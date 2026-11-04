<?php

namespace Tests\API;

use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Fixture\CreateABooking;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class V3AgendaDayTimeSlotsTest extends TestCase
{
    protected static array $builders = [];
    protected static ?int $createdStaffId = null;

    protected array $tenantMiddleware = [
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware($this->tenantMiddleware);
    }

    protected function authAsStaff(): Staff
    {
        $staff = Staff::first();

        if (!$staff) {
            $tourOpId = DB::table('tour_operators')->value('id') ?? 1;
            $id = DB::table('tour_operator_staff')->insertGetId([
                'tour_operator_id' => $tourOpId,
                'first_name'       => 'Test',
                'last_name'        => 'Staff',
                'email'            => 'test-staff-' . uniqid() . '@test.com',
                'password'         => Hash::make('password'),
                'status'           => 'Active',
                'created_at'       => now(),
            ]);
            static::$createdStaffId = $id;
            $staff = Staff::find($id);
        }

        Sanctum::actingAs($staff, ['*']);
        return $staff;
    }

    /**
     * Extract all booking IDs from the nested response structure.
     *
     * The agenda endpoint nests bookings inside data.data[].slotArray[],
     * grouped by time slot.
     */
    protected function extractBookingIds(TestResponse $response): array
    {
        return collect($response->json('data.data'))
            ->flatMap(fn ($slot) => $slot['slotArray'] ?? [])
            ->pluck('booking_id')
            ->toArray();
    }

    /**
     * POST /api/v3/agenda-day-time-slots returns bookings for a given date.
     */
    public function testReturnsBookingsForDate(): void
    {
        $this->authAsStaff();

        $today = Carbon::today();
        $builder = (new CreateABooking())
            ->withDate($today->toDateString())
            ->withTourists(2)
            ->create();
        static::$builders[] = $builder;

        $payload = [
            'date' => (int) $today->format('Ymd'),
            'tour_package_id' => 0,
            'payment_status' => 0,
            'slot_id' => 0,
        ];

        $response = $this->postJson('/api/v3/agenda-day-time-slots', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.date', $today->toDateString());

        $bookingIds = $this->extractBookingIds($response);
        $this->assertContains($builder->bookingId, $bookingIds,
            'Response should include the created booking');
    }

    /**
     * Filtering by payment_status=2 (paid) returns paid bookings.
     */
    public function testFiltersByPaymentStatus(): void
    {
        $this->authAsStaff();

        $today = Carbon::today();
        $builder = (new CreateABooking())
            ->withDate($today->toDateString())
            ->create();
        static::$builders[] = $builder;

        $payload = [
            'date' => (int) $today->format('Ymd'),
            'tour_package_id' => 0,
            'payment_status' => 2,
            'slot_id' => 0,
        ];

        $response = $this->postJson('/api/v3/agenda-day-time-slots', $payload);

        $response->assertStatus(200);

        $bookingIds = $this->extractBookingIds($response);
        $this->assertContains($builder->bookingId, $bookingIds,
            'Paid booking should appear when filtering by paid status');
    }

    /**
     * Filtering by slot_id narrows results to that slot.
     */
    public function testFiltersBySlotId(): void
    {
        $this->authAsStaff();

        $today = Carbon::today();
        $builder = (new CreateABooking())
            ->withDate($today->toDateString())
            ->create();
        static::$builders[] = $builder;

        $payload = [
            'date' => (int) $today->format('Ymd'),
            'tour_package_id' => 0,
            'payment_status' => 0,
            'slot_id' => $builder->slotTimeId,
        ];

        $response = $this->postJson('/api/v3/agenda-day-time-slots', $payload);

        $response->assertStatus(200);

        $bookingIds = $this->extractBookingIds($response);
        $this->assertContains($builder->bookingId, $bookingIds,
            'Booking should appear when filtering by its slot ID');
    }

    /**
     * Verify the response envelope and nested data types match what the iOS app expects.
     */
    public function testResponseTypesForIosApp(): void
    {
        $this->authAsStaff();

        $today = Carbon::today();
        $builder = (new CreateABooking())
            ->withDate($today->toDateString())
            ->withTourists(2)
            ->create();
        static::$builders[] = $builder;

        $payload = [
            'date' => (int) $today->format('Ymd'),
            'tour_package_id' => 0,
            'payment_status' => 0,
            'slot_id' => 0,
        ];

        $response = $this->postJson('/api/v3/agenda-day-time-slots', $payload);
        $json = $response->json();

        // -- Top-level envelope --
        $this->assertIsString($json['message']);
        $this->assertIsInt($json['code']);
        $this->assertIsArray($json['data']);

        // -- data.* keys --
        $data = $json['data'];
        $this->assertIsString($data['date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['date']);
        $this->assertIsString($data['agendaView']);
        $this->assertIsArray($data['tourPackages']);
        $this->assertIsArray($data['tourOperatorCheckInStatues']);
        $this->assertIsArray($data['otaPlatforms']);
        $this->assertIsArray($data['checkInStatues']);
        $this->assertIsArray($data['data']);

        // -- Locate the slot group containing our test booking --
        $targetSlot = null;
        $targetBooking = null;
        foreach ($data['data'] as $slot) {
            foreach ($slot['slotArray'] ?? [] as $booking) {
                if ($booking['booking_id'] === $builder->bookingId) {
                    $targetSlot = $slot;
                    $targetBooking = $booking;
                    break 2;
                }
            }
        }
        $this->assertNotNull($targetSlot, 'Test booking slot group not found in response');
        $this->assertNotNull($targetBooking, 'Test booking not found in response');

        // -- Slot group types --
        $this->assertIsString($targetSlot['slotTime']);
        $this->assertIsInt($targetSlot['tour_slot_id']);
        $this->assertIsInt($targetSlot['tour_package_id']);
        $this->assertIsString($targetSlot['tour_package_name']);
        $this->assertIsArray($targetSlot['slotArray']);
        $this->assertIsString($targetSlot['availableSeats']);
        $this->assertIsString($targetSlot['seats']);
        $this->assertIsArray($targetSlot['ageTotalGroup']);

        // -- Booking types --
        $this->assertIsInt($targetBooking['booking_id']);
        $this->assertIsInt($targetBooking['tour_slot_id']);
        $this->assertIsInt($targetBooking['hotel_id']);
        $this->assertIsInt($targetBooking['tourists']);
        $this->assertIsInt($targetBooking['tour_package_id']);
        $this->assertIsString($targetBooking['tour_package_name']);
        $this->assertIsString($targetBooking['customer_name']);
        $this->assertIsString($targetBooking['email']);
        $this->assertIsString($targetBooking['phone_number']);
        $this->assertIsString($targetBooking['booking_total']);
        $this->assertIsString($targetBooking['paids']);
        $this->assertIsString($targetBooking['via']);
        $this->assertIsString($targetBooking['created_by_user_type']);
        $this->assertIsString($targetBooking['comments']);
        $this->assertIsString($targetBooking['note']);
        $this->assertIsInt($targetBooking['counter']);
        $this->assertIsString($targetBooking['background_colour_class']);
        $this->assertIsArray($targetBooking['dayBookings']);
    }

    /**
     * A date with no bookings returns 200 with empty slot data.
     */
    public function testEmptyResultForDateWithNoBookings(): void
    {
        $this->authAsStaff();

        $payload = [
            'date' => 20990101,
            'tour_package_id' => 0,
            'payment_status' => 0,
            'slot_id' => 0,
        ];

        $response = $this->postJson('/api/v3/agenda-day-time-slots', $payload);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data'),
            'Far-future date should have no bookings');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        static::$builders = [];

        if (static::$createdStaffId) {
            DB::table('tour_operator_staff')->where('id', static::$createdStaffId)->delete();
            static::$createdStaffId = null;
        }
    }
}
