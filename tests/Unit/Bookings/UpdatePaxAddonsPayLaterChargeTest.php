<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class UpdatePaxAddonsPayLaterChargeTest extends TestCase
{
    public function test_pay_later_addition_via_add_passenger_addons_popup_does_not_store_card(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->withoutTransaction()->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Repro Form ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => $booking->transactId,
                        'pax_index' => 'repro-adult-1',
                        'person_ordinal' => 0,
                        'added' => [
                            [
                                'field_id' => $photoField->id,
                                'option_id' => null,
                                'option_label' => null,
                                'value' => null,
                                'unit_type' => 'price_per_pax',
                                'unit' => 1,
                                'rule_id' => null,
                                'applied_rule_snapshot' => null,
                            ],
                        ],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
                'financial' => [
                    'action' => 'charge',
                    'net_amount' => 15.00,
                    'total_charge' => 15.00,
                    'total_refund' => 0,
                    'to_charge' => [
                        [
                            'field_id' => $photoField->id,
                            'pax_index' => 'repro-adult-1',
                            'label' => 'Photography Package',
                            'option_id' => null,
                            'unit' => 1,
                            'new_price' => 15.00,
                            'old_db_id' => null,
                            'old_paid' => 0,
                            'charge_amount' => 15.00,
                            'field_price' => 15.00,
                            'option_price' => 0,
                            'field_additional_fee' => true,
                            'option_additional_fee' => false,
                            'reason' => 'added',
                        ],
                    ],
                    'to_refund' => [],
                    // Exactly what the JS sends: this.bookingPaymentVia, which resolves from the
                    // blade's `var paymentMethod = "{{ ($trans==null)?'Pay Later':$trans->via }}"`.
                    'payment_method' => 'Pay Later',
                ],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $photoField->id)
                ->first();
            $this->assertNotNull($newField, 'Field row should have been created');

            $txn = TourBookingAddonTransaction::where('tour_booking_addon_id', $newField->id)
                ->orderByDesc('id')->first();
            $this->assertNotNull($txn);
            $this->assertNotEquals('card', $txn->payment_method, 'Ledger row must not claim card for a Pay Later charge');
            $this->assertEquals('open', $txn->status);

            $transactionsRow = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->first();
            $this->assertNull($transactionsRow, 'No real transactions-ledger row should be created for a Pay Later (nothing collected) charge');

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('charge_type', 'addons')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the addition');
            $details = json_decode($historyRow->details, true);
            $this->assertEquals('Photography Package', $details['addons'][0]['name']);
            $this->assertEquals('', $details['addons'][0]['payment_method'], 'Item-level payment method must be blank, not "card"');
            $this->assertNull($details['payment_type']);
        } finally {
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            TourBookingAddonTransaction::where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Same repro, but for a booking that (like most real Pay Later bookings created through the
     * normal checkout flow) DOES have a `transactions` row for the arrangement itself — via
     * 'Pay Later', charge_type NULL, status 'Completed' — NOT a real card charge. Confirms
     * resolveBookingPaymentMethodLabel()-driven paths don't leak "Pay Later" itself in as if it
     * were a real payment method either.
     */
    public function test_pay_later_addition_with_a_pay_later_arrangement_row_does_not_store_card(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Repro Form 2 ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => $booking->transactId,
                        'pax_index' => 'repro-adult-2',
                        'person_ordinal' => 0,
                        'added' => [
                            [
                                'field_id' => $photoField->id,
                                'option_id' => null,
                                'option_label' => null,
                                'value' => null,
                                'unit_type' => 'price_per_pax',
                                'unit' => 1,
                                'rule_id' => null,
                                'applied_rule_snapshot' => null,
                            ],
                        ],
                        'updated' => [],
                        'deleted' => [],
                    ],
                ],
                'financial' => [
                    'action' => 'charge',
                    'net_amount' => 15.00,
                    'total_charge' => 15.00,
                    'total_refund' => 0,
                    'to_charge' => [
                        [
                            'field_id' => $photoField->id,
                            'pax_index' => 'repro-adult-2',
                            'label' => 'Photography Package',
                            'option_id' => null,
                            'unit' => 1,
                            'new_price' => 15.00,
                            'old_db_id' => null,
                            'old_paid' => 0,
                            'charge_amount' => 15.00,
                            'field_price' => 15.00,
                            'option_price' => 0,
                            'field_additional_fee' => true,
                            'option_additional_fee' => false,
                            'reason' => 'added',
                        ],
                    ],
                    'to_refund' => [],
                    'payment_method' => 'Pay Later',
                ],
            ]);

            $newField = TourBookingAddonField::where('tour_booking_id', $bookingId)
                ->where('field_id', $photoField->id)
                ->first();
            $txn = TourBookingAddonTransaction::where('tour_booking_addon_id', $newField->id)
                ->orderByDesc('id')->first();
            $this->assertNotEquals('card', $txn->payment_method);
        } finally {
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            TourBookingAddonTransaction::where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }

    /**
     * Regression: removing a Pay Later add-on (Add Passenger Add-ons popup, uncheck with nothing
     * else changed — financial.action='refund') on a booking whose `transactions` table DOES
     * have a Completed/charge_type-null row for the Pay Later arrangement itself (via='Pay
     * Later' — exactly what CreateABooking's default, non-withoutTransaction() setup leaves
     * behind, and what a real booking can genuinely have) showed a "Pay Later" chip next to
     * "Don't Refund" in History & Notes. resolveBookingPaymentMethodLabel() filtered by
     * status='Completed' but never excluded via='Pay Later' itself, even though its own docblock
     * already promised it never returns that — the lookup just didn't actually enforce it.
     */
    public function test_pay_later_removal_does_not_show_pay_later_as_the_payment_method(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Pay Later')->create();
        $bookingId = $booking->bookingId;

        $packageId = (int) DB::table('tour_bookings_master')->where('id', $bookingId)->value('tour_package_id');

        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name' => 'Repro Form 3 ' . uniqid(),
            'status' => 'Active',
        ]);

        $photoField = AddonField::create([
            'form_id' => $form->id,
            'label' => 'Photography Package',
            'type' => 'checkbox',
            'unit_type' => 'price_per_pax',
            'additional_fee' => true,
            'price' => 15.00,
            'status' => 'Active',
            'visibility' => 'both',
            'sort_order' => 0,
        ]);

        // Matches what an earlier Pay Later addition already left behind: field row + an 'open'
        // status ledger row (never 'paid', since nothing was collected).
        $existingField = TourBookingAddonField::create([
            'tour_booking_id' => $bookingId,
            'form_id' => $form->id,
            'field_id' => $photoField->id,
            'pax_id' => $booking->transactId,
            'field_label' => 'Photography Package',
            'pax_index' => 'repro-removal-adult-1',
            'person_ordinal' => 0,
            'unit_type' => 'price_per_pax',
            'unit' => 1,
        ]);
        TourBookingAddonTransaction::create([
            'tour_booking_id' => $bookingId, 'tour_booking_addon_id' => $existingField->id,
            'payment_method' => 'pay later', 'status' => 'open', 'operation' => 'charge',
            'transaction_id' => null, 'field_price' => 15.00, 'field_fee' => 0,
            'option_price' => 0, 'option_fee' => 0,
            'operation_at' => now(), 'operation_by' => 0, 'operation_by_user_type' => 'Admin',
        ]);

        try {
            $repo = app(TourBookingAddonRepository::class);
            $repo->updatePaxAddons((object) [
                'booking_id' => $bookingId,
                'form_id' => $form->id,
                'pax' => [
                    [
                        'pax_id' => $booking->transactId,
                        'pax_index' => 'repro-removal-adult-1',
                        'person_ordinal' => 0,
                        'added' => [],
                        'updated' => [],
                        'deleted' => [['db_id' => $existingField->id, 'field_id' => $photoField->id]],
                    ],
                ],
                'financial' => [
                    'action' => 'refund',
                    'net_amount' => 15.00,
                    'total_charge' => 0,
                    'total_refund' => 15.00,
                    'to_charge' => [],
                    'to_refund' => [
                        [
                            'db_id' => $existingField->id,
                            'field_id' => $photoField->id,
                            'label' => 'Photography Package',
                            'refund_amount' => 0,
                            'credit_amount' => 15.00,
                            'reason' => 'removed',
                        ],
                    ],
                    'payment_method' => 'Pay Later',
                    'refund_type' => 'dont_refund',
                ],
            ]);

            $this->assertSoftDeleted('tour_booking_addon_fields', ['id' => $existingField->id]);

            $historyRow = DB::table('tour_booking_history')
                ->where('tour_booking_id', $bookingId)
                ->where('operation', 'Refund')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($historyRow, 'A history entry must exist for the removal');

            $details = json_decode($historyRow->details, true);
            $this->assertEquals(15.00, $details['addons'][0]['price'], 'The real add-on price must show, not $0.00');
            $this->assertEquals(
                '',
                $details['payment_type'],
                'Pay Later is not a real payment method — must be blank, not "Pay Later"'
            );
        } finally {
            TourBookingAddonField::withTrashed()->where('tour_booking_id', $bookingId)->forceDelete();
            TourBookingAddonTransaction::where('tour_booking_id', $bookingId)->forceDelete();
            DB::table('addon_fields')->where('id', $photoField->id)->delete();
            DB::table('addon_forms')->where('id', $form->id)->delete();
            $booking->cleanup();
        }
    }
}
