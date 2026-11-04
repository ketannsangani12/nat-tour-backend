<?php

namespace App\Notifications\TourBooking;

use App\Abstracts\Notification;
use App\Mail\TourBooking\Common;
use App\Models\EmailTemplate;
use App\Models\TourPackageEmailTemplate;

class TipForGuide extends Notification
{
	/**
	 * The tour booking data.
	 *
	 * @var array
	 */
	public $booking;

	/**
	 * The email template.
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Should attach pdf or not.
	 *
	 * @var bool
	 */
	public $attach_pdf;

	/**
	 * Create a notification instance.
	 *
	 * @param  array  $booking
	 * @param  string  $template_alias
	 * @param  object  $attach_pdf
	 */
	public function __construct($booking = [], $template_alias = null, $attach_pdf = false)
	{
		parent::__construct();

		$emailTemplate = EmailTemplate::alias($template_alias)->first();
		$tourPackageEmailTemplate = TourPackageEmailTemplate::where('tour_package_id', $booking['tourPackageId'])
			->alias($template_alias)
			->first();

		$this->booking = $booking;
		$this->template = $tourPackageEmailTemplate ?? $emailTemplate;
		$this->attach_pdf = $attach_pdf;
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return mixed
	 */
	public function toMail($notifiable)
	{
		// Disable the initMessage feature because of dynamic SMTP configuration
		// $message = $this->initMessage($this->booking);

		$message = (new Common($this->booking, $this->getSubject(), $this->getBody()));

		// Attach the PDF file
		if ($this->attach_pdf) {
			$message->attach($this->storePdfAndGetPath($this->booking), [
				'mime' => 'application/pdf',
			]);
		}

		return $message;
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return array
	 */
	public function toArray($notifiable): array
	{
		return $this->booking;
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
			'{cancellation_policy}',
			'{guide_name}',
			'{customer_head}',
			'{map}',
			'{guide_phone}',
			'{guide_email}',
			'{cust_pay_amount}',
			'{current_date}'
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
			$this->booking['bookingId'] ?? null,
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
			$this->booking['cancellationPolicy'] ?? null,
			$this->booking['guideName'] ?? null,
			$this->booking['custHead'] ?? null,
			config('app.url') . '/images/map.jpg',
			$this->booking['guidePhone'] ?? null,
			$this->booking['guideEmail'] ?? null,
			$this->booking['custPayAmount'] ?? null,
			$this->booking['date'] ?? null,
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
					$formattedString .= $value['rate_for'] . ': ' . $value['tourists'] . '<br>';
				}
				break;
		endswitch;

		return $formattedString;
	}

	/**
	 * Add attachments to the notification.
	 *
	 * @param  array  $booking
	 */
	public function storePdfAndGetPath($booking)
	{
		// Add feature if you want to attach something
	}
}
