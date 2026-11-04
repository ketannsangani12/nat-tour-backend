<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\DisputeEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeEvidenceFactory extends Factory
{
    protected $model = DisputeEvidence::class;

    public function definition(): array
    {
        $evidenceTypes = [
            'receipt',
            'shipping_documentation',
            'duplicate_charge_documentation',
            'product_or_service_description',
            'customer_communication',
            'refund_policy',
            'cancellation_policy',
            'customer_signature',
            'uncategorized_file'
        ];

        $contentTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        return [
            'dispute_id' => Dispute::factory(),
            'evidence_type' => $this->faker->randomElement($evidenceTypes),
            'file_path' => 'evidence/' . $this->faker->uuid . '.pdf',
            'file_name' => $this->faker->word . '.' . $this->faker->fileExtension,
            'file_size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'content_type' => $this->faker->randomElement($contentTypes),
            'stripe_file_id' => $this->faker->optional(0.7)->regexify('file_[a-zA-Z0-9]{24}'),
            'submitted_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'created_by' => $this->faker->numberBetween(1, 100),
            'created_by_user_type' => $this->faker->randomElement(['Admin', 'Tour Operator', 'Customer', 'Affiliate']),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_file_id' => 'file_' . $this->faker->regexify('[a-zA-Z0-9]{24}'),
            'submitted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_file_id' => null,
            'submitted_at' => null,
        ]);
    }

    public function receipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'evidence_type' => 'receipt',
            'file_name' => 'receipt.pdf',
            'content_type' => 'application/pdf',
        ]);
    }

    public function customerCommunication(): static
    {
        return $this->state(fn (array $attributes) => [
            'evidence_type' => 'customer_communication',
            'file_name' => 'customer_email.pdf',
            'content_type' => 'application/pdf',
        ]);
    }
}