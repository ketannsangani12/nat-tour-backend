<?php

namespace App\Mail\TourBooking;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Jobs\TimeSlot\IdentifyBookingPackageRelation;
use App\Models\EmailTemplate;
use App\Traits\TemplateModification;
class TipPaymentLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TemplateModification;

    public array $booking;
    public object|null $template;
    public bool $attachPdf;

    /**
     * Create a new message instance.
     */
    public function __construct(array $booking = [], string $templateAlias = '', bool $attachPdf = false)
    {
        $this->booking = $booking;
        $this->attachPdf = $attachPdf;

        $emailTemplate = EmailTemplate::alias($templateAlias)->first();
        $this->template = $emailTemplate;
    }

    /**
     * Build the email message.
     */
    public function build(): self
    {
        if (filled($this->booking['custEmail'] ?? null)) {
            if (!empty($this->booking['replyTo'])) {
                $this->replyTo(
                    $this->booking['replyTo']['email'],
                    $this->booking['replyTo']['name']
                );
            }

            $this->from(
                $this->booking['tourOpEmail'],
                $this->booking['tourOperator']
            )
                ->to(
                    $this->booking['custEmail']
                )
                ->subject($this->template->subject ?? 'Tip Payment Link')
                ->view('partials.email.body', [
                    'body' => $this->getBody($this->template->body ?? ''),
                ]);

            $this->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('X-Model-TP', $this->booking['emailType'] ?? 0);
                $message->getHeaders()->addTextHeader('X-Model-OB', $this->booking['operationBy'] ?? 1);
                $message->getHeaders()->addTextHeader('X-Model-UT', $this->booking['operationByUserType'] ?? 'Admin');
                $message->getHeaders()->addTextHeader('X-Model-TID', $this->booking['tenantId'] ?? '');
            });
        }

        return $this;
    }

    /**
     * Return array of supported tags.
     */
    public function getTags(): array
    {
        return [
            '{tour_operator}',
            '{tour_operator_logo}',
            '{tour_operator_phone}',
            '{tour_operator_email}',
            '{tour_operator_website}',
            '{payment_link_url}'
        ];
    }

    /**
     * Return array of tag replacements.
     */
    public function getTagsReplacement(): array
    {
        return [
            $this->booking['tourOperator'] ?? null,
            $this->booking['tourOpLogo'] ?? null,
            $this->booking['tourOpPhone'] ?? null,
            $this->booking['tourOpEmail'] ?? null,
            $this->booking['tourOpWebsite'] ?? null,
            $this->booking['paymentLinkUrlInfo'] ?? ''
        ];
    }
}
