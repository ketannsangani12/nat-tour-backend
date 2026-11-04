<?php

namespace Tests\Unit\Bookings;

use App\Services\Booking\PaymentLinkService;
use Tests\TestCase;

/**
 * Regression (real-world report): editing a BOOKING-level (not per-pax) add-on — "Extra Water
 * Bottles" 5 → 4 units — whose amount was pending on a remaining_payment link left the link at
 * the old amount. Booking-level pending items key field_to_db by the plain field_id ("3") and
 * their lines carry no pax_index, but the old lookup built "{field_id}_{pax_index}" ("3_") and
 * never matched. The end-to-end repricing is covered by EditAddonRepricesPendingLinkItemTest;
 * this pins the key-resolution rule itself.
 */
class UpdateBookingLevelAddonDowngradeRependsPendingLinkTest extends TestCase
{
    /** @test */
    public function resolver_matches_booking_level_and_pax_level_field_to_db_keys(): void
    {
        $this->assertSame(11, PaymentLinkService::resolvePendingAddonLineFieldId(['field_id' => 3], ['3' => 11]));
        $this->assertSame(11, PaymentLinkService::resolvePendingAddonLineFieldId(['field_id' => 3, 'pax_index' => null], ['3' => 11]));
        $this->assertSame(22, PaymentLinkService::resolvePendingAddonLineFieldId(['field_id' => 3, 'pax_index' => 'p0'], ['3' => 11, '3_p0' => 22]));
        $this->assertNull(PaymentLinkService::resolvePendingAddonLineFieldId(['field_id' => 3, 'pax_index' => 'p1'], ['3' => 11, '3_p0' => 22]),
            'A pax line must never fall back to another pax / booking-level mapping');
        $this->assertSame(99, PaymentLinkService::resolvePendingAddonLineFieldId(['field_id' => 3, 'old_db_id' => 99], ['3' => 11]));
    }
}
