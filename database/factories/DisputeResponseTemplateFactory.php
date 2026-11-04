<?php

namespace Database\Factories;

use App\Models\DisputeResponseTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeResponseTemplateFactory extends Factory
{
    protected $model = DisputeResponseTemplate::class;

    public function definition(): array
    {
        $reasons = [
            'fraudulent',
            'subscription_canceled',
            'product_unacceptable',
            'credit_not_processed',
            'duplicate',
            'product_not_received',
            'unrecognized',
            'general'
        ];

        $reason = $this->faker->randomElement($reasons);

        return [
            'name' => $this->faker->words(3, true) . ' Template',
            'dispute_reason' => $reason,
            'template_content' => $this->generateTemplateContent($reason),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'created_by' => $this->faker->numberBetween(1, 100),
            'created_by_user_type' => $this->faker->randomElement(['Admin', 'Tour Operator']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function fraudulent(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispute_reason' => 'fraudulent',
            'name' => 'Fraudulent Dispute Response Template',
            'template_content' => $this->generateTemplateContent('fraudulent'),
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispute_reason' => 'duplicate',
            'name' => 'Duplicate Charge Response Template',
            'template_content' => $this->generateTemplateContent('duplicate'),
        ]);
    }

    public function unrecognized(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispute_reason' => 'unrecognized',
            'name' => 'Unrecognized Charge Response Template',
            'template_content' => $this->generateTemplateContent('unrecognized'),
        ]);
    }

    private function generateTemplateContent(string $reason): string
    {
        $templates = [
            'fraudulent' => "Dear {{customer_name}},

We have received a dispute regarding your booking {{booking_reference}} for {{tour_name}} on {{tour_date}}.

We have comprehensive records showing that this transaction was legitimate:
- Booking confirmation was sent to {{customer_email}}
- Service was provided as scheduled
- Customer participated in the tour

Please find attached:
- Booking confirmation
- Customer communication records
- Service delivery proof

We believe this dispute should be resolved in our favor.

Best regards,
{{company_name}}",

            'duplicate' => "Dear {{customer_name}},

Regarding the duplicate charge dispute for booking {{booking_reference}}:

Our records show this was a single, legitimate transaction for {{tour_name}} on {{tour_date}}. The charge of {{booking_amount}} was processed once on {{payment_date}}.

Attached documentation includes:
- Single transaction record
- Booking confirmation
- Payment receipt

This appears to be a misunderstanding. We request this dispute be resolved in our favor.

Best regards,
{{company_name}}",

            'unrecognized' => "Dear {{customer_name}},

We understand you may not recognize the charge for booking {{booking_reference}}.

This charge was for {{tour_name}} booked for {{tour_date}} at {{tour_time}}. The booking was made using the email {{customer_email}}.

Documentation provided:
- Booking confirmation sent to customer
- Tour participation records
- Payment authorization

We believe this will help clarify the legitimate nature of this charge.

Best regards,
{{company_name}}",

            'default' => "Dear {{customer_name}},

We have received your dispute regarding booking {{booking_reference}} for {{tour_name}}.

We have reviewed our records and believe this transaction was legitimate. Please find our supporting documentation attached.

If you have any questions, please contact us at {{company_email}}.

Best regards,
{{company_name}}"
        ];

        return $templates[$reason] ?? $templates['default'];
    }
}