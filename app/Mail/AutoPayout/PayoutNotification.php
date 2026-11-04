<?php

namespace App\Mail\AutoPayout;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayoutNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $type;
    public array $payoutData;

    /**
     * @param string $type One of: pending_approval, executed, failed
     * @param array $payoutData
     */
    public function __construct(string $type, array $payoutData)
    {
        $this->type = $type;
        $this->payoutData = $payoutData;
    }

    public function build()
    {
        $subjects = [
            'pending_approval' => 'Payout Awaiting Approval - ' . $this->payoutData['payout_reference'],
            'executed' => 'Payout Executed Successfully - ' . $this->payoutData['payout_reference'],
            'failed' => 'Payout Failed - ' . $this->payoutData['payout_reference'],
        ];

        return $this
            ->subject($subjects[$this->type] ?? 'Payout Notification')
            ->view('mail.auto-payout.notification', [
                'type' => $this->type,
                'payoutData' => $this->payoutData,
            ]);
    }
}
