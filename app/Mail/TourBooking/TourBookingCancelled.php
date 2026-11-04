<?php

namespace App\Mail\TourBooking;

use App\Jobs\TimeSlot\IdentifyBookingPackageRelation;
use App\Models\EmailTemplate;
use App\Models\TourBooking;
use App\Models\TourPackageEmailTemplate;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Traits\TemplateModification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TourBookingCancelled extends Mailable implements ShouldQueue
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
	 * Should attach pdf or not.
	 *
	 * @var bool
	 */
	public $attach_pdf;

	/**
	 * Create a new message instance.
	 *
	 * @param  array  $booking
	 * @param  string  $template_alias
	 * @param  object  $attach_pdf
	 */
	public function __construct($booking = [], $template = null, $attach_pdf = false)
	{
		$emailTemplate = EmailTemplate::alias($template)->first();
		$tourPackageEmailTemplate = TourPackageEmailTemplate::where('tour_package_id', $booking['tourPackageId'])
			->alias($template)
			->first();

		$this->booking = $booking;
		$this->template = $tourPackageEmailTemplate ?? $emailTemplate;
		$this->attach_pdf = $attach_pdf;
	}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
		// Start - Update relational slot status
		$tourSlotRepository = new TourSlotRepository();
		$tourBooking = TourBooking::findOrFail($this->booking['bookingId']);
		dispatch(new IdentifyBookingPackageRelation($tourBooking, $tourSlotRepository));
		// End - Update relational slot status

		if (isset($this->booking['custEmail']) && !empty($this->booking['custEmail'])) {
			if (isset($this->booking['replyTo']))
				$this->replyTo($this->booking['replyTo']['email'], $this->booking['replyTo']['name']);

			$this->from(
				resolveTourPackageFromEmail($this->booking['tourPackageId'] ?? null, $this->booking['custEmail'] ?? null, $this->booking['packageFromEmail']),
				$this->booking['packageFromName']
			)
				->to($this->booking['custEmail'], $this->booking['custName'])
				->subject($this->template->subject)
				->view('partials.email.body', ['body' => $this->getBody($this->template->body)]);

			if ($this->booking['packageEmail']) {
				$packageEmail = explode(',', $this->booking['packageEmail']);
				$this->bcc($packageEmail, $this->booking['packageFromName']);
			}

			$this->withSymfonyMessage(function ($message) {
				$message->getHeaders()->addTextHeader(
					'X-Model-ID',
					isset($this->booking['bookingId']) ? $this->booking['bookingId'] : 0
				);
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

	/**
	 * Get the array tags of the notification.
	 *
	 * @return array
	 */
	public function getTags(): array
	{
		return [
			'{tour_operator}',
            '{tour_operator_logo}',
            '{tour_operator_phone}',
            '{tour_operator_email}',
            '{tour_operator_website}',
            '{url_booking_info}',
            '{receiver_name}',
            '{customer_phone}',
            '{customer_email}',
            '{booking_id}',
            '{tour_package_photo}',
            '{tour_package}',
            '{tour_date}',
            '{tour_date_time}',
            '{number_of_tourist}',
            '{tour_list_with_seat}',
            '{deleted_for}',
            '{deleted_people}',
            '{url_liability_waiver}',
            '{important_notes}',
            '{card_number}',
            '{cancellation_policy}',
			'{refunded_amount}',
            '{map}',
			'{tour_package_image}'
		];
	}

	/**
	 * Get the array tag values of the notification.
	 *
	 * @return array
	 */
	public function getTagsReplacement(): array
	{
		return [
            $this->booking['tourOperator'] ?? null,
            $this->booking['tourOpLogo'] ?? null,
            $this->booking['tourOpPhone'] ?? null,
            $this->booking['tourOpEmail'] ?? null,
            $this->booking['tourOpWebsite'] ?? null,
            $this->booking['urlBookingInfo'] ?? null,
            $this->booking['custName'] ?? null,
            $this->booking['custPhone'] ?? null,
            $this->booking['custEmail'] ?? null,
            bookingPrefix($this->booking['bookingId']),
            $this->booking['tourPkgPhoto'] ?? null,
            $this->booking['tourPackage'] ?? null,
            $this->booking['tourDate'] ?? null,
            $this->booking['tourDateTime'] ?? null,
            $this->booking['numberoftourist'] ?? null,
            $this->convertArrayToString('tourListwithSeat'),
            $this->booking['deletedFor'] ?? null,
            $this->booking['deletedPeople'] ?? null,
            $this->booking['urlLiabilityWaiver'] ?? null,
            $this->booking['importantNotes'] ?? null,
            $this->booking['cardNumber'] ?? null,
            $this->booking['cancellationPolicy'] ?? null,
			isset($this->booking['refundedAmount']) ? moneyFormat($this->booking['refundedAmount']) : moneyFormat(0),
			url('/') . '/images/map.jpg',
			$this->booking['tourPkgImage'] ?? null
		];
	}

	/**
	 * Convert array to string.
	 *
	 * @param  string  $var
	 * @return string
	 */
	public function convertArrayToString($var)
	{
        $formattedString = '';

        switch ($var):
            case 'tourListwithSeat':
                $tourListwithSeat = json_decode(json_encode($this->booking['tourListwithSeat']), true);
                foreach ($tourListwithSeat as $value) {
                    $formattedString .= $value['rate_for'] . ': ' . ($value['infants'] > 0 ? $value['infants'] : $value['tourists']) . '<br>';
                }
                break;
        endswitch;

        return $formattedString;
	}
}
