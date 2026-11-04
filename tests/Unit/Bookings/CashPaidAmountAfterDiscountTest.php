<?php

namespace Tests\Unit\Bookings;

use App\Services\Booking\DiscountService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regression (real-world report): affiliate Pay Later booking ($450), Make Payment → Cash with a
 * $65 discount ($385 owed) and "Paid amount" $400 was recorded as a $400 "Split Payment" instead
 * of a $385 Full Payment with $15 change — the paid-amount check compared against the
 * undiscounted total. DiscountService::cashDueAfterDiscounts() is what it compares against now.
 */
class CashPaidAmountAfterDiscountTest extends TestCase
{
    private function request(array $data): Request
    {
        $request = new Request();
        $request->merge(array_merge(['payment_method' => 'Cash', 'split_value' => ''], $data));
        return $request;
    }

    private function booking(float $discount2Value = 0, float $discount2Percentage = 0): array
    {
        return [(object) [
            'tour_package_id'      => 0,
            'discount2_value'      => $discount2Value,
            'discount2_percentage' => $discount2Percentage,
        ]];
    }

    /** @test */
    public function fixed_discount_entered_in_the_modal_is_netted_out(): void
    {
        $due = DiscountService::cashDueAfterDiscounts(
            $this->request(['discount_type' => 'Fixed Money', 'discount_fixed_money' => 65]), 450.0, $this->booking()
        );
        $this->assertEqualsWithDelta(385.00, $due, 0.001);
        $this->assertFalse(400 < $due, '$400 paid on $385 owed is a full payment, not a split');
    }

    /** @test */
    public function percentage_discount_entered_in_the_modal_is_netted_out(): void
    {
        $due = DiscountService::cashDueAfterDiscounts(
            $this->request(['discount_type' => 'Percentage', 'discount_percentage' => 10]), 450.0, $this->booking()
        );
        $this->assertEqualsWithDelta(405.00, $due, 0.001);
    }

    /** @test */
    public function existing_pay_later_discount_is_netted_out(): void
    {
        $this->assertEqualsWithDelta(385.00, DiscountService::cashDueAfterDiscounts($this->request([]), 450.0, $this->booking(65)), 0.001);
        $this->assertEqualsWithDelta(405.00, DiscountService::cashDueAfterDiscounts($this->request([]), 450.0, $this->booking(45, 10)), 0.001);
    }

    /** @test */
    public function no_discount_leaves_the_total_unchanged(): void
    {
        $this->assertEqualsWithDelta(450.00, DiscountService::cashDueAfterDiscounts($this->request([]), 450.0, $this->booking()), 0.001);
    }
}
