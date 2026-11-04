<?php

namespace Database\Seeders;

use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeTimeline;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DisputeSeeder extends Seeder
{
    public function run(): void
    {
        tenancy()->initialize('dixies');

        $fixtures = [
            [
                'booking_id'     => 63252,
                'transaction_id' => 16096,
                'amount'         => 161.00,
                'reason'         => 'fraudulent',
                'status'         => 'warning_needs_response',
                'due_days'       => 5,
                'evidence'       => ['receipt', 'customer_communication'],
                'events'         => ['dispute_created', 'webhook_received'],
            ],
            [
                'booking_id'     => 63253,
                'transaction_id' => 16097,
                'amount'         => 322.00,
                'reason'         => 'duplicate',
                'status'         => 'warning_needs_response',
                'due_days'       => 2,
                'evidence'       => ['duplicate_charge_documentation'],
                'events'         => ['dispute_created', 'evidence_uploaded'],
            ],
            [
                'booking_id'     => 63269,
                'transaction_id' => 16100,
                'amount'         => 322.00,
                'reason'         => 'unrecognized',
                'status'         => 'under_review',
                'due_days'       => null,
                'evidence'       => ['customer_communication', 'product_or_service_description'],
                'events'         => ['dispute_created', 'evidence_submitted', 'status_changed'],
            ],
            [
                'booking_id'     => 63271,
                'transaction_id' => 16110,
                'amount'         => 161.00,
                'reason'         => 'product_not_received',
                'status'         => 'won',
                'due_days'       => null,
                'evidence'       => ['receipt', 'customer_communication', 'product_or_service_description'],
                'events'         => ['dispute_created', 'evidence_submitted', 'status_changed'],
            ],
            [
                'booking_id'     => 63272,
                'transaction_id' => 16111,
                'amount'         => 80.50,
                'reason'         => 'product_unacceptable',
                'status'         => 'lost',
                'due_days'       => null,
                'evidence'       => ['customer_communication'],
                'events'         => ['dispute_created', 'status_changed'],
            ],
        ];

        foreach ($fixtures as $data) {
            // Skip if dispute already exists for this booking
            if (Dispute::where('booking_id', $data['booking_id'])->exists()) {
                continue;
            }

            $dispute = Dispute::factory()->create([
                'booking_id'     => $data['booking_id'],
                'transaction_id' => $data['transaction_id'],
                'amount'         => $data['amount'],
                'currency'       => 'USD',
                'reason'         => $data['reason'],
                'status'         => $data['status'],
                'evidence_due_by' => $data['due_days']
                    ? Carbon::now()->addDays($data['due_days'])
                    : null,
            ]);

            foreach ($data['evidence'] as $evidenceType) {
                DisputeEvidence::factory()->submitted()->create([
                    'dispute_id'           => $dispute->id,
                    'evidence_type'        => $evidenceType,
                    'created_by'           => 1,
                    'created_by_user_type' => 'Admin',
                ]);
            }

            foreach ($data['events'] as $eventType) {
                DisputeTimeline::factory()->{$this->timelineState($eventType)}()->create([
                    'dispute_id' => $dispute->id,
                ]);
            }
        }

        $this->command->info('Dispute seed data created for tenant: dixies');
    }

    private function timelineState(string $eventType): string
    {
        return match ($eventType) {
            'dispute_created'  => 'disputeCreated',
            'evidence_submitted' => 'evidenceSubmitted',
            'status_changed'   => 'statusChanged',
            'webhook_received' => 'webhookReceived',
            default            => 'disputeCreated',
        };
    }
}
