<?php

namespace Tests\Unit\Bookings;

use PHPUnit\Framework\TestCase;

class CardPaymentTest extends TestCase
{
    /**
     * A split payment with discount.
     * 
     * CMD: php artisan test --filter=test_split_payment_with_discount
     */
    public function test_split_payment_with_discount(): void
    {
        // define variables
        $tourists = 40; // number of tourists for single rate group
        $rate = 46.77; // rate per pax
        $tax = 3.23; // tax per pax
        $platformFeePercentage = 9.8; // NAT platform fee
        $stripeFeePercentage = 8.9282; // Stripe platform fee
        $discount = 400; // in fixed amount
        $splitAmount = 1300; // with fee, first payment

        $ticketCost = $tourists * ($rate + $tax); // total ticket cost

        $ticketCostAfterDiscount = $ticketCost - $discount; // total ticket cost after discount

        $platformFeeFirst = $splitAmount * $stripeFeePercentage / 100; // platform fee calculated for first split payment
        $ticketCostFirst = $splitAmount - $platformFeeFirst; // get ticket cost after removing platform fee from first split payment

        $ticketCostSecond = $ticketCostAfterDiscount - $ticketCostFirst; // get rest of ticket cost after deducting the first split ticket cost
        $platformFeeSecond = $ticketCostSecond * $platformFeePercentage / 100;

        $totalTicketCost = roundout($ticketCostFirst, 2) + roundout($ticketCostSecond, 2); // sum both ticket cost
        // dump(roundout($ticketCostFirst, 2));
        // dump(roundout($platformFeeFirst, 2));
        // dump(roundout($ticketCostSecond, 2));
        // dump(roundout($platformFeeSecond, 2));

        $this->assertEquals($ticketCostAfterDiscount, $totalTicketCost); // check original ticket cost and paid ticket cost equal or not
    }
}
