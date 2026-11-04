<?php

namespace Tests\Unit\Bookings;

use App\Models\AddonField;
use App\Models\AddonForm;
use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLink;
use App\Services\Booking\PaymentLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * Regression (real-world report): a Pay-Later (never paid) booking with 4 pax and add-ons on all
 * of them gets a single combined "Payment Link" sent for the whole booking. Splitting it 2+2
 * regenerates that link for BOTH resulting bookings — but the new booking's regenerated amount
 * silently dropped its own pax's add-ons (worth $20 + fee = $21.96 in the reported case), because
 * tour_bookings_master.total never includes pay-later add-ons (they're always added on separately,
 * see handleMakePaymentSection()'s Branch 2) and
 * PaymentLinkService::splitBookingPaymentLink()/splitNewBookingPaymentLink() never called
 * BookingService::fetchPayLaterAddonsWithPrice() the way that Branch 2 does. Since
 * splitOldBookingPaymentLink() derives the OLD booking's share by subtracting the (deficient) new
 * amount from the original combined total, the missing add-on amount silently stayed misattributed
 * to the OLD booking instead — old too high, new too low, by the exact same amount.
 */
class SplitBookingPaymentLinkAddonsTest extends TestCase
{
    protected static array $builders = [];
    protected static array $addonForms = [];
    protected static array $addonFields = [];

    private function makeAddonField(int $packageId): AddonField
    {
        $form = AddonForm::create([
            'tour_package_id' => $packageId,
            'name'            => 'Split Payment Link Addons Test Form ' . uniqid(),
            'status'          => 'Active',
        ]);
        static::$addonForms[] = $form;

        $field = AddonField::create([
            'form_id' => $form->id, 'label' => 'Photography Package', 'type' => 'checkbox',
            'unit_type' => 'price_per_pax', 'additional_fee' => true, 'price' => 15.00,
            'status' => 'Active', 'visibility' => 'both', 'sort_order' => 0,
        ]);
        static::$addonFields[] = $field;

        return $field;
    }

    /** @test */
    public function new_booking_link_includes_its_own_pay_later_addons_and_old_booking_gets_the_correct_remainder(): void
    {
        Mail::fake();

        $natFeePct = 9.82;

        $oldBooking = (new CreateABooking())
            ->withTourists(2)->withRate(50.00)->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => $natFeePct])
            ->create();
        static::$builders[] = $oldBooking;
        DB::table('tour_bookings_master')->where('id', $oldBooking->bookingId)->update(['group_id' => $oldBooking->bookingId]);

        $newBooking = (new CreateABooking())
            ->usePackage($oldBooking->packageId)->useRate($oldBooking->rateId)
            ->useSlotTime($oldBooking->slotTimeId)->useCustomer($oldBooking->customerId)
            ->withTourists(2)->withoutTransaction()
            ->withTransactOverrides(['nat_fees_percentage' => $natFeePct])
            ->create();
        static::$builders[] = $newBooking;
        DB::table('tour_bookings_master')->where('id', $newBooking->bookingId)->update(['group_id' => $newBooking->bookingId]);

        $addonField = $this->makeAddonField($oldBooking->packageId);

        // Each side has its own pax with a $15 add-on — splitBookingPaxAddons() (already run by the
        // time splitBookingPaymentLink() executes) has already re-pointed the new booking's field
        // onto it, so it's created directly there.
        $oldAddon = TourBookingAddonField::create([
            'tour_booking_id' => $oldBooking->bookingId, 'form_id' => $addonField->form_id, 'field_id' => $addonField->id,
            'pax_id' => $oldBooking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'link-addons-old-0', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);
        $newAddon = TourBookingAddonField::create([
            'tour_booking_id' => $newBooking->bookingId, 'form_id' => $addonField->form_id, 'field_id' => $addonField->id,
            'pax_id' => $newBooking->transactId, 'field_label' => 'Photography Package',
            'pax_index' => 'link-addons-new-0', 'person_ordinal' => 0,
            'unit_type' => 'price_per_pax', 'unit' => 1,
        ]);

        // The ORIGINAL combined link, covering all 4 pax's tickets + both sides' add-ons (mirrors
        // handleMakePaymentSection() Branch 2's full formula): (100 + 9.82 + 16.47) * 2 = 252.58.
        $originalLink = TourBookingPaymentLink::create([
            'order_id' => $oldBooking->bookingId, 'amount' => 252.58, 'section' => null,
            'reason_of_payment' => 'Make Payment', 'email' => 'test@example.com', 'phone_number' => '5555551234',
            'token' => Str::random(64), 'status' => 'Unpaid', 'override_status' => 'Yes',
            'expired_at' => now()->addDays(30),
            'created_by' => 1, 'created_by_user_type' => 'Admin',
        ]);

        $info = (object) [
            'external_booking_id' => null, 'tour_operator_id' => 1, 'tour_package_id' => $oldBooking->packageId,
            'tour_operator' => 'Test Operator', 'tour_op_phone' => '', 'tour_op_email' => '', 'tour_op_website' => '',
            'package_name' => 'Test Package', 'important_notes' => null, 'things_to_bring' => null,
            'cancellation_policy' => '', 'packageEmail' => '', 'packageFromName' => '', 'packageFromEmail' => '',
            'packageApiKey' => '', 'tenant_id' => tenant()->id, 'tour_op_logo' => null,
        ];

        $bookingTransact = DB::table('tour_bookings_transact')->where('tour_booking_id', $newBooking->bookingId)->first();
        $newTourBookingModel = TourBooking::find($newBooking->bookingId);
        $oldTourBookingModel = TourBooking::find($oldBooking->bookingId);

        try {
            PaymentLinkService::splitBookingPaymentLink($newTourBookingModel, $oldTourBookingModel, $originalLink, $info, $bookingTransact);

            $newLink = TourBookingPaymentLink::where('order_id', $newBooking->bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($newLink);
            $this->assertEqualsWithDelta(126.29, (float) $newLink->amount, 0.01, 'New booking link must include its own $15 add-on ($15 + fee), not just the ticket');

            $oldLink = TourBookingPaymentLink::where('order_id', $oldBooking->bookingId)->where('status', 'Unpaid')->latest('id')->first();
            $this->assertNotNull($oldLink);
            $this->assertEqualsWithDelta(126.29, (float) $oldLink->amount, 0.01, 'Old booking link must keep exactly its own share, not the new booking\'s missing add-on too');

            $this->assertEqualsWithDelta((float) $originalLink->amount, (float) $newLink->amount + (float) $oldLink->amount, 0.01, 'Nothing should be gained or lost overall');

            DB::table('tour_booking_payment_links')->where('id', $newLink->id)->delete();
            DB::table('tour_booking_payment_links')->where('id', $oldLink->id)->delete();
        } finally {
            DB::table('tour_booking_payment_links')->where('id', $originalLink->id)->delete();
            TourBookingAddonField::withTrashed()->whereIn('id', [$oldAddon->id, $newAddon->id])->forceDelete();
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
