<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\TourBooking;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    public function definition(): array
    {
        return [
            'stripe_dispute_id' => 'dp_' . $this->faker->unique()->regexify('[a-zA-Z0-9]{24}'),
            'booking_id' => TourBooking::factory(),
            'transaction_id' => Transaction::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD']),
            'reason' => $this->faker->randomElement([
                'fraudulent',
                'subscription_canceled',
                'product_unacceptable',
                'credit_not_processed',
                'duplicate',
                'product_not_received',
                'unrecognized',
                'general'
            ]),
            'status' => $this->faker->randomElement([
                'warning_needs_response',
                'under_review',
                'charge_refunded',
                'lost',
                'won'
            ]),
            'evidence_due_by' => $this->faker->optional(0.8)->dateTimeBetween('now', '+30 days'),
        ];
    }

    public function needsResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'warning_needs_response',
            'evidence_due_by' => $this->faker->dateTimeBetween('now', '+7 days'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'warning_needs_response',
            'evidence_due_by' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_review',
        ]);
    }

    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'won',
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'lost',
        ]);
    }

    public function fraudulent(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => 'fraudulent',
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => 'duplicate',
        ]);
    }
}