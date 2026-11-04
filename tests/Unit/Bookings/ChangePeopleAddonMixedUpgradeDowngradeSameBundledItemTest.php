<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonFieldOption;
use App\Models\AddonForm;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\TourBookingPaymentLink;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Services\Booking\ChangePeopleAddonService;
use App\Services\Booking\PaymentLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Stress test for the interaction between two INDEPENDENT fixes that can both touch the SAME
 * bundled `tour_booking_payment_link_items` row in a single Change People edit:
 *  - reduceChangePeopleAddonPaymentLink() (pre-existing) drops a downgraded/removed field's
 *    pending charge entirely.
 *  - The new "supersedes_pending_link" drop (this session, see
 *    [[project_change_people_addon_bundled_remaining_link_fix]] Rounds 2-4) drops an
 *    upgraded-from-never-paid field's pending charge via
 *    PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields().
 *
 * A single pending 'addon' item can bundle MULTIPLE pax's fields together (documented and tested
 * elsewhere, e.g. ChangePeopleAddonRemovalShrinksPendingLinkTest's "bundled" case). This test
 * proves that when ONE edit upgrades one bundled pax's never-paid field (hitting the NEW code) AND
 * REMOVES a DIFFERENT bundled pax entirely (an unconditional refundItem regardless of paid status
 * -- calculatePricedAddonDelta()'s $removedAddons loop, hitting the EXISTING
 * reduceChangePeopleAddonPaymentLink() code) AND leaves a THIRD bundled pax's field completely
 * untouched, running both functions in TourBookingRepository::changePeopleBooking()'s real order
 * (reduce, then sync) correctly narrows the item down to just the untouched field's own original
 * line -- neither function clobbers the other's work, because both re-read the item's current
 * `details` from the DB at call time and only ever touch the specific field ids they were given.
 *
 * (A never-paid field being merely DOWNGRADED in option, not removed, is NOT this scenario --
 * calculatePricedAddonDelta()'s never-paid override charges its full new price regardless of
 * whether that's nominally higher or lower than the old, uncollected price, so it always lands in
 * chargeItems, never refundItems, unless downgraded all the way to a free $0 option.)
 */
class ChangePeopleAddonMixedUpgradeDowngradeSameBundledItemTest extends TestCase
{
    private function reduceViaReflection(array $refundItems, TourBooking $tourBooking): void
    {
        $repo = app(TourBookingRepository::class);
        $method = new ReflectionMethod($repo, 'reduceChangePeopleAddonPaymentLink');
        $method->setAccessible(true);
        $method->invoke($repo, $refundItems, $tourBooking);
    }

    public function test_upgrade_and_downgrade_on_the_same_bundled_item_do_not_clobber_each_other_or_the_untouched_third_pax(): void
    {
        $booking = (new CreateABooking())->withTourists(1)->withPaymentMethod('Cash')->create();
        $bookingId = $booking->bookingId;
        DB::table('tour_bookings_master')->where('id', $bookingId)->update(['group_id' => (string) $bookingId]);
        // syncPendingAddonLinkItemsForUpdatedFields() recomputes the KEPT line's fee from the
        // booking's own nat_fees_percentage when recalculate=true -- must match the 9.82% this
        // test's fixture amounts (and calculatePricedAddonDelta() calls below) already assume.
        DB::table('tour_bookings_transact')->where('tour_booking_id', $bookingId)->update(['nat_fees_percentage' => 9.82]);

        $form = AddonForm::create(['tour_package_id' => $booking->packageId, 'name' => 'Mixed Bundle Form ' . uniqid(), 'status' => 'Active']);
        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'dropdown', 'unit_type' => 'price_per_pax',
            'additional_fee' => true, 'price' => 15.00, 'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        $standard = AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Standard', 'value' => 'standard', 'additional_fee' => true, 'price' => 5.00, 'sort_order' => 0]);
        $premium = AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Premium', 'value' => 'premium', 'additional_fee' => true, 'price' => 10.00, 'sort_order' => 1]);
        $deluxe = AddonFieldOption::create(['field_id' => $field->id, 'label' => 'Deluxe', 'value' => 'deluxe', 'additional_fee' => true, 'price' => 15.00, 'sort_order' => 2]);

        // Pax A: upgrading Standard($5) -> Premium($10), never paid -- hits the NEW code.
        $paxIndexA = 'bundle-a-0';
        $rowA = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $standard->id, 'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndexA, 'person_ordinal' => 0, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        // Pax B: downgrading Deluxe($15) -> Standard($5), never paid -- hits the EXISTING code.
        $paxIndexB = 'bundle-b-1';
        $rowB = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $deluxe->id, 'option_label' => 'Deluxe', 'value' => 'deluxe',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndexB, 'person_ordinal' => 1, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        // Pax C: untouched -- Standard($5), never paid, no edit at all this round.
        $paxIndexC = 'bundle-c-2';
        $rowC = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId, 'form_id' => $form->id, 'field_id' => $field->id,
            'field_label' => 'Photography Package', 'option_id' => $standard->id, 'option_label' => 'Standard', 'value' => 'standard',
            'pax_id' => $booking->transactId, 'pax_index' => $paxIndexC, 'person_ordinal' => 2, 'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // ONE bundled pending 'addon' item covering all three pax's never-paid selections:
        // field($15) + option($5 or $15) + fee(9.82%) each -> $20+$1.96=$21.96 (A, C) / $30+$2.95=$32.95 (B).
        $link = TourBookingPaymentLink::create([
            'order_id' => (string) $bookingId, 'section' => 'remaining_payment', 'amount' => 76.87,
            'status' => 'Unpaid', 'override_status' => 'Yes', 'token' => Str::random(64),
            'expired_at' => now()->addDays(30), 'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);
        $item = TourBookingPaymentLinkItem::create([
            'payment_link_id' => $link->id, 'type' => 'addon', 'amount' => 76.87, 'status' => 'Unpaid',
            'details' => [
                'booking_id' => $bookingId, 'form_id' => (string) $form->id, 'platform_fees' => 6.87,
                'field_to_db' => [
                    $field->id . '_' . $paxIndexA => $rowA->id,
                    $field->id . '_' . $paxIndexB => $rowB->id,
                    $field->id . '_' . $paxIndexC => $rowC->id,
                ],
                'financial' => [
                    'action' => 'charge', 'net_amount' => 70.00, 'to_refund' => [],
                    'to_charge' => [
                        ['label' => 'Photography Package (Standard)', 'field_id' => $field->id, 'field_fee' => 1.47, 'old_db_id' => null, 'pax_index' => $paxIndexA, 'option_fee' => 0.49, 'field_price' => 15, 'option_price' => 5, 'charge_amount' => 20, 'field_additional_fee' => true, 'option_additional_fee' => true],
                        ['label' => 'Photography Package (Deluxe)', 'field_id' => $field->id, 'field_fee' => 1.47, 'old_db_id' => null, 'pax_index' => $paxIndexB, 'option_fee' => 1.47, 'field_price' => 15, 'option_price' => 15, 'charge_amount' => 30, 'field_additional_fee' => true, 'option_additional_fee' => true],
                        ['label' => 'Photography Package (Standard)', 'field_id' => $field->id, 'field_fee' => 1.47, 'old_db_id' => null, 'pax_index' => $paxIndexC, 'option_fee' => 0.49, 'field_price' => 15, 'option_price' => 5, 'charge_amount' => 20, 'field_additional_fee' => true, 'option_additional_fee' => true],
                    ],
                ],
            ],
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        try {
            // Compute the real delta exactly like changePeopleBooking() does: pax A upgrades
            // (never paid -> full new price + supersedes_pending_link); pax B is REMOVED entirely
            // (calculatePricedAddonDelta()'s $removedAddons loop always lands in refundItems,
            // regardless of paid status -- the realistic case that exercises
            // reduceChangePeopleAddonPaymentLink() on a never-paid bundled field).
            $delta = ChangePeopleAddonService::calculatePricedAddonDelta(
                [],
                [
                    ['db_id' => $rowA->id, 'field_id' => $field->id, 'option_id' => $premium->id, 'option_label' => 'Premium', 'value' => 'premium', 'pax_index' => $paxIndexA],
                ],
                [
                    ['db_id' => $rowB->id],
                ],
                9.82,
                true
            );

            $this->assertCount(1, $delta['chargeItems'], 'Pax A\'s upgrade is the only charge item.');
            $this->assertTrue($delta['chargeItems'][0]['supersedes_pending_link']);
            $this->assertCount(1, $delta['refundItems'], 'Pax B\'s removal is the only refund item.');

            // Real order in TourBookingRepository::changePeopleBooking(): reduce (existing code)
            // runs first, THEN the new supersedes-pending-link drop.
            $this->reduceViaReflection($delta['refundItems'], TourBooking::find($bookingId));

            $afterReduce = TourBookingPaymentLinkItem::find($item->id);
            $this->assertEquals('Unpaid', $afterReduce->status, 'Pax A and Pax C\'s lines survive pax B\'s removal.');
            $detailsAfterReduce = is_array($afterReduce->details) ? $afterReduce->details : json_decode($afterReduce->details, true);
            $this->assertCount(2, $detailsAfterReduce['financial']['to_charge'], 'Only pax B\'s line was dropped so far.');

            $supersededFieldIds = array_column(
                array_filter($delta['chargeItems'], fn ($chargeItem) => !empty($chargeItem['supersedes_pending_link'])),
                'db_id'
            );
            $this->assertEquals([$rowA->id], $supersededFieldIds);

            PaymentLinkService::syncPendingAddonLinkItemsForUpdatedFields($bookingId, $supersededFieldIds, $supersededFieldIds, true);

            $final = TourBookingPaymentLinkItem::find($item->id);
            $this->assertEquals('Unpaid', $final->status, 'Pax C\'s untouched line keeps the item alive -- must not be cancelled outright.');

            $finalDetails = is_array($final->details) ? $final->details : json_decode($final->details, true);
            $this->assertCount(1, $finalDetails['financial']['to_charge'], 'Only pax C\'s original line remains -- A (superseded) and B (removed) are both gone.');
            $this->assertEquals($paxIndexC, $finalDetails['financial']['to_charge'][0]['pax_index']);

            // Pax C's own original pending amount ($20 + $1.96 fee = $21.96) is exactly what's left.
            $this->assertEqualsWithDelta(21.96, (float) $final->amount, 0.01, 'Only pax C\'s untouched $21.96 should remain on the item.');

            $finalLink = TourBookingPaymentLink::find($link->id);
            $this->assertEquals('Unpaid', $finalLink->status, 'The link stays Unpaid -- pax C\'s pending amount is still genuinely owed.');
        } finally {
            TourBookingPaymentLinkItem::where('payment_link_id', $link->id)->delete();
            TourBookingPaymentLink::where('id', $link->id)->delete();
            DB::table('tour_booking_addon_transactions')->whereIn('tour_booking_addon_id', [$rowA->id, $rowB->id, $rowC->id])->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$rowA->id, $rowB->id, $rowC->id])->forceDelete();
            DB::table('addon_field_options')->where('field_id', $field->id)->delete();
            DB::table('addon_fields')->where('id', $field->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
