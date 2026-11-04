<?php

namespace Tests\Unit\Bookings;

use App\Repositories\Octo\OctoRepository;
use App\Services\Booking\BookingService;
use App\Services\TourPackageRate\TourPackageRateService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * save-affiliate-booking resolves the custom date through
 * TourPackageRateService::getCustomRateDate(). Its "standard rate date" lookup
 * passed the slot into BookingService::getCustomRateDate(), which returns a
 * slot specific rule before it looks at priority. So a lower priority slot rule
 * beat a higher priority general rule, unlike every other booking path.
 *
 * On kens, Ajoy (customer 2) had "May-Oct" $52 on the 10:00-14:00 slots at
 * priority 1 and "June - Dec | Standardized Rate" $49 on all slots at
 * priority 2. Seat blocks saved on 12:30 got $52 and the payment modal showed
 * $150.06 instead of $143.26 for 2 pax.
 */
class AffiliateCustomRateDatePriorityTest extends TestCase
{
    private const DATE = '2099-07-15';
    private const SLOT_TIME = '12:30:00';

    private int $packageId;
    private int $affiliateId;
    private ?int $slotDateId = null;
    private ?int $slotTimeId = null;
    private array $surchargeIds = [];
    private array $rateIds = [];
    private $originalCdrAffiliates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageId = (int) DB::table('tour_packages')->whereNull('deleted_at')->value('id');
        $this->affiliateId = (int) DB::table('customers')->where('is_affiliate', 'Yes')->whereNull('deleted_at')->value('id');
        $this->assertGreaterThan(0, $this->packageId, 'test tenant needs a tour package');
        $this->assertGreaterThan(0, $this->affiliateId, 'test tenant needs an affiliate');

        $this->originalCdrAffiliates = DB::table('settings')->where('key', 'CDR_AFFILIATES')->value('value');
        DB::table('settings')->where('key', 'CDR_AFFILIATES')->update(['value' => 1]);

        $this->slotDateId = DB::table('tour_slot_dates')->insertGetId([
            'tour_package_id' => $this->packageId,
            'date' => self::DATE,
            'seats' => 70,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->slotTimeId = DB::table('tour_slot_times')->insertGetId([
            'tour_slot_id' => $this->slotDateId,
            'tour_package_id' => $this->packageId,
            'time_slot_seats' => 70,
            'date' => self::DATE,
            'time_from' => self::SLOT_TIME,
            'time_to' => '13:30:00',
            'bookable_status' => 'Open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('tour_package_rates')->whereIn('id', $this->rateIds)->delete();
        DB::table('rate_slot_mappings')->whereIn('custom_date_id', $this->surchargeIds)->delete();
        DB::table('surcharge_dates')->whereIn('id', $this->surchargeIds)->delete();
        DB::table('tour_slot_times')->where('id', $this->slotTimeId)->delete();
        DB::table('tour_slot_dates')->where('id', $this->slotDateId)->delete();
        DB::table('settings')->where('key', 'CDR_AFFILIATES')->update(['value' => $this->originalCdrAffiliates]);

        parent::tearDown();
    }

    public function test_higher_priority_general_rule_beats_lower_priority_slot_rule()
    {
        $slotRule = $this->createRule('May-Oct', 1, [self::SLOT_TIME]);
        $generalRule = $this->createRule('Standardized Rate', 2);

        $this->assertNotSame($slotRule, $this->resolve());
        $this->assertSame($generalRule, $this->resolve());
    }

    public function test_higher_priority_slot_rule_still_wins_on_its_slot()
    {
        $this->createRule('Standardized Rate', 1);
        $slotRule = $this->createRule('Peak slots', 2, [self::SLOT_TIME]);

        $this->assertSame($slotRule, $this->resolve());
    }

    public function test_higher_priority_slot_rule_falls_back_to_general_on_other_slots()
    {
        $generalRule = $this->createRule('Standardized Rate', 1);
        $this->createRule('Peak slots', 2, ['10:00:00']);

        $this->assertSame($generalRule, $this->resolve());
    }

    /**
     * The shared resolver itself, as called with a slot by Edit Booking, Switch Package, the
     * V2 API, NetworkService etc. — not only the save-affiliate-booking path above.
     */
    public function test_shared_resolver_lets_higher_priority_general_rule_beat_slot_rule()
    {
        $this->createRule('May-Oct', 1, [self::SLOT_TIME]);
        $generalRule = $this->createRule('Standardized Rate', 2);

        $this->assertSame($generalRule, $this->resolveShared()->id);
    }

    public function test_shared_resolver_keeps_higher_priority_slot_rule()
    {
        $this->createRule('Standardized Rate', 1);
        $slotRule = $this->createRule('Peak slots', 2, [self::SLOT_TIME]);

        $this->assertSame($slotRule, $this->resolveShared()->id);
    }

    public function test_shared_resolver_keeps_slot_rule_on_equal_priority()
    {
        $this->createRule('Standardized Rate', 1);
        $slotRule = $this->createRule('Peak slots', 1, [self::SLOT_TIME]);

        $this->assertSame($slotRule, $this->resolveShared()->id);
    }

    public function test_shared_resolver_without_slot_ignores_slot_rules()
    {
        $generalRule = $this->createRule('Standardized Rate', 1);
        $this->createRule('Peak slots', 2, [self::SLOT_TIME]);

        $this->assertSame($generalRule, BookingService::getCustomRateDate(self::DATE, $this->packageId, 0, $this->affiliateId)->id);
    }

    /**
     * OCTO resolves availability pricing in memory (OctoRepository::resolvePricingByDate) and
     * must follow the same rule: a lower priority slot window no longer overrides the general one.
     */
    public function test_octo_pricing_skips_lower_priority_slot_rule()
    {
        $this->createRate(null, 40.00);
        $slotRule = $this->createRule('May-Oct', 1, [self::SLOT_TIME]);
        $this->createRate($slotRule, 52.00);
        $generalRule = $this->createRule('Standardized Rate', 2);
        $this->createRate($generalRule, 49.00);

        $pricing = $this->resolveOcto();

        $this->assertArrayNotHasKey(self::DATE . '_12:30', $pricing['by_slot_time']);
        $this->assertEquals(49.00, (float) $pricing['by_date'][self::DATE]->first()->rate, json_encode($pricing));
    }

    public function test_octo_pricing_keeps_higher_priority_slot_rule()
    {
        $this->createRate(null, 40.00);
        $generalRule = $this->createRule('Standardized Rate', 1);
        $this->createRate($generalRule, 49.00);
        $slotRule = $this->createRule('Peak slots', 2, [self::SLOT_TIME]);
        $this->createRate($slotRule, 52.00);

        $pricing = $this->resolveOcto();

        $this->assertEquals(52.00, (float) $pricing['by_slot_time'][self::DATE . '_12:30']->first()->rate, json_encode($pricing));
    }

    private function resolveOcto(): array
    {
        $method = new \ReflectionMethod(OctoRepository::class, 'resolvePricingByDate');
        $method->setAccessible(true);

        return $method->invoke(app(OctoRepository::class), $this->packageId, $this->affiliateId, self::DATE, self::DATE);
    }

    private function createRate(?int $customDateId, float $rate): void
    {
        $this->rateIds[] = DB::table('tour_package_rates')->insertGetId([
            'tour_package_id' => $this->packageId,
            'affiliate_id' => $this->affiliateId,
            'custom_date_id' => $customDateId,
            'rate_for' => 'Test Guest(s)',
            'rate' => $rate,
            'permit_fee' => 0,
            'additional_charge' => 0,
            'scheduling_year' => 2099,
            'status' => 'Active',
            'orderby' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveShared()
    {
        return BookingService::getCustomRateDate(self::DATE, $this->packageId, $this->slotTimeId, $this->affiliateId);
    }

    private function resolve()
    {
        $info = (object) ['date' => self::DATE, 'tour_package_id' => $this->packageId];
        $request = (object) ['affiliate_id' => $this->affiliateId, 'tour_slot_id' => $this->slotTimeId];

        return TourPackageRateService::getCustomRateDate($info, $request);
    }

    private function createRule(string $name, int $priority, array $slotTimes = []): int
    {
        $id = DB::table('surcharge_dates')->insertGetId([
            'tour_package_id' => $this->packageId,
            'affiliate_id' => $this->affiliateId,
            'custom_date_name' => $name,
            'date' => '2099-06-01',
            'date_range_start' => '2099-06-01',
            'date_range_end' => '2099-10-31',
            'apply_on_slots' => $slotTimes ? 1 : 0,
            'priority' => $priority,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->surchargeIds[] = $id;

        foreach ($slotTimes as $time) {
            DB::table('rate_slot_mappings')->insert([
                'tour_package_id' => $this->packageId,
                'affiliate_id' => $this->affiliateId,
                'custom_date_id' => $id,
                'time_from' => $time,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }
}
