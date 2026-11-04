<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'tour_booking_id' => TourBooking::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'fee_amount' => $this->faker->optional()->randomFloat(2, 1, 50),
            'transaction_number' => 'TXN' . $this->faker->unique()->numberBetween(100000, 999999),
            'application_fee_id' => $this->faker->optional()->regexify('fee_[a-zA-Z0-9]{24}'),
            'via' => $this->faker->randomElement(['Stripe', 'Cash', 'Check', 'Bank Transfer']),
            'payment_type' => $this->faker->numberBetween(1, 4),
            'response_init' => json_encode([
                'payment_intent_id' => 'pi_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
                'status' => 'requires_payment_method'
            ]),
            'response_end' => json_encode([
                'payment_intent_id' => 'pi_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
                'status' => 'succeeded'
            ]),
            'response_end_datetime' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'status' => $this->faker->randomElement(['Initialized', 'Completed', 'Failed', 'Refund']),
            'is_reallocated' => $this->faker->boolean(10), // 10% chance
            'visitor' => $this->faker->optional()->ipv4,
            'request_from' => $this->faker->optional()->randomElement(['web', 'mobile', 'api']),
            'created_by' => 1,
            'created_by_user_type' => $this->faker->randomElement(['Admin', 'Tour Operator', 'Customer', 'Affiliate']),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
            'response_end_datetime' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Failed',
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Refund',
            'amount' => -abs($this->faker->randomFloat(2, 10, 1000)), // Negative for refund
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'via' => 'Stripe',
            'payment_type' => 1,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'via' => 'Cash',
            'payment_type' => 2,
        ]);
    }
}