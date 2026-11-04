<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\BookingTransact;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\PaymentLinkService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: a booking-level add-on sent via "Pay with Link" (e.g. Souvenir T-Shirt X-Large,
 * $25) and then EDITED before the customer paid (downgraded to Large, $22) must reprice that
 * pending addon payment-link item and regenerate the link — previously the link kept its old
 * token and old X-Large amount, and no new link was sent.
 *
 * Also covers the booking-level (non-pax) field_to_db lookup: those items key field_to_db by the
 * plain field_id and their lines carry no pax_index, which the old "{field_id}_{pax_index}"
 * lookup never matched — so deleting such a field never cancelled its pending item either.
 *
 * CMD: php artisan test --filter=EditAddonRepricesPendingLinkItemTest
 */
class EditAddonRepricesPendingLinkItemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    /**
     * Booking with one booking-level T-Shirt field (X-Large) sitting on a pending addon link
     * item shaped exactly like TourBookingAddonRepository::createAddonPaymentLink() writes it.
     */
    private function makeScenario(): array
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name'            => 'Edit Addon Pending Link Form ' . uniqid(),
            'status'          => 'Active',
        ]);

        $fieldDef = AddonField::create([
            'form_id'        => $form->id,
            'label'          => 'Souvenir T-Shirt Size',
            'type'           => 'dropdown',
            'unit_type'      => 'one_time',
            'additional_fee' => false,
            'price'          => 0,
            'status'         => 'Active',
            'visibility'     => 'both',
            'sort_order'     => 0,
        ]);
        $xl = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'X-Large', 'value' => 'x-large', 'additional_fee' => true, 'price' => 25.00, 'sort_order' => 0]);
        $lg = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Large', 'value' => 'large', 'additional_fee' => true, 'price' => 22.00, 'sort_order' => 1]);
        $xxl = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'XX-Large', 'value' => 'xx-large', 'additional_fee' => true, 'price' => 28.00, 'sort_order' => 2]);

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id'         => $form->id,
            'field_id'        => $fieldDef->id,
            'field_label'     => 'Souvenir T-Shirt Size',
            'option_id'       => $xl->id,
            'option_label'    => 'X-Large',
            'value'           => 'x-large',
            'unit_type'       => 'one_time',
            'unit'            => 1,
        ]);

        // The fixture's rate is 0% — use a real one so fee math is actually exercised.
        BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->update(['nat_fees_percentage' => 9.82]);
        $feePct = 9.82;
        $xlFee  = round(25.00 * $feePct / 100, 2);

        $link = TourBookingPaymentLink::create([
            'order_id'             => $groupId,
            'section'              => 'remaining_payment',
            'amount'               => 25.00 + $xlFee,
            'reason_of_payment'    => 'Payment for add-ons on your booking',
            'email'                => 'test@example.com',
            'phone_number'         => '5555551234',
            'token'                => Str::random(64),
            'status'               => 'Unpaid',
            'override_status'      => 'Yes',
            'expired_at'           => Carbon::now()->addDays(30),
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
        ]);

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id'      => $link->id,
            'type'                 => 'addon',
            'reference_id'         => null,
            'amount'               => 25.00 + $xlFee,
            'status'               => 'Unpaid',
            'details'              => [
                'platform_fees' => $xlFee,
                'form_id'       => $form->id,
                'booking_id'    => $bookingId,
                'field_to_db'   => [$fieldDef->id => $row->id],
                'financial'     => [
                    'action'       => 'charge',
                    'net_amount'   => 25.00,
                    'total_charge' => 25.00,
                    'total_refund' => 0,
                    'to_charge'    => [[
                        'field_id'      => $fieldDef->id,
                        'label'         => 'Souvenir T-Shirt Size (X-Large)',
                        'option_id'     => $xl->id,
                        'unit'          => 1,
                        'new_price'     => 25.00,
                        'old_db_id'     => null,
                        'old_paid'      => 0,
                        'charge_amount' => 25.00,
                        'field_price'   => 0,
                        'option_price'  => 25.00,
                        'reason'        => 'added',
                    ]],
                    'to_refund'    => [],
                ],
                'added'         => [[
                    'field_id' => $fieldDef->id, 'option_id' => $xl->id, 'option_label' => 'X-Large',
                    'value' => 'x-large', 'unit_type' => 'one_time', 'unit' => 1,
                ]],
                'updated'       => [],
                'pax'           => [],
            ],
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'updated_by'           => 1,
            'updated_by_user_type' => 'Admin',
        ]);

        return compact('booking', 'bookingId', 'form', 'fieldDef', 'xl', 'lg', 'xxl', 'row', 'feePct', 'link', 'item');
    }

    private function cleanup(array $s): void
    {
        TourBookingPaymentLinkItem::where('payment_link_id', $s['link']->id)->delete();
        DB::table('tour_booking_payment_links')->where('id', $s['link']->id)->delete();
        TourBookingAddonField::withTrashed()->where('tour_booking_id', $s['bookingId'])->forceDelete();
        DB::table('tour_booking_addon_transactions')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('tour_booking_history')->where('tour_booking_id', $s['bookingId'])->delete();
        DB::table('addon_field_options')->where('field_id', $s['fieldDef']->id)->delete();
        DB::table('addon_fields')->where('id', $s['fieldDef']->id)->delete();
        DB::table('addon_forms')->where('id', $s['form']->id)->delete();
        $s['booking']->cleanup();
    }

    private function updatedEntry(array $s, AddonFieldOption $option): array
    {
        return [
            'db_id'                 => $s['row']->id,
            'field_id'              => $s['fieldDef']->id,
            'option_id'             => $option->id,
            'option_label'          => $option->label,
            'value'                 => $option->value,
            'unit_type'             => 'one_time',
            'unit'                  => 1,
            'rule_id'               => null,
            'applied_rule_snapshot' => null,
        ];
    }

    public function test_downgrading_a_pending_link_addon_reprices_and_regenerates_the_link(): void
    {
        $s = $this->makeScenario();
        $oldToken = $s['link']->token;

        try {
            $result = app(TourBookingAddonRepository::class)->updateBookingAddons((object) [
                'booking_id' => $s['bookingId'],
                'form_id'    => $s['form']->id,
                'added'      => [],
                'updated'    => [$this->updatedEntry($s, $s['lg'])],
                'deleted'    => [],
                // Exactly what buildAddonPayload() sends for this never-paid downgrade.
                'financial'  => [
                    'action'       => 'refund',
                    'refund_type'  => 'dont_refund',
                    'net_amount'   => 3.00,
                    'total_charge' => 0,
                    'total_refund' => 0,
                    'to_charge'    => [],
                    'to_refund'    => [[
                        'db_id'         => $s['row']->id,
                        'field_id'      => $s['fieldDef']->id,
                        'label'         => 'Souvenir T-Shirt Size: X-Large → Large',
                        'refund_amount' => 0,
                        'credit_amount' => 3.00,
                        'reason'        => 'downgraded',
                    ]],
                ],
            ]);
            $this->assertTrue($result['success'], json_encode($result));

            $expected = round(22.00 + round(22.00 * $s['feePct'] / 100, 2), 2);

            $item = TourBookingPaymentLinkItem::find($s['item']->id);
            $this->assertEquals('Unpaid', $item->status);
            $this->assertEqualsWithDelta($expected, (float) $item->amount, 0.001, 'Pending item must be repriced to the Large price + fee');
            $line = $item->details['financial']['to_charge'][0];
            $this->assertEquals($s['lg']->id, $line['option_id']);
            $this->assertEqualsWithDelta(22.00, (float) $line['charge_amount'], 0.001);
            $this->assertEquals('Souvenir T-Shirt Size (Large)', $line['label']);
            $this->assertEquals($s['lg']->id, $item->details['added'][0]['option_id']);

            $link = TourBookingPaymentLink::find($s['link']->id);
            $this->assertEquals('Unpaid', $link->status);
            $this->assertEqualsWithDelta($expected, (float) $link->amount, 0.001, 'Link must carry the new amount');
            $this->assertNotEquals($oldToken, $link->token, 'Old link URL must stop working (token regenerated)');
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_upgrade_charged_in_full_drops_the_pending_line_without_deleting_the_field(): void
    {
        $s = $this->makeScenario();

        try {
            // Upgrade X-Large → XX-Large, charged in full ($28, old_paid 0) by this request itself.
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields(
                $s['bookingId'],
                [$s['row']->id],
                [$s['row']->id]
            );

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($s['item']->id)->status,
                'The old X-Large line must not stay billable alongside the new full charge');
            $this->assertNotNull(TourBookingAddonField::find($s['row']->id),
                'The (now separately charged) field row must NOT be deleted');
            $this->assertEquals('Cancelled', TourBookingPaymentLink::find($s['link']->id)->status);
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_repricing_a_partially_paid_line_charges_fee_only_on_the_remaining_delta(): void
    {
        $s = $this->makeScenario();

        // Large ($22) was already paid; the pending line is an upgrade to X-Large sent via link —
        // $3 still owed (old_paid 22), with field/option prices carrying the FULL X-Large breakdown
        // exactly as buildAddonPayload() sends them.
        $details = $s['item']->details;
        $details['financial']['to_charge'][0]['old_db_id']     = $s['row']->id;
        $details['financial']['to_charge'][0]['old_paid']      = 22.00;
        $details['financial']['to_charge'][0]['charge_amount'] = 3.00;
        $details['financial']['to_charge'][0]['reason']        = 'upgraded';
        $details['financial']['net_amount']                    = 3.00;
        $s['item']->details = $details;
        $s['item']->amount  = 3.00 + round(3.00 * $s['feePct'] / 100, 2);
        $s['item']->save();

        // Before the customer pays, the admin edits it to a $24 option.
        $mid = AddonFieldOption::create(['field_id' => $s['fieldDef']->id, 'label' => 'Large Plus', 'value' => 'large-plus', 'additional_fee' => true, 'price' => 24.00, 'sort_order' => 3]);
        $s['row']->update(['option_id' => $mid->id, 'option_label' => 'Large Plus', 'value' => 'large-plus']);

        try {
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($s['bookingId'], [$s['row']->id]);

            $item = TourBookingPaymentLinkItem::find($s['item']->id);
            $line = $item->details['financial']['to_charge'][0];
            $this->assertEqualsWithDelta(2.00, (float) $line['charge_amount'], 0.001, '$24 new price − $22 already paid');

            $expectedFee = round(2.00 * $s['feePct'] / 100, 2);
            $this->assertEqualsWithDelta($expectedFee, (float) $item->details['platform_fees'], 0.011,
                'Fee must be on the $2 still owed, not the full $24 new price');
            $this->assertEqualsWithDelta(2.00 + $expectedFee, (float) $item->amount, 0.011);
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_unrelated_edit_leaves_pending_item_untouched(): void
    {
        $s = $this->makeScenario();
        $oldToken = $s['link']->token;

        try {
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($s['bookingId'], [$s['row']->id + 999999]);

            $item = TourBookingPaymentLinkItem::find($s['item']->id);
            $this->assertEquals('Unpaid', $item->status);
            $this->assertEqualsWithDelta((float) $s['item']->amount, (float) $item->amount, 0.001);
            $this->assertEquals($oldToken, TourBookingPaymentLink::find($s['link']->id)->token);
        } finally {
            $this->cleanup($s);
        }
    }

    public function test_deleting_a_booking_level_pending_addon_cancels_its_link_item(): void
    {
        $s = $this->makeScenario();

        try {
            app(TourBookingAddonRepository::class)->updateBookingAddons((object) [
                'booking_id' => $s['bookingId'],
                'form_id'    => $s['form']->id,
                'added'      => [],
                'updated'    => [],
                'deleted'    => [['db_id' => $s['row']->id, 'field_id' => $s['fieldDef']->id]],
                'financial'  => ['action' => 'none', 'to_charge' => [], 'to_refund' => []],
            ]);

            $this->assertEquals('Cancelled', TourBookingPaymentLinkItem::find($s['item']->id)->status);
        } finally {
            $this->cleanup($s);
        }
    }

    /**
     * Regression: one "Pay with Link" send can bundle TWO separately-priced add-ons onto a
     * single TourBookingPaymentLinkItem (e.g. a T-Shirt + Sunglasses). Deleting just one of them
     * must only drop its own share and shrink the item, not cancel the whole thing — a sibling
     * field's still-legitimate pending charge must survive.
     */
    public function test_deleting_one_of_two_bundled_addons_shrinks_the_item_instead_of_cancelling_it(): void
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->withTransactOverrides(['nat_fees_percentage' => 10.00])->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name'            => 'Bundled Addon Partial Cancel Form ' . uniqid(),
            'status'          => 'Active',
        ]);
        $tshirtDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'T-Shirt', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 20.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $sunglassesDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Sunglasses', 'type' => 'checkbox',
            'unit_type' => 'one_time', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 1,
        ]);

        $tshirtRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $tshirtDef->id,
            'field_label' => 'T-Shirt', 'unit_type' => 'one_time', 'unit' => 1,
        ]);
        $sunglassesRow = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $sunglassesDef->id,
            'field_label' => 'Sunglasses', 'unit_type' => 'one_time', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 38.50,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid',
            'override_status' => 'Yes', 'expired_at' => Carbon::now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null,
            'amount' => 38.50, 'status' => 'Unpaid',
            'details' => [
                'platform_fees' => 3.50,
                'form_id' => $form->id, 'booking_id' => $bookingId,
                'field_to_db' => [$tshirtDef->id => $tshirtRow->id, $sunglassesDef->id => $sunglassesRow->id],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 35.00, 'total_charge' => 35.00, 'total_refund' => 0,
                    'to_charge' => [
                        [
                            'field_id' => $tshirtDef->id, 'label' => 'T-Shirt', 'option_id' => null, 'unit' => 1,
                            'new_price' => 20.00, 'old_db_id' => null, 'old_paid' => 0, 'charge_amount' => 20.00,
                            'field_price' => 20.00, 'option_price' => 0, 'field_fee' => 2.00, 'option_fee' => 0,
                            'reason' => 'added',
                        ],
                        [
                            'field_id' => $sunglassesDef->id, 'label' => 'Sunglasses', 'option_id' => null, 'unit' => 1,
                            'new_price' => 15.00, 'old_db_id' => null, 'old_paid' => 0, 'charge_amount' => 15.00,
                            'field_price' => 15.00, 'option_price' => 0, 'field_fee' => 1.50, 'option_fee' => 0,
                            'reason' => 'added',
                        ],
                    ],
                    'to_refund' => [],
                ],
                'added' => [], 'updated' => [], 'pax' => [],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            app(TourBookingAddonRepository::class)->updateBookingAddons((object) [
                'booking_id' => $bookingId,
                'form_id'    => $form->id,
                'added'      => [],
                'updated'    => [],
                'deleted'    => [['db_id' => $tshirtRow->id, 'field_id' => $tshirtDef->id]],
                'financial'  => ['action' => 'none', 'to_charge' => [], 'to_refund' => []],
            ]);

            $refreshed = TourBookingPaymentLinkItem::find($item->id);
            $this->assertEquals(
                'Unpaid',
                $refreshed->status,
                'Deleting the T-Shirt must not cancel the whole item -- Sunglasses is still owed'
            );
            $this->assertCount(1, $refreshed->details['financial']['to_charge']);
            $this->assertEquals($sunglassesDef->id, $refreshed->details['financial']['to_charge'][0]['field_id']);
            $this->assertEqualsWithDelta(16.50, (float) $refreshed->amount, 0.001, 'Item must shrink to just the Sunglasses charge + fee ($15 + $1.50)');
            $this->assertNotNull(TourBookingAddonField::find($sunglassesRow->id), 'Sunglasses field itself must be untouched');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->whereIn('id', [$tshirtDef->id, $sunglassesDef->id])->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields() taxed the
     * unscaled field_price/option_price (the field's FULL current price) instead of the actual
     * charge_amount (what's really still owed after subtracting old_paid) when repricing a
     * partially-paid line -- overcharging the fee on the regenerated link.
     */
    public function test_reprice_taxes_the_actual_remaining_delta_not_the_unscaled_full_price(): void
    {
        $booking   = (new CreateABooking())->withPaymentMethod('Card')->withTransactOverrides(['nat_fees_percentage' => 10.00])->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');
        $groupId   = (string) $bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => $groupId]);

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name'            => 'Fee Overcharge Repro Form ' . uniqid(),
            'status'          => 'Active',
        ]);
        $fieldDef = AddonField::create([
            'form_id' => $form->id, 'label' => 'Camera Rental', 'type' => 'dropdown',
            'unit_type' => 'one_time', 'additional_fee' => false, 'price' => 0,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        // The field's CURRENT selection: a $30 option.
        $premium = AddonFieldOption::create(['field_id' => $fieldDef->id, 'label' => 'Premium', 'value' => 'premium', 'additional_fee' => true, 'price' => 30.00, 'sort_order' => 0]);

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $fieldDef->id,
            'field_label' => 'Camera Rental', 'option_id' => $premium->id, 'option_label' => 'Premium',
            'value' => 'premium', 'unit_type' => 'one_time', 'unit' => 1,
        ]);

        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 7.70,
            'reason_of_payment' => 'Payment for add-ons on your booking', 'email' => 'test@example.com',
            'phone_number' => '5555551234', 'token' => Str::random(64), 'status' => 'Unpaid',
            'override_status' => 'Yes', 'expired_at' => Carbon::now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        // The pending line's stored state: $15 was already paid toward this field earlier
        // (old_paid), so charge_amount correctly reflects the $15 delta still owed -- but
        // field_price/option_price (as written by an earlier reprice pass) are the field's FULL
        // new price ($0/$30), not scaled down to match the $15 actually being charged.
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null,
            'amount' => 7.70, 'status' => 'Unpaid',
            'details' => [
                'platform_fees' => 0.70,
                'form_id' => $form->id, 'booking_id' => $bookingId,
                'field_to_db' => [$fieldDef->id => $row->id],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 15.00, 'total_charge' => 15.00, 'total_refund' => 0,
                    'to_charge' => [[
                        'field_id' => $fieldDef->id, 'label' => 'Camera Rental (Premium)',
                        'option_id' => $premium->id, 'unit' => 1,
                        'new_price' => 30.00, 'old_db_id' => null, 'old_paid' => 15.00,
                        'charge_amount' => 15.00, 'field_price' => 0, 'option_price' => 30.00,
                        'reason' => 'upgraded',
                    ]],
                    'to_refund' => [],
                ],
                'added' => [], 'updated' => [], 'pax' => [],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin', 'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        try {
            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($bookingId, [$row->id]);

            $refreshed = TourBookingPaymentLinkItem::find($item->id);
            // Correct: 10% of the real $15 delta actually owed = $1.50, not 10% of the unscaled
            // $30 full price ($3.00) -- which would double-charge the fee.
            $this->assertEqualsWithDelta(
                1.50,
                (float) $refreshed->details['platform_fees'],
                0.001,
                'Fee must be taxed on the actual remaining delta ($15), not the unscaled full new price ($30)'
            );
            $this->assertEqualsWithDelta(16.50, (float) $refreshed->amount, 0.001);
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_field_options')->where('field_id', $fieldDef->id)->delete();
            DB::table('addon_fields')->where('id', $fieldDef->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            DB::table('tour_booking_history')->where('tour_booking_id', $bookingId)->delete();
            $booking->cleanup();
        }
    }
}
