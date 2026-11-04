<?php

namespace Database\Factories;

use App\Models\AutoPayoutRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutoPayoutRuleFactory extends Factory
{
    protected $model = AutoPayoutRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Payout Rule',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'execution_time' => '09:00:00',
            'minimum_amount' => 50.00,
            'enabled' => true,
            'requires_approval' => true,
            'last_executed_at' => null,
            'next_execution_at' => now()->addWeek(),
        ];
    }

    public function daily(): static
    {
        return $this->state(fn () => [
            'frequency' => 'daily',
            'day_of_week' => null,
            'day_of_month' => null,
        ]);
    }

    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state(fn () => [
            'frequency' => 'monthly',
            'day_of_week' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'enabled' => false,
        ]);
    }

    public function noApprovalRequired(): static
    {
        return $this->state(fn () => [
            'requires_approval' => false,
        ]);
    }

    public function duePast(): static
    {
        return $this->state(fn () => [
            'next_execution_at' => now()->subHour(),
        ]);
    }
}
