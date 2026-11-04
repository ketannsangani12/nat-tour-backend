<?php

namespace App\Mail\TourBooking;

use App\Models\EmailTemplate;
use App\Traits\TemplateModification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsLetter extends Mailable implements ShouldQueue
{
	use Queueable, SerializesModels, TemplateModification;

	/**
	 * The tour booking data.
	 *
	 * @var array
	 */
	public $booking;

	/**
	 * The email template.
	 *
	 * @var object
	 */
	public $template;

	/**
	 * Create a new message instance.
	 *
	 * @param  array  $booking
	 * @param  string  $template_alias
	 */
	public function __construct($booking = [], $template = null)
	{
		$emailTemplate = EmailTemplate::alias($template)->first();

		$this->booking = $booking;
		$this->template = $emailTemplate;
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build()
	{
		if (isset($this->booking['custEmail']) && !empty($this->booking['custEmail'])) {
			$this->from(
				resolveTourPackageFromEmail($this->booking['tourPackageId'] ?? null, $this->booking['custEmail'] ?? null, $this->booking['packageFromEmail']),
				$this->booking['packageFromName']
			)
				->to($this->booking['custEmail'], $this->booking['custName']);

			$this->subject($this->template->subject)
				->view('partials.email.body', ['body' => $this->getBody($this->template->body)]);

			$this->withSymfonyMessage(function ($message) {
				$message->getHeaders()->addTextHeader(
					'X-Model-TP',
					isset($this->booking['emailType']) ? $this->booking['emailType'] : 0
				);
				$message->getHeaders()->addTextHeader(
					'X-Model-TID',
					isset($this->booking['tenantId']) ? $this->booking['tenantId'] : ''
				);
			});
		}

		return $this;
	}
}
