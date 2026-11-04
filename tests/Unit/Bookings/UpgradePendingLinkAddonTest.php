<?php

namespace Tests\Unit\Bookings;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\PaymentLinkService;
use App\Services\Booking\StripeService;
use App\Services\Payments\StripePaymentGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: a Card booking gets a pax added via Change People with a Sweater (Medium, $15) on
 * the new pax, sent as a payment link — pending link = ticket item $80.50 + Sweater item
 * $15 + $1.47 fee = $96.97. Upgrading that still-pending Sweater to Large ($20) charges its FULL
 * new price (nothing was ever paid for Medium) and drops its old pending line, so the correct
 * total is $80.50 + $20 + $1.96 = $102.46.
 *
 * Before this fix the "Payment Required" preview showed $118.93 ($20 + $1.96 + the whole $96.97,
 * Medium still included), and the Card option actually charged that — and then settled the
 * Medium line too, recording the Sweater as paid for both Medium and Large.
 *
 * CMD: php artisan test --filter=UpgradePendingLinkAddonTest
 */
class UpgradePendingLinkAddonTest extends TestCase
{
    private const FEE_PCT = 9.82;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    private function makeScenario(): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->withTourists(2)->create();
        $bookingId = $booking->bookingId;
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);
        DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->where('deleted_by', 0)
            ->update(['nat_fees_percentage' => self::FEE_PCT]);

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Upgrade Pending Form ' . uniqid(), 'status' => 'Active']);
        $sweater = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sweater', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => false, 'price' => 0, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $medium = AddonFieldOption::create(['field_id' => $sweater->id, 'label' => 'Medium', 'value' => 'medium', 'additional_fee' => true, 'price' => 15.00, 'sort_order' => 0]);
        $large  = AddonFieldOption::create(['field_id' => $sweater->id, 'label' => 'Large', 'value' => 'large', 'additional_fee' => true, 'price' => 20.00, 'sort_order' => 1]);

        $paxIndex = 'test-upgrade-pax-2';
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $sweater->id,
            'field_label' => 'Sweater', 'option_id' => $medium->id, 'option_label' => 'Medium', 'value' => 'medium',
            'pax_id' => 1, 'pax_index' => $paxIndex, 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 96.97,
            'reason_of_payment' => 'Payment for added guests to your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => Carbon::now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        // Shapes exactly as Change People writes them (see the real item rows on booking #102946).
        $ticketItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'change_people', 'amount' => 80.50, 'status' => 'Unpaid',
            'details' => ['tourists' => 2, 'ticket_cost' => 73.3, 'platform_fees' => 7.2, 'tour_booking_id' => $bookingId, 'added_transact_data' => []],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);
        $addonItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'amount' => 16.47, 'status' => 'Unpaid',
            'details' => [
                'booking_id' => $bookingId, 'form_id' => (string) $form->id, 'platform_fees' => 1.47,
                'field_to_db' => [$sweater->id . '_' . $paxIndex => $row->id],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 15, 'to_refund' => [],
                    'to_charge' => [[
                        'label' => 'Sweater (Medium)', 'field_id' => $sweater->id, 'pax_index' => $paxIndex, 'old_db_id' => null,
                        'charge_amount' => 15, 'field_price' => 0, 'option_price' => 15, 'field_fee' => 0, 'option_fee' => 1.47,
                        'field_additional_fee' => false, 'option_additional_fee' => true,
                    ]],
                ],
                'added' => [], 'updated' => [], 'pax' => [],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        return compact('booking', 'bookingId', 'form', 'sweater', 'medium', 'large', 'paxIndex', 'row', 'link', 'ticketItem', 'addonItem');
    }

    private function upgradeLine(array $s): array
    {
        // What buildBulkAddonPayload() sends for Medium → Large on a never-paid field.
        return [
            'field_id' => $s['sweater']->id, 'pax_index' => $s['paxIndex'], 'label' => 'Sweater: Medium → Large',
            'option_id' => $s['large']->id, 'unit' => 1, 'new_price' => 20, 'old_db_id' => $s['row']->id, 'old_paid' => 0,
            'charge_amount' => 20, 'field_price' => 0, 'option_price' => 20,
            'field_additional_fee' => false, 'option_additional_fee' => true, 'reason' => 'upgraded',
        ];
    }

    private function cleanup(array $s): void
    {
        TourBookingPaymentLinkItem::where('payment_link_id', $s['link']->id)->delete();
        DB::table('tour_booking_payment_links')->where('order_id', (string) $s['bookingId'])->delete();
        TourBookingAddonField::withTrashed()->where('tour_booking_id', $s['bookingId'])->forceDelete();
        DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('addon_field_options')->where('field_id', $s['sweater']->id)->delete();
        DB::table('addon_fields')->where('id', $s['sweater']->id)->delete();
        DB::table('addon_forms')->where('id', $s['form']->id)->delete();
        $s['booking']->cleanup();
    }

    public function test_line_shares_expose_the_pending_sweater_value_for_the_preview(): void
    {
        $s = $this->makeScenario();
        try {
            $shares = PaymentLinkService::pendingAddonLinkLineShares($s['bookingId']);

            $this->assertArrayHasKey($s['row']->id, $shares);
            $this->assertEqualsWithDelta(15.00, $shares[$s['row']->id]['net'], 0.001);
            $this->assertEqualsWithDelta(1.47, $shares[$s['row']->id]['fee'], 0.001);
            $this->assertCount(1, $shares, 'Only add-on lines have shares — the ticket item is not an add-on');
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_card_payment_intent_leaves_the_superseded_pending_line_out(): void
    {
        $s = $this->makeScenario();
        try {
            $captured = null;
            $real = new StripePaymentGateway();
            $gateway = $this->createMock(PaymentGatewayInterface::class);
            $gateway->method('listCustomers')->willReturn((object) ['data' => []]);
            $gateway->method('createCustomer')->willReturn((object) ['id' => 'cus_test_' . uniqid()]);
            $gateway->method('listPaymentMethods')->willReturn((object) ['data' => []]);
            $gateway->method('buildConnectedPaymentData')
                ->willReturnCallback(fn (string $d, int $f, ?string $sd = null, string $m = 'destination') => $real->buildConnectedPaymentData($d, $f, $sd, $m));
            $gateway->method('createPayment')->willReturnCallback(function (array $payload) use (&$captured) {
                $captured = $payload;
                return (object) ['id' => 'pi_test_' . uniqid(), 'client_secret' => 'secret_test'];
            });
            $this->app->instance(PaymentGatewayInterface::class, $gateway);

            $request = new Request();
            $request->merge([
                'bookingId'       => $s['bookingId'],
                'net_amount'      => 20.00,
                'financial_items' => json_encode(['to_charge' => [$this->upgradeLine($s)], 'to_refund' => []]),
            ]);
            StripeService::stripeCreatePaymentIntentForAddonEditCharge($request);

            $this->assertNotNull($captured);
            // Large $20 + $1.96 fee + (pending $96.97 − Medium's $16.47) = $102.46, not $118.93.
            $this->assertEquals(10246, $captured['amount']);
            // Platform cut: Large's $1.96 + the ticket item's $7.20 — Medium's $1.47 left out.
            $this->assertEquals(916, $captured['application_fee_amount']);
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_card_upgrade_drops_the_old_pending_line_before_the_link_is_settled(): void
    {
        $s = $this->makeScenario();
        $oldToken = $s['link']->token;
        try {
            // Card charge without a PaymentIntent id — exercises the pending-link sync that runs
            // before RemainingPaymentService::finalizeItems() (which needs a real PI, so it's
            // skipped here).
            $result = app(TourBookingAddonRepository::class)->updatePaxAddons((object) [
                'booking_id' => $s['bookingId'],
                'form_id'    => $s['form']->id,
                'pax'        => [[
                    'pax_id' => 1, 'pax_index' => $s['paxIndex'], 'person_ordinal' => 1,
                    'added' => [], 'deleted' => [],
                    'updated' => [[
                        'db_id' => $s['row']->id, 'field_id' => $s['sweater']->id, 'option_id' => $s['large']->id,
                        'option_label' => 'Large', 'value' => 'large', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ]],
                ]],
                'financial'  => [
                    'action' => 'charge', 'payment_method' => 'Card', 'net_amount' => 20.00,
                    'to_charge' => [$this->upgradeLine($s)], 'to_refund' => [],
                ],
            ]);
            $this->assertTrue($result['success'], json_encode($result));

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($s['addonItem']->id)->status,
                'The Medium line must not be settled again alongside the Large card charge');
            $this->assertEquals('Unpaid', TourBookingPaymentLinkItem::find($s['ticketItem']->id)->status,
                'The added pax ticket stays on the link for finalizeItems() to settle with the same PI');

            $link = TourBookingPaymentLink::find($s['link']->id);
            $this->assertEquals($oldToken, $link->token, 'No regenerated link/email mid card payment');
            $this->assertEquals('Unpaid', $link->status);
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_payment_link_upgrade_replaces_the_old_line_with_the_new_full_price(): void
    {
        $s = $this->makeScenario();
        try {
            $result = app(TourBookingAddonRepository::class)->createAddonPaymentLink((object) [
                'booking_id' => $s['bookingId'],
                'form_id'    => $s['form']->id,
                'added'      => [], 'updated' => [], 'deleted' => [],
                'pax'        => [[
                    'pax_id' => 1, 'pax_index' => $s['paxIndex'], 'person_ordinal' => 1,
                    'added' => [], 'deleted' => [],
                    'updated' => [[
                        'db_id' => $s['row']->id, 'field_id' => $s['sweater']->id, 'option_id' => $s['large']->id,
                        'option_label' => 'Large', 'value' => 'large', 'unit_type' => 'price_per_pax', 'unit' => 1,
                        'rule_id' => null, 'applied_rule_snapshot' => null,
                    ]],
                ]],
                'financial'  => [
                    'action' => 'charge', 'net_amount' => 20.00,
                    'to_charge' => [$this->upgradeLine($s)], 'to_refund' => [],
                ],
            ]);
            $this->assertTrue($result['success'], json_encode($result));

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($s['addonItem']->id)->status);
            $this->assertEqualsWithDelta(102.46, (float) TourBookingPaymentLink::find($s['link']->id)->amount, 0.001,
                'Link = ticket $80.50 + Large $20 + $1.96 — the same total the preview now shows');
        } finally {
            $this->cleanup($s);
        }
    }
}
