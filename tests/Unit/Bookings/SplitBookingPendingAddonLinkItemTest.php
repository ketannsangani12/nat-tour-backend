<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Services\Booking\AddonBookingService;
use App\Services\Booking\PaymentLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): a booking with 4 already-paid (ticket-wise) pax has add-ons sent
 * via ONE combined "Payment Link" (all 4 pax's add-ons bundled into a single Unpaid
 * tour_booking_payment_link_items row of type='addon'). Splitting off only 2 of those 4 pax must:
 *   1. Reduce the OLD booking's pending addon item to just the 2 staying pax's share (not leave it
 *      at the full original amount, and not cancel it outright).
 *   2. Create a NEW pending addon item on the split-off booking's own link for its 2 pax's share.
 *   3. NEVER let those still-unpaid fields get marked 'paid' just because they now have no
 *      transaction row on their (new) booking — see AddonBookingService::
 *      createAddonTransactionsFromFields()'s "Source 1" exclusion.
 *
 * Root cause: PaymentLinkService::movePendingAddonPaymentLinkItems() (formerly a private method on
 * TourBookingRepository, used only by the mixed-unpaid-pax split path) either fully matched an item
 * (moving 100% of it) or left it fully alone — never partially — AND the plain (all-paid-pax) split
 * path never called it at all, so createAddonTransactionsFromFields() (run via CardBookingService as
 * part of "finalize this new booking") saw the just-moved, still-pending fields with no transaction
 * row and wrongly settled them as paid.
 */
class SplitBookingPendingAddonLinkItemTest extends TestCase
{
    protected static array $builders = [];

    protected static array $addonForms = [];
    protected static array $addonFields = [];

    private function makeBookingWithTwoPax(): CreateABooking
    {
        $builder = (new CreateABooking())->withTourists(2)->withRate(50.00)->withPaymentMethod('Card')->create();
        static::$builders[] = $builder;
        DB::table('tour_bookings_master')->where('id', $builder->bookingId)->update(['group_id' => $builder->bookingId]);
        return $builder;
    }

    /**
     * A real addon_fields row (not a guessed/hardcoded id — a fresh CI database won't have the
     * same seeded/accumulated ids a local dev database does), priced so it qualifies for
     * createAddonTransactionsFromFields()'s "Source 1" query (additional_fee + price > 0).
     */
    private function makeAddonField(int $packageId): AddonField
    {
        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name'            => 'Pending Addon Link Item Test Form ' . uniqid(),
            'status'          => 'Active',
        ]);
        static::$addonForms[] = $form;

        $field = AddonField::create([
            'form_id'        => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type'      => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status'         => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        static::$addonFields[] = $field;

        return $field;
    }

    /**
     * @return array{0: TourBookingAddonField, 1: TourBookingAddonField} [paxA field, paxB field]
     */
    private function makeAddonFieldsForBothPax(CreateABooking $booking): array
    {
        $addonField = $this->makeAddonField($booking->packageId);

        $fieldA = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $addonField->form_id, 'field_id' => $addonField->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'link-test-pax-0', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $fieldB = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $addonField->form_id, 'field_id' => $addonField->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'link-test-pax-1', 'person_ordinal' => 1,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        return [$fieldA, $fieldB];
    }

    /**
     * Mirrors the real shape found on a live booking: one item, two pax, no fee breakdown per
     * line (only item-level fee_amount/platform_fees), old_db_id null (brand-new fields at the
     * time the link was created), resolved via field_to_db.
     */
    private function makeSharedAddonLinkItem(int $groupId, TourBookingAddonField $fieldA, TourBookingAddonField $fieldB): array
    {
        $fieldId = $fieldA->field_id;
        $link = TourBookingPaymentLink::create([
            'order_id' => $groupId, 'section' => 'remaining_payment', 'amount' => 32.94,
            'reason_of_payment' => 'Payment for add-ons on your booking',
            'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'reference_id' => null,
            'amount' => 32.94, 'status' => 'Unpaid',
            'details' => [
                'financial' => [
                    'action' => 'charge',
                    'to_charge' => [
                        ['field_id' => $fieldId, 'old_db_id' => null, 'pax_index' => 'link-test-pax-0', 'charge_amount' => 15.00],
                        ['field_id' => $fieldId, 'old_db_id' => null, 'pax_index' => 'link-test-pax-1', 'charge_amount' => 15.00],
                    ],
                    'to_refund' => [],
                    'net_amount' => 30.00,
                    'fee_amount' => 2.94,
                    'total_charge' => 30.00,
                    'total_refund' => 0,
                    'total_to_charge' => 32.94,
                ],
                'field_to_db' => [
                    $fieldId . '_link-test-pax-0' => $fieldA->id,
                    $fieldId . '_link-test-pax-1' => $fieldB->id,
                ],
                'platform_fees' => 2.94,
                'booking_id' => $fieldA->tour_booking_id,
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
            'updated_by' => 1, 'updated_by_user_type' => 'Admin',
        ]);

        return [$link, $item];
    }

    /** @test */
    public function partially_reduces_the_old_item_and_creates_a_matching_item_on_the_new_booking(): void
    {
        $oldBooking = $this->makeBookingWithTwoPax();
        [$fieldA, $fieldB] = $this->makeAddonFieldsForBothPax($oldBooking);
        [$oldLink, $item] = $this->makeSharedAddonLinkItem($oldBooking->bookingId, $fieldA, $fieldB);

        // Stand-in for the booking the split flow creates for the split-off pax (field B).
        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)->useCustomer($oldBooking->customerId)
            ->withTourists(1)->withPaymentMethod('Card')->create();
        static::$builders[] = $newBooking;
        DB::table('tour_bookings_master')->where('id', $newBooking->bookingId)->update(['group_id' => $newBooking->bookingId]);

        // Move field B (and field B only) — mirrors AddonBookingService::splitBookingPaxAddons()
        // having already re-pointed it onto the new booking.
        $fieldB->update(['tour_booking_id' => $newBooking->bookingId]);

        try {
            PaymentLinkService::movePendingAddonPaymentLinkItems($oldBooking->bookingId, $newBooking->bookingId, [$fieldB->id]);

            $refreshedOldItem = TourBookingPaymentLinkItem::find($item->id);
            $this->assertEquals('Unpaid', $refreshedOldItem->status, 'Field A stays behind — the old item must NOT be cancelled outright');
            $this->assertEqualsWithDelta(16.47, (float) $refreshedOldItem->amount, 0.01, 'Old item must shrink to just field A\'s half ($15 + its half of the $2.94 fee)');

            $oldDetails = is_array($refreshedOldItem->details) ? $refreshedOldItem->details : json_decode($refreshedOldItem->details, true);
            $this->assertCount(1, $oldDetails['financial']['to_charge']);
            $this->assertEquals('link-test-pax-0', $oldDetails['financial']['to_charge'][0]['pax_index']);

            $newLink = TourBookingPaymentLink::where('order_id', $newBooking->bookingId)
                ->where('section', 'remaining_payment')->where('status', 'Unpaid')->first();
            $this->assertNotNull($newLink, 'A remaining_payment link must be created for the new booking');

            $newItem = TourBookingPaymentLinkItem::where('payment_link_id', $newLink->id)->where('type', 'addon')->first();
            $this->assertNotNull($newItem);
            $this->assertEquals('Unpaid', $newItem->status);
            $this->assertEqualsWithDelta(16.47, (float) $newItem->amount, 0.01, 'New item must carry field B\'s half');

            $newDetails = is_array($newItem->details) ? $newItem->details : json_decode($newItem->details, true);
            $this->assertCount(1, $newDetails['financial']['to_charge']);
            $this->assertEquals('link-test-pax-1', $newDetails['financial']['to_charge'][0]['pax_index']);

            // The field must NOT have been wrongly settled as paid by this move alone.
            $this->assertDatabaseMissing('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $fieldB->id,
                'status' => 'paid',
            ]);

            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $newLink->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $newLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $oldLink->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $oldLink->id)->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id])->forceDelete();
        }
    }

    /** @test */
    public function createAddonTransactionsFromFields_never_settles_a_field_still_covered_by_a_pending_link(): void
    {
        $booking = $this->makeBookingWithTwoPax();
        [$fieldA, $fieldB] = $this->makeAddonFieldsForBothPax($booking);
        [$link,] = $this->makeSharedAddonLinkItem($booking->bookingId, $fieldA, $fieldB);

        // A genuinely fresh, unrelated field with NO pending link coverage at all — this is the
        // case createAddonTransactionsFromFields() exists to settle, and must still work.
        $freshAddonField = $this->makeAddonField($booking->packageId);
        $freshField = TourBookingAddonField::create([
            'tour_booking_id' => $booking->bookingId, 'form_id' => $freshAddonField->form_id, 'field_id' => $freshAddonField->id,
            'pax_id' => $booking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'link-test-fresh', 'person_ordinal' => 2,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        try {
            AddonBookingService::createAddonTransactionsFromFields($booking->bookingId, null, 'card', true);

            // Field A/B (covered by the pending link) must get no transaction row at all — not
            // paid, not anything.
            $this->assertDatabaseMissing('tour_booking_addon_transactions', ['tour_booking_addon_id' => $fieldA->id]);
            $this->assertDatabaseMissing('tour_booking_addon_transactions', ['tour_booking_addon_id' => $fieldB->id]);

            // An unrelated fresh field with no pending link must still be settled normally.
            $this->assertDatabaseHas('tour_booking_addon_transactions', [
                'tour_booking_addon_id' => $freshField->id,
                'status' => 'paid',
            ]);
        } finally {
            DB::table('tour_booking_addon_transactions')->where('tour_booking_addon_id', $freshField->id)->delete();
            DB::table('tour_booking_payment_link_items')->where('payment_link_id', $link->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $link->id)->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$fieldA->id, $fieldB->id, $freshField->id])->forceDelete();
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$addonFields as $field) {
            DB::table('addon_fields')->where('id', $field->id)->delete();
        }
        foreach (static::$addonForms as $form) {
            DB::table('addon_forms')->where('id', $form->id)->delete();
        }
        foreach (static::$builders as $builder) {
            $builder->cleanup();
        }
        parent::tearDownAfterClass();
    }
}
