<?php

namespace Database\Seeders;

use App\Models\DisputeResponseTemplate;
use Illuminate\Database\Seeder;

class DisputeResponseTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Fraudulent Dispute Response',
                'dispute_reason' => 'fraudulent',
                'template_content' => $this->getFraudulentTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
            [
                'name' => 'Duplicate Charge Response',
                'dispute_reason' => 'duplicate',
                'template_content' => $this->getDuplicateTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
            [
                'name' => 'Unrecognized Charge Response',
                'dispute_reason' => 'unrecognized',
                'template_content' => $this->getUnrecognizedTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
            [
                'name' => 'Product Not Received Response',
                'dispute_reason' => 'product_not_received',
                'template_content' => $this->getProductNotReceivedTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
            [
                'name' => 'Product Unacceptable Response',
                'dispute_reason' => 'product_unacceptable',
                'template_content' => $this->getProductUnacceptableTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
            [
                'name' => 'General Dispute Response',
                'dispute_reason' => 'general',
                'template_content' => $this->getGeneralTemplate(),
                'is_active' => true,
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
            ],
        ];

        foreach ($templates as $template) {
            DisputeResponseTemplate::updateOrCreate(
                [
                    'dispute_reason' => $template['dispute_reason'],
                    'name' => $template['name']
                ],
                $template
            );
        }
    }

    private function getFraudulentTemplate(): string
    {
        return "Dear {{customer_name}},

We have received a dispute regarding your booking {{booking_reference}} for {{tour_name}} scheduled on {{tour_date}} at {{tour_time}}.

We have comprehensive records showing that this transaction was legitimate and authorized:

• Booking confirmation was sent to {{customer_email}} on {{payment_date}}
• Customer provided personal information and contact details during booking
• Service was provided as scheduled on {{tour_date}}
• No previous complaints or cancellation requests were received

Supporting Documentation:
• Booking confirmation email
• Customer communication records
• Service delivery confirmation
• Payment authorization details

We believe this dispute should be resolved in our favor as we have provided the service as agreed and have documentation supporting the legitimacy of this transaction.

If you have any questions or need additional information, please contact us at {{company_email}} or {{company_phone}}.

Best regards,
{{company_name}}
Customer Service Team";
    }

    private function getDuplicateTemplate(): string
    {
        return "Dear {{customer_name}},

Regarding the duplicate charge dispute for booking {{booking_reference}}:

Our records show this was a single, legitimate transaction for {{tour_name}} on {{tour_date}}. The charge of {{booking_amount}} was processed only once on {{payment_date}} with transaction ID {{transaction_id}}.

Transaction Details:
• Single payment processed on {{payment_date}}
• Booking reference: {{booking_reference}}
• Service provided: {{tour_name}}
• Amount: {{booking_amount}}

Attached documentation includes:
• Single transaction record from our payment processor
• Booking confirmation showing one reservation
• Payment receipt with unique transaction ID

This appears to be a misunderstanding. We request this dispute be resolved in our favor as our records clearly show only one transaction was processed for this booking.

If you believe you see multiple charges on your statement, please check if there are separate charges for different bookings or services.

Best regards,
{{company_name}}
Customer Service Team";
    }

    private function getUnrecognizedTemplate(): string
    {
        return "Dear {{customer_name}},

We understand you may not recognize the charge for booking {{booking_reference}}.

This charge was for {{tour_name}} booked for {{tour_date}} at {{tour_time}}. The booking was made using the email address {{customer_email}} and the charge amount was {{booking_amount}}.

Booking Details:
• Tour: {{tour_name}}
• Date: {{tour_date}}
• Time: {{tour_time}}
• Amount: {{booking_amount}}
• Booking Reference: {{booking_reference}}

Documentation provided:
• Booking confirmation sent to {{customer_email}}
• Tour participation records
• Payment authorization from {{payment_date}}
• Customer contact information used during booking

We believe this documentation will help clarify the legitimate nature of this charge. The service was provided as booked and paid for.

If you need any clarification about this booking, please contact us at {{company_email}}.

Best regards,
{{company_name}}
Customer Service Team";
    }

    private function getProductNotReceivedTemplate(): string
    {
        return "Dear {{customer_name}},

We have received your dispute claiming that you did not receive the service for booking {{booking_reference}}.

Our records show that the tour service {{tour_name}} was provided on {{tour_date}} at {{tour_time}} as scheduled. This is a tour service that is consumed at the time of delivery, not a physical product that is shipped.

Service Delivery Details:
• Tour: {{tour_name}}
• Date: {{tour_date}}
• Time: {{tour_time}}
• Location: [Tour meeting point/location]
• Duration: [Tour duration]

Supporting Documentation:
• Booking confirmation with tour details
• Tour schedule and itinerary
• Service delivery confirmation
• Tour operator records

For tour services, the product is the experience provided on the scheduled date and time. Our records confirm that this service was available and provided as booked.

If there were any issues with the service quality or if you were unable to attend, we encourage you to contact us directly at {{company_email}} to discuss resolution options.

Best regards,
{{company_name}}
Customer Service Team";
    }

    private function getProductUnacceptableTemplate(): string
    {
        return "Dear {{customer_name}},

We have received your dispute regarding the quality of service for booking {{booking_reference}} - {{tour_name}} on {{tour_date}}.

We take customer satisfaction seriously and regret that your experience did not meet your expectations. However, we believe the service was provided as described in our booking terms and conditions.

Service Details:
• Tour: {{tour_name}}
• Date: {{tour_date}}
• Time: {{tour_time}}
• Service Description: [As advertised]

Our Standard Practices:
• All tours are conducted according to published itineraries
• Professional guides provide the service as described
• Safety protocols are followed at all times
• Weather and operational conditions are communicated in advance

We encourage customers to contact us directly with any concerns so we can address them promptly. Our customer service team is available at {{company_email}} and {{company_phone}}.

If you contacted us about this issue, we would be happy to work with you on a resolution. However, as the service was provided as described, we believe this dispute should be resolved in our favor.

Best regards,
{{company_name}}
Customer Service Team";
    }

    private function getGeneralTemplate(): string
    {
        return "Dear {{customer_name}},

We have received your dispute regarding booking {{booking_reference}} for {{tour_name}}.

We have reviewed our records and believe this transaction was legitimate and the service was provided as agreed. Please find our supporting documentation attached.

Booking Details:
• Reference: {{booking_reference}}
• Service: {{tour_name}}
• Date: {{tour_date}}
• Amount: {{booking_amount}}
• Payment Date: {{payment_date}}

We maintain comprehensive records of all bookings and transactions. Our documentation supports the legitimacy of this charge and the delivery of the service as booked.

If you have any questions or need additional information about this booking, please contact us at {{company_email}} or {{company_phone}}. We are committed to resolving any concerns you may have.

Best regards,
{{company_name}}
Customer Service Team

---
This response was generated on {{current_date}}";
    }
}