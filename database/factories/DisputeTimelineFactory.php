<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\DisputeTimeline;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeTimelineFactory extends Factory
{
    protected $model = DisputeTimeline::class;

    public function definition(): array
    {
        $eventTypes = [
            'dispute_created',
            'dispute_updated',
            'dispute_closed',
            'evidence_submitted',
            'evidence_uploaded',
            'status_changed',
            'webhook_received',
            'notification_sent',
            'user_action',
            'system_action'
        ];

        return [
            'dispute_id' => Dispute::factory(),
            'event_type' => $this->faker->randomElement($eventTypes),
            'event_data' => [
                'description' => $this->faker->sentence,
                'additional_info' => $this->faker->optional()->word,
            ],
            'user_id' => $this->faker->optional(0.7)->numberBetween(1, 100),
            'user_type' => $this->faker->randomElement(['Admin', 'Tour Operator', 'Customer', 'Affiliate', 'System']),
            'ip_address' => $this->faker->optional(0.8)->ipv4,
            'user_agent' => $this->faker->optional(0.8)->userAgent,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function disputeCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'dispute_created',
            'event_data' => [
                'stripe_dispute_id' => 'dp_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
                'amount' => $this->faker->randomFloat(2, 10, 1000),
                'reason' => $this->faker->randomElement(['fraudulent', 'duplicate', 'unrecognized']),
                'status' => 'warning_needs_response',
            ],
            'user_type' => 'System',
        ]);
    }

    public function evidenceSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'evidence_submitted',
            'event_data' => [
                'evidence_count' => $this->faker->numberBetween(1, 5),
                'submission_id' => 'sub_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
            ],
            'user_type' => $this->faker->randomElement(['Admin', 'Tour Operator']),
        ]);
    }

    public function statusChanged(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => 'warning_needs_response',
                'new_status' => 'under_review',
                'changed_by' => 'stripe_webhook',
            ],
            'user_type' => 'System',
        ]);
    }

    public function webhookReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'webhook_received',
            'event_data' => [
                'webhook_type' => $this->faker->randomElement(['charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed']),
                'webhook_id' => 'evt_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
            ],
            'user_type' => 'System',
        ]);
    }
}