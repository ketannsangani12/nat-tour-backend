<?php

namespace App\Mail\TourBooking;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Common extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data;
    public $subject;
    public $body;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $subject, $body)
    {
        $this->data = $data;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if (isset($this->data['custEmail']) && !empty($this->data['custEmail'])) {
            if (isset($this->data['replyTo']))
                $this->replyTo($this->data['replyTo']['email'], $this->data['replyTo']['name']);

            $this->from(
                resolveTourPackageFromEmail($this->data['tourPackageId'] ?? null, $this->data['custEmail'] ?? null, $this->data['packageFromEmail']),
                $this->data['packageFromName']
            )
                ->to($this->data['custEmail'], $this->data['custName'])
                ->subject($this->subject)
                ->view('partials.email.body', ['body' => $this->body]);

            if ($this->data['packageEmail']) {
                $packageEmail = explode(',', $this->booking['packageEmail']);
                $this->bcc($packageEmail, $this->booking['packageFromName']);
            }

            $this->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader(
                    'X-Model-ID',
                    isset($this->data['bookingId']) ? $this->data['bookingId'] : 0
                );
                $message->getHeaders()->addTextHeader(
                    'X-Model-TP',
                    isset($this->data['emailType']) ? $this->data['emailType'] : 0
                );
                $message->getHeaders()->addTextHeader(
                    'X-Model-TID',
                    isset($this->data['tenantId']) ? $this->data['tenantId'] : ''
                );
            });
        }

        return $this;
    }
}
