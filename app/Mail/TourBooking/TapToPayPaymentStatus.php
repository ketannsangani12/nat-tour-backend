<?php

namespace App\Mail\TourBooking;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Jobs\TimeSlot\IdentifyBookingPackageRelation;
use App\Models\EmailTemplate;
use App\Models\Hotel;
use App\Models\TourBooking;
use App\Models\TourPackageEmailTemplate;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Traits\TemplateModification;
use App\Constants\Constants;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Milon\Barcode\Facades\DNS1DFacade as DNS1D;

class TapToPayPaymentStatus extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TemplateModification;

    public array $booking;
    public object|null $template;
    
    /**
     * Create a new message instance.
     */
    public function __construct(array $booking = [], string $templateAlias = '', bool $attachPdf = false)
    {
        $this->booking = $booking;
        $emailTemplate = EmailTemplate::alias($templateAlias)->first();
        $tourPackageTemplate = TourPackageEmailTemplate::where('tour_package_id', $booking['tourPackageId'] ?? null)
            ->alias($templateAlias)
            ->first();

        $this->template = $tourPackageTemplate ?? $emailTemplate;
    }

    /**
     * Build the email message.
     */
    public function build(): self
    {
        if (filled($this->booking['custEmail'] ?? null)) {
            
            $this->to(
                $this->booking['custEmail']
            )
            ->subject($this->template->subject ?? 'Tap To Pay Payment Status')
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
            '{cust_pay_amount}',
			'{guide_name}',
            '{payment_status}'
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
            isset($this->booking['custPayAmount']) ? moneyFormat($this->booking['custPayAmount']) : moneyFormat(0),
			$this->booking['guideName'] ?? null,
            $this->booking['paymentStatus'] ?? null
		];
    }


	
}