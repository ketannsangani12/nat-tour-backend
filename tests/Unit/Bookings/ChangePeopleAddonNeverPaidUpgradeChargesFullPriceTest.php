<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\ChangePeopleAddonService;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression: real-world repro (tenant `kens`, booking 348698) -- a pax added via Change People
 * with "Photography Package: Standard" ($5), sent as a payment link (never actually charged).
 * Before ever being paid, the admin edits the SAME pax's addon to "Premium" ($10) and pays by
 * Card. calculatePricedAddonDelta() computed the price/option DELTA ($5), as if $5 (Standard) had
 * already been collected -- producing a SECOND, separate $5.49 "Add-ons" ledger entry alongside
 * the pending link's own $21.96 entry once it was finalized (see
 * [[project_change_people_addon_bundled_remaining_link_fix]]), instead of one $27.45 entry for
 * the real, single, never-before-charged selection.
 *
 * Fixed: when the field's CURRENT (pre-edit) selection is still referenced by an Unpaid 'addon'
 * item on this booking's pending remaining_payment link (real production shape: the item's
 * `details.field_to_db` maps "fieldId_paxIndex" -> this field's db id), calculatePricedAddonDelta()
 * now charges the FULL new price (not new-minus-old) and flags the item
 * 'supersedes_pending_link' => true, so the caller can drop the old pending link line instead of
 * separately settling it. Must NOT change behavior for the overwhelmingly common case (the old
 * selection was already paid, or simply isn't on any pending link) -- that must keep computing an
 * ordinary delta. Deliberately detected via the real pending-link-item condition, not merely "no
 * paid transaction row exists" -- the latter is also true for unrelated reasons and would
 * misfire on an ordinary already-settled edit (caught by
 * test_updated_addon_option_change_nets_the_difference in ChangePeopleAddonServiceTest, which
 * constructs a field with neither a transaction row nor a pending link item and must still net an
 * ordinary delta).
 */
class ChangePeopleAddonNeverPaidUpgradeChargesFullPriceTest extends TestCase
{
    private function makeAddonField(int $packageId, string $value, float $fieldPrice, array $optionPrices): array
    {
        $form = AddonForm::create(['tour_package_id' => $packageId, 'name' => 'Never Paid Upgrade Form ' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => $fieldPrice > 0, 'price' => $fieldPrice, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $options = [];
        foreach ($optionPrices as $label => $price) {
            $options[$label] = AddonFieldOption::create([
                'field_id' => $field->id, 'label' => $label, 'value' => strtolower($label),
                'additional_fee' => true, 'price' => $price, 'sort_order' => 0,
            ]);
        }

        return [$form, $field, $options];
    }

    private function cleanupAddon($form, $field, $options, $row): void
    {
        DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $row->id)->delete();
        TourBookingAddonField::withTrashed()->where('id', $row->id)->forceDelete();
        foreach ($options as $option) {
            DB::table('addon_field_options')->where('id', $option->id)->delete();
        }
        DB::table('addon_fields')->where('id', $field->id)->delete();
        DB::table('addon_forms')->where('id', $form->id)->delete();
    }

    public function test_upgrade_of_a_never_paid_selection_charges_the_full_new_price_and_flags_supersedes_pending_link(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();
        $groupId = (string) $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $booking->bookingId)->update(['group_id' => $groupId]);

        [$form, $field, $options] = $this->makeAddonField(
            $booking->packageId,
            'standard',
            15.00,
            ['Standard' => 5.00, 'Premium' => 10.00]
        );

        $paxIndex = 'test-never-paid-0';
        $row = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $options['Standard']->id,
            'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndex, 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // Real production shape (tenant `kens`, booking 348698, link item 59): an Unpaid 'addon'
        // item on the booking's pending remaining_payment link, whose field_to_db references this
        // exact field row -- the condition the fix actually detects.
        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 21.96,
            'status' => 'Unpaid', 'override_status' => 'Yes', 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $linkItem = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'amount' => 21.96, 'status' => 'Unpaid',
            'details' => [
                'booking_id' => $booking->bookingId, 'form_id' => (string) $form->id, 'platform_fees' => 1.96,
                'field_to_db' => [$field->id . '_' . $paxIndex => $row->id],
                'financial' => ['action' => 'charge', 'net_amount' => 20, 'to_refund' => [], 'to_charge' => []],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [],
                [[
                    'db_id' => $row->id,
                    'field_id' => $field->id,
                    'option_id' => $options['Premium']->id,
                    'option_label' => 'Premium',
                    'value' => 'premium',
                    'pax_index' => 'test-never-paid-0',
                ]],
                [],
                9.82,
                true
            );

            $this->assertCount(1, $delta['chargeItems']);
            $chargeItem = $delta['chargeItems'][0];

            // Full Premium price (field $15 + option $10 = $25), NOT the $5 option delta.
            $this->assertEqualsWithDelta(25.00, $chargeItem['price'], 0.01, 'Must charge the full new price, not new-minus-old, when the old selection was never paid.');
            $this->assertTrue($chargeItem['supersedes_pending_link'], 'Must be flagged so the caller drops the superseded pending link line.');
            $this->assertFalse($chargeItem['is_price_delta'] ?? true);
            $this->assertEqualsWithDelta(25.00, $delta['net_price'], 0.01);
        } finally {
            TourBookingPaymentLinkItem::where('id', $linkItem->id)->delete();
            TourBookingPaymentLink::where('id', $link->id)->delete();
            $this->cleanupAddon($form, $field, $options, $row);
            $booking->cleanup();
        }
    }

    public function test_upgrade_of_an_already_paid_selection_still_charges_only_the_delta(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Card')->create();

        [$form, $field, $options] = $this->makeAddonField(
            $booking->packageId,
            'standard',
            15.00,
            ['Standard' => 5.00, 'Premium' => 10.00]
        );

        $row = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $options['Standard']->id,
            'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => 'test-already-paid-0', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // A real 'paid' transaction exists for the CURRENT (Standard) selection -- the normal,
        // overwhelmingly common case this fix must not change.
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $booking->bookingId, 'tour_booking_addon_id' => $row->id,
            'status' => 'paid', 'operation' => 'charge', 'transaction_id' => 'pi_already_paid_' . uniqid(),
            'field_price' => 15.00, 'field_fee' => 1.47, 'option_price' => 5.00, 'option_fee' => 0.49,
            'field_additional_fee' => true, 'option_additional_fee' => true,
        ]);

        try {
            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [],
                [[
                    'db_id' => $row->id,
                    'field_id' => $field->id,
                    'option_id' => $options['Premium']->id,
                    'option_label' => 'Premium',
                    'value' => 'premium',
                    'pax_index' => 'test-already-paid-0',
                ]],
                [],
                9.82,
                true
            );

            $this->assertCount(1, $delta['chargeItems']);
            $chargeItem = $delta['chargeItems'][0];

            // Only the $5 option delta (10 - 5) -- unchanged, pre-existing, correct behavior.
            $this->assertEqualsWithDelta(5.00, $chargeItem['price'], 0.01);
            $this->assertFalse($chargeItem['supersedes_pending_link'] ?? false);
            $this->assertTrue($chargeItem['is_price_delta']);
            $this->assertEqualsWithDelta(5.00, $delta['net_price'], 0.01);
        } finally {
            $this->cleanupAddon($form, $field, $options, $row);
            $booking->cleanup();
        }
    }
}
