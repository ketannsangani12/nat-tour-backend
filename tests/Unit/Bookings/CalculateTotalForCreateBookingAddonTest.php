<?php

namespace Tests\Unit\Bookings;

use App\Services\Booking\BookingService;
use Illuminate\Http\Request;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

class CalculateTotalForCreateBookingAddonTest extends TestCase
{
    /**
     * Regression for a real Stripe undercharge: an admin-created booking with the "Camera Rental
     * Type" add-on (a CHILD field, $40 DSLR option, id 2538 in dixies) nested under "Equipment
     * Rental Needed?" (a parent field with NO price of its own, id 2537). Stripe captured $43.93
     * less than the booking's own recorded total — exactly the child's $40.00 price + $3.93 fee.
     *
     * Root cause: calculateTotalForCreateBooking()'s addons_data loop gated the ENTIRE field group
     * on the PARENT's own field_price_enabled/option_price_enabled flags before ever calling
     * AddonBookingService::calculateFieldAmounts(). Since the parent itself is unpriced, that call
     * — and therefore calculateFieldAmounts()'s own correct recursion into $field['children'] —
     * never happened, silently dropping the priced child from the Stripe PaymentIntent amount even
     * though it's stored/charged correctly once the booking actually exists (booking creation's own
     * insertAddonFieldRecursive()/buildAddonTransaction() have no such gate and were never affected).
     *
     * Confirmed against real dixies data: booking #69822's "Camera Rental Type" (DSLR) transaction
     * row is exactly $40.00 price / $3.93 fee — matching the shape reconstructed below.
     */
    public function test_addons_data_includes_priced_child_under_unpriced_parent(): void
    {
        $booking = (new CreateABooking())->withPaymentMethod('Card')->withTourists(1)->create();

        try {
            $addonsData = json_encode([
                'form_id' => 2,
                'fields' => [
                    'addons' => [
                        [
                            'uid' => 'row-uid', // must be skipped, not passed to calculateFieldAmounts()
                            2537 => [
                                'field_id' => 2537,
                                'unit_type' => 'one_time',
                                'value' => 'yes',
                                'field_price_enabled' => 0,
                                'option_price_enabled' => 0,
                                'field_price' => 0,
                                'option_price' => 0,
                                'children' => [
                                    [
                                        2538 => [
                                            'field_id' => 2538,
                                            'unit_type' => 'one_time',
                                            'option_id' => 1312,
                                            'field_price_enabled' => 0,
                                            'option_price_enabled' => 1,
                                            'field_price' => 0,
                                            'option_price' => 40,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $request = new Request();
            $request->merge([
                'tourists' => [1],
                'rate_group' => [$booking->rateId],
                'permit_fee' => [0],
                'payment_method' => 'Card',
                'addons_data' => $addonsData,
            ]);

            $info = new \stdClass();
            $info->is_partner_package = 0;

            $result = BookingService::calculateTotalForCreateBooking($request, 2.9, 9.82, $info, '0');

            // stripeTotal/stripeFee must include the child's price/fee on top of the base tour
            // total/fee — before the fix, otherAddonTotal/otherAddonFee were always 0 here.
            $this->assertEqualsWithDelta($result['total'] + 43.93, $result['stripeTotal'], 0.001, 'Child add-on price+fee missing from Stripe charge amount');
            $this->assertEqualsWithDelta($result['serviceCommission'] + 3.93, $result['stripeFee'], 0.001, 'Child add-on fee missing from Stripe fee amount');
        } finally {
            $booking->cleanup();
        }
    }
}
