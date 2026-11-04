<?php

namespace Database\Factories;

use App\Models\TourBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourBookingFactory extends Factory
{
    protected $model = TourBooking::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'phone_number' => $this->faker->phoneNumber,
            'tourists' => $this->faker->numberBetween(1, 10),
            'infants' => $this->faker->numberBetween(0, 3),
            'service_commission' => $this->faker->randomFloat(2, 0, 50),
            'comments' => $this->faker->sentence,
            'note' => $this->faker->optional(0.7, '')->sentence,
            'reference_booking' => 'BK' . $this->faker->unique()->numberBetween(100000, 999999),
            'mask_required' => $this->faker->randomElement(['Yes', 'No']),
            'discount2_value' => $this->faker->randomFloat(2, 0, 100),
            'discount2_percentage' => $this->faker->randomFloat(2, 0, 50),
            'total' => $this->faker->randomFloat(2, 50, 500),
            'booking_total' => $this->faker->randomFloat(2, 50, 500),
            'liability_waiver_signing' => json_encode([
                'sign_date_time' => now()->toISOString(),
                'tourists' => []
            ]),
            'status' => $this->faker->randomElement(['Booked', 'Completed', 'Cancelled']),
            'created_by' => 1,
            'created_by_user_type' => 'Customer',
            'updated_by' => 1,
            'updated_by_user_type' => 'Tour Operator',
        ];
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Booked',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }
}