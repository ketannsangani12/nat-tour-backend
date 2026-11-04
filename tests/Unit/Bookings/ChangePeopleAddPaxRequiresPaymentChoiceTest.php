<?php

namespace Tests\Unit\Bookings;

use App\Models\Admin;
use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Change People on a booking that has no completed payment yet (Pay Later / Payment Link) takes
 * the Pay Later fast path: the pax is committed right away (also on the preflight
 * validation_check=true call, since the payment-method modal is skipped for it) and a pending
 * lump-sum link is regenerated. A pending link no longer blocks that path.
 *
 * A booking that already has a completed payment still goes through the real Card / Payment Link
 * handling, which appends the new pax to its existing itemized remaining-payment link.
 *
 * CMD: php artisan test --filter=ChangePeopleAddPaxRequiresPaymentChoiceTest
 */
class ChangePeopleAddPaxRequiresPaymentChoiceTest extends TestCase
{
    protected TourBookingRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = app(TourBookingRepository::class);
    }

    protected function authAsAdmin(): Admin
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function baseRequestData(int $bookingId, CreateABooking $builder, int $newTouristCount): array
    {
        return [
            'bookingID' => $bookingId,
            'slot_time' => $builder->slotTimeId,
            'tourists' => [$newTouristCount],
            'rate_group' => [$builder->rateId],
            'permit_fee' => [0],
            'total_tourist' => $newTouristCount,
            'discount_percentage' => 0,
            'discount_fixed_money' => 0,
            'payment_method' => 'Pay Later',
            'infantorchilds' => 0,
            'payment_amount' => 0,
            'refund_amount' => 0,
        ];
    }

    public function test_adding_a_pax_commits_on_the_preflight_call_when_a_remaining_payment_link_is_pending(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        $bookingId = $builder->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'amount' => 73.30,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');
        $transactCountBefore = DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->count();
        $historyCountBefore = DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->count();

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            ['validation_check' => 'true']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $this->assertEquals(
                $touristsBefore + 1,
                (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'),
                'With no completed payment the Pay Later fast path commits the pax even while a remaining-payment link is pending'
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $linkId)->delete();
            $builder->cleanup();
        }
    }

    /**
     * A booking whose only pending balance is the original LUMP-SUM link (section=NULL, created at
     * booking creation for a Payment-Link booking) behaves like Pay Later -- no payment-method
     * choice is asked for (the change-people modal is skipped), so the pax is committed right away
     * and that same link is regenerated for the new total. (Only the ITEMIZED remaining_payment
     * shape above needs the preflight guard, since the Pay Later path cannot update that one.)
     */
    public function test_adding_a_pax_to_a_lump_sum_payment_link_booking_behaves_like_pay_later_and_grows_the_link(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        $bookingId = $builder->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => null,
            'amount' => 200.00,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            ['validation_check' => 'true']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $this->assertEquals(
                $touristsBefore + 1,
                (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'),
                'A lump-sum Payment Link booking commits the pax like Pay Later does'
            );

            $links = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->where('status', 'Unpaid')->get();
            $this->assertCount(1, $links, 'Exactly one Unpaid link must remain -- regenerated, not duplicated');
            $this->assertNull($links->first()->section, 'It stays the lump-sum link; no itemized link is created');
            $this->assertGreaterThan(200.00, (float) $links->first()->amount, 'The lump-sum link must grow by the added pax');
            $this->assertEquals(
                0,
                DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id'))->count()
            );
        } finally {
            $orderLinkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $orderLinkIds)->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $builder->cleanup();
        }
    }

    public function test_adding_a_pax_still_commits_on_the_real_submission_when_a_remaining_payment_link_is_pending(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        $bookingId = $builder->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'amount' => 73.30,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            ['validation_check' => 'false']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $this->assertEquals(
                $touristsBefore + 1,
                (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'),
                'The real (validation_check=false) submission must still actually add the pax'
            );
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $linkId)->delete();
            $builder->cleanup();
        }
    }

    /**
     * Regression: an explicit "Pay with Link" submission on a Split Booking-created booking must
     * route into the REAL Payment Link handler (PaymentLinkService::upsertRemainingPaymentLink),
     * not the generic "Pay Later" fast path -- which has no idea about this booking's own
     * remaining_payment link and settles everything as an unpaid "Billable" edit instead.
     */
    public function test_adding_a_pax_via_payment_link_updates_the_existing_remaining_payment_link_not_billable(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();
        $bookingId = $builder->bookingId;

        // Matches how the real Split Booking flow creates this link (PaymentLinkService::
        // upsertRemainingPaymentLink()): a link row PLUS a backing tour_booking_payment_link_items
        // row, since the link's amount is always derived by SUMMING its own Unpaid items, never
        // read from the link row's own `amount` column directly.
        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'amount' => 73.30,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $linkId,
            'type' => 'change_people',
            'reference_id' => null,
            'amount' => 73.30,
            'status' => 'Unpaid',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            [
                'payment_method' => 'Payment Link',
                'validation_check' => 'false',
                'payment_amount' => 73.30, // real, nonzero cost of the addition -- matches what the JS actually sends
                'refund_amount' => 0,
                'remaining_link_id' => $linkId,
            ]
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $this->assertEquals(
                $touristsBefore + 1,
                (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'),
                'The pax must actually be added on the real submission'
            );

            // Exactly one Unpaid remaining_payment link must still exist for this order -- updated
            // in place, not left stale alongside a duplicate.
            $links = DB::table('tour_booking_payment_links')
                ->where('order_id', $groupId)
                ->where('status', 'Unpaid')
                ->where('section', 'remaining_payment')
                ->get();
            $this->assertCount(1, $links, 'Exactly one remaining_payment link must exist -- updated, not duplicated');
            $this->assertGreaterThan(
                73.30,
                (float) $links->first()->amount,
                'The existing link amount must grow to reflect the newly added pax, not stay at its pre-addition amount'
            );

            $this->assertDatabaseMissing('tour_booking_history', [
                'tour_booking_id' => $bookingId,
                'payment_method' => 'Billable',
            ]);
        } finally {
            $orderLinkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $orderLinkIds)->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $builder->cleanup();
        }
    }

    /**
     * No completed payment, pending ITEMIZED link (e.g. Split Booking): the Pay Later fast path
     * commits the pax, and must also append its cost to that link -- otherwise the customer keeps
     * paying the old amount. The fee is deferred (recorded on service_commission when paid).
     */
    public function test_adding_a_pax_to_a_booking_with_an_itemized_link_and_no_payment_adds_it_to_that_link(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $builder->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'amount' => 73.30,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $linkId,
            'type' => 'change_people',
            'reference_id' => null,
            'amount' => 73.30,
            'status' => 'Unpaid',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('tour_bookings_master')->where('id', $bookingId)->first();

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, (int) $before->tourists + 1),
            ['validation_check' => 'false']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $after = DB::table('tour_bookings_master')->where('id', $bookingId)->first();
            $this->assertEquals((int) $before->tourists + 1, (int) $after->tourists);
            $this->assertEqualsWithDelta((float) $before->service_commission, (float) $after->service_commission, 0.001, 'The fee is not recorded before it is paid');

            $links = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->where('status', 'Unpaid')->where('section', 'remaining_payment')->get();
            $this->assertCount(1, $links, 'Updated in place, not duplicated');
            $this->assertGreaterThan(73.30, (float) $links->first()->amount, 'The link must grow by the added pax');

            $newItem = DB::table('tour_booking_payment_link_items')
                ->where('payment_link_id', $linkId)->where('type', 'change_people')->where('status', 'Unpaid')
                ->orderByDesc('id')->first();
            $details = json_decode($newItem->details, true);
            $this->assertTrue((bool) ($details['fee_deferred'] ?? false));
            $this->assertEqualsWithDelta(
                (float) $links->first()->amount,
                (float) DB::table('tour_booking_payment_link_items')->where('payment_link_id', $linkId)->where('status', 'Unpaid')->sum('amount'),
                0.01
            );
        } finally {
            $orderLinkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $orderLinkIds)->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $builder->cleanup();
        }
    }

    /**
     * The fee on the added pax follows the same per-pax rounding as the rest of Change People
     * (roundout(per-pax cost * %) * pax), not a total-based figure: 3 pax at 10.05 + 9.82% is
     * 3 * 0.99 = 2.97, whereas rounding the 30.15 total gives 2.96.
     */
    public function test_itemized_link_item_uses_per_pax_fee_rounding(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withRate(10.05)
            ->withPermitFee(0)
            ->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $builder->bookingId;

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 10.05, 'status' => 'Unpaid',
            'override_status' => 'Yes', 'expired_at' => now()->addDays(30), 'created_by' => 1,
            'created_by_user_type' => 'Admin', 'created_at' => now(),
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $linkId, 'type' => 'change_people', 'reference_id' => null, 'amount' => 10.05,
            'status' => 'Unpaid', 'created_by' => 1, 'created_by_user_type' => 'Admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rate = DB::table('tour_package_rates')->where('id', $builder->rateId)->first();
        $perPax = (float) $rate->rate + (float) $rate->additional_charge;
        $perPaxFee = roundout($perPax * 9.82 / 100, 2);
        $this->assertNotEquals(roundout(3 * $perPax * 9.82 / 100, 2), round(3 * $perPaxFee, 2), 'Fixture must make per-pax and total rounding differ');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, 4),
            ['validation_check' => 'false']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $newItem = DB::table('tour_booking_payment_link_items')
                ->where('payment_link_id', $linkId)->where('status', 'Unpaid')->orderByDesc('id')->first();
            $details = json_decode($newItem->details, true);
            $this->assertEqualsWithDelta(round(3 * $perPaxFee, 2), (float) $details['platform_fees'], 0.001);
            $this->assertEqualsWithDelta(round(3 * $perPax, 2), (float) $details['ticket_cost'], 0.001);
        } finally {
            $orderLinkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $orderLinkIds)->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $builder->cleanup();
        }
    }

    public function test_adding_a_pax_still_commits_on_the_preflight_call_when_no_payment_link_is_pending(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())->withTourists(1)->withoutTransaction()->create();
        $bookingId = $builder->bookingId;

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            ['validation_check' => 'true']
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $this->assertEquals(
                $touristsBefore + 1,
                (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists'),
                'A genuine Pay Later booking (no pending payment link at all) must keep committing on the preflight call, unchanged'
            );
        } finally {
            $builder->cleanup();
        }
    }

    /**
     * Regression for booking #348666 (tenant `kens`): the pending Payment Link amount shown to
     * the customer for a newly-added priced add-on must exactly match what actually gets charged
     * when they pay -- upsertChangePeopleAddonPaymentLink() independently re-derived the fee from
     * the combined net price (roundout($addonNetPrice * $natFeesPercentage / 100, 2)) instead of
     * reusing calculatePricedAddonDelta()'s own per-component sum (field_fee + option_fee, each
     * independently rounded), landing on the opposite side of a rounding boundary for a $25 addon
     * at 9.82%: $25 * 0.0982 = $2.455 rounds to $2.46 combined vs $1.47 + $0.98 = $2.45 summed --
     * a real, confirmed 1-cent overstatement (link showed $210.42, Stripe actually took $210.41).
     */
    public function test_addon_payment_link_amount_matches_the_per_component_fee_not_a_recomputed_one(): void
    {
        $this->authAsAdmin();

        $builder = (new CreateABooking())
            ->withTourists(1)
            ->withPaymentMethod('Card')
            ->withTransactOverrides(['nat_fees_percentage' => 9.82])
            ->create();
        $bookingId = $builder->bookingId;

        // A real AddonForm, not a hardcoded id -- addon_fields.form_id is a foreign key, and a
        // fresh CI-seeded database has no guarantee an addon_forms row with any particular id
        // exists (confirmed: CI failed with a 1452 constraint violation on form_id=1).
        $form = AddonForm::create([
            'tour_package_id' => $builder->packageId,
            'name' => 'Test Addon Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $field = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package ' . uniqid(),
            'type' => 'dropdown',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);
        $option = AddonFieldOption::create([
            'field_id' => $field->id,
            'label' => 'Premium',
            'value' => 'premium_' . uniqid(),
            'additional_fee' => true,
            'price' => 10.00,
            'sort_order' => 0,
        ]);

        $groupId = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        $linkId = DB::table('tour_booking_payment_links')->insertGetId([
            'order_id' => $groupId,
            'section' => 'remaining_payment',
            'amount' => 80.50,
            'status' => 'Unpaid',
            'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
        ]);
        DB::table('tour_booking_payment_link_items')->insert([
            'payment_link_id' => $linkId,
            'type' => 'change_people',
            'reference_id' => null,
            'amount' => 80.50,
            'status' => 'Unpaid',
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $touristsBefore = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tourists');

        $request = new Request();
        $request->merge(array_merge(
            $this->baseRequestData($bookingId, $builder, $touristsBefore + 1),
            [
                'payment_method' => 'Payment Link',
                'validation_check' => 'false',
                'payment_amount' => 80.50,
                'refund_amount' => 0,
                'remaining_link_id' => $linkId,
                'addons_added' => [[
                    'field_id' => $field->id,
                    'option_id' => $option->id,
                    'pax_index' => 'p-0',
                ]],
            ]
        ));

        try {
            $this->repo->changePeopleBooking($request);

            $addonItem = DB::table('tour_booking_payment_link_items')
                ->where('payment_link_id', $linkId)
                ->where('type', 'addon')
                ->first();

            $this->assertNotNull($addonItem, 'The addon must produce its own pending Payment Link item');
            $this->assertEquals(
                27.45,
                (float) $addonItem->amount,
                'The pending amount must match the per-component fee ($1.47 + $0.98) that will actually be charged, not a recomputed combined-fee value'
            );
        } finally {
            $orderLinkIds = DB::table('tour_booking_payment_links')->where('order_id', $groupId)->pluck('id');
            DB::table('tour_booking_payment_link_items')->whereIn('payment_link_id', $orderLinkIds)->delete();
            DB::table('tour_booking_payment_links')->where('order_id', $groupId)->delete();
            DB::table('tour_booking_addon_fields')->where('tour_booking_id', $bookingId)->delete();
            DB::table('addon_field_options')->where('id', $option->id)->delete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $builder->cleanup();
        }
    }
}
