<?php

namespace Database\Factories;

use App\Models\PendingPayout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PendingPayoutFactory extends Factory
{
    protected $model = PendingPayout::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 100, 5000);
        $platformFee = round($totalAmount * 0.029 + 0.30, 2);
        $netAmount = round($totalAmount - $platformFee, 2);

        return [
            'payout_reference' => 'PP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'rule_id' => null,
            'stripe_account_id' => 'acct_' . Str::random(16),
            'total_amount' => $totalAmount,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount,
            'booking_count' => fake()->numberBetween(1, 20),
            'status' => 'pending_approval',
            'rule_snapshot' => [],
            'calculation_data' => [
                'booking_ids' => [],
                'booking_details' => [],
                'calculation_date' => now()->toDateTimeString(),
                'stripe_account_id' => 'acct_test',
            ],
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => 1,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => 1,
            'rejection_reason' => 'Test rejection reason',
        ]);
    }
}
