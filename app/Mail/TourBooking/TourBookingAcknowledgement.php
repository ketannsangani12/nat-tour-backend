<?php

namespace App\Mail\TourBooking;

use App\Jobs\TimeSlot\IdentifyBookingPackageRelation;
use App\Models\EmailTemplate;
use App\Models\Hotel;
use App\Models\TourBooking;
use App\Models\TourPackageEmailTemplate;
use App\Repositories\TourSlot\TourSlotRepository;
use App\Traits\TemplateModification;
use App\Constants\Constants;
use App\Models\TourBookingForms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Milon\Barcode\Facades\DNS1DFacade as DNS1D;

class TourBookingAcknowledgement extends Mailable implements ShouldQueue
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

	public $waiver_pdf;

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
		$attachedWaiverPdf = $booking['attachWaiverPdf'] ?? 0;
		$this->template = $tourPackageEmailTemplate ?? $emailTemplate;
		if ($attachedWaiverPdf == 1 && isset($booking['waiverPdf']) && !empty($booking['waiverPdf'])) {
			$this->waiver_pdf = $booking['waiverPdf'];
		} else {
			$this->waiver_pdf = '';
		}
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

			if (isset($this->booking['attachWaiverPdf']) && $this->booking['attachWaiverPdf'] == 1 && $this->waiver_pdf != '') {
				$pdfInvoiceFile = Storage::disk('s3')->url($this->waiver_pdf);
				$this->attach($pdfInvoiceFile, [
					'as' => "Waiver-" . $this->booking['bookingId'] . '.pdf',
					'mime' => 'application/pdf',
				]);
			}

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
					'X-Model-OB',
					isset($this->booking['operationBy']) ? $this->booking['operationBy'] : 1
				);
				$message->getHeaders()->addTextHeader(
					'X-Model-UT',
					isset($this->booking['operationByUserType']) ? $this->booking['operationByUserType'] : 'Admin'
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
			'{arrival_time}',
			'{number_of_tourist}',
			'{tour_list_with_seat}',
			'{deleted_for}',
			'{deleted_people}',
			'{url_liability_waiver}',
			'{important_notes}',
			'{things_to_bring}',
			'{cancellation_policy}',
			'{cancellation_url}',
			'{map}',
			'{qr_code}',
			'{hotel}',
			'{hotel_address}',
			'{hotel_location}',
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
			$this->booking['arrivalTime'] ?? null,
			$this->booking['numberoftourist'] ?? null,
			$this->convertArrayToString('tourListwithSeat'),
			$this->booking['deletedFor'] ?? null,
			$this->booking['deletedPeople'] ?? null,
			$this->booking['urlLiabilityWaiver'] ?? null,
			$this->booking['importantNotes'] ?? null,
			$this->convertArrayToString('thingsToBring') ?? null,
			$this->booking['cancellationPolicy'] ?? null,
			$this->booking['cancellationUrl'] ?? null,
			url('/') . '/images/map.jpg',
			$this->generateQrCode('bookingId') ?? null,
			$this->getHotelName(),
			$this->getHotelAddress(),
			$this->getHotelLocation(),
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
				if (!isset($this->booking['tourListwithSeat'])) {
					break;
				}
				$tourListwithSeat = json_decode(json_encode($this->booking['tourListwithSeat']), true);
				foreach ($tourListwithSeat as $value) {
					$formattedString .= $value['rate_for'] . ': ' . ($value['infants'] > 0 ? $value['infants'] : $value['tourists']) . '<br><br>';
				}
				break;

			case 'thingsToBring':
				if (!isset($this->booking['thingsToBring'])) {
					$formattedString = '';
					break;
				}

				$thingsToBring = json_decode(json_encode($this->booking['thingsToBring']), true);
				foreach ($thingsToBring as $value) {
					// If $value is array/object — pick the value property if it exists
					if (is_array($value) && isset($value['value'])) {
						$formattedString .= $value['value'] . "<br>";
					} elseif (is_string($value)) {
						$formattedString .= $value . "<br>";
					} elseif (is_scalar($value)) {
						$formattedString .= (string)$value . "<br>";
					}
				}
				break;
		endswitch;

		$forms = TourBookingForms::where('tour_booking_id', $this->booking['bookingId'])->get();
		if (is_iterable($forms)) {
			foreach ($forms as $form) {
				$formSubmission = json_decode($form->form_submission, true);
				if (is_iterable($formSubmission)) {
					foreach ($formSubmission as $field) {
						if (
							isset($field['name'], $field['value']) &&
							$field['name'] === 'How many Infant age 3 and under are in your group?' &&
							$field['value'] > 0
						) {
							$formattedString .= 'Infants: ' . htmlspecialchars($field['value']) . "<br>";
						}
					}
				}
			}
		}

		return $formattedString;
	}

	/**
	 * Prepare the booking QR code.
	 *
	 * @param  string  $var
	 * @return string
	 */
	public function generateQrCode($var)
	{
		$codeImage = '';
		if (isset($this->booking[$var]) && isset($this->booking['tenantId']) && $this->booking['tenantId'] != '') {
			if (isset($this->booking['externalBookingId']) && $this->booking['externalBookingId'] != null && $this->booking['tenantId'] == Constants::TENANT_GCW) {
				$fileName =  "barcode-" . $this->booking[$var] . ".png";
				if (!Storage::disk('s3')->missing('images/tour-operator/' . $this->booking['tenantId'] . '/barcode/' . $fileName)) {
					$codeImage = Storage::disk('s3')->url('images/tour-operator/' . $this->booking['tenantId'] . '/barcode/' . $fileName);
				} else {
					$barcode = DNS1D::getBarcodePNG($this->booking['externalBookingId'], 'C128', 2, 100);
					$path = "images/tour-operator/" . $this->booking['tenantId'] . "/barcode/";
					Storage::disk('s3')->put($path . $fileName, base64_decode($barcode));
					$codeImage = Storage::disk('s3')->url('images/tour-operator/' . $this->booking['tenantId'] . '/barcode/' . $fileName);
				}
			} else {
				$fileName =  "qrcode-" . $this->booking[$var] . ".png";
				if (!Storage::disk('s3')->missing('images/tour-operator/' . $this->booking['tenantId'] . '/qrcode/' . $fileName)) {
					$codeImage = Storage::disk('s3')->url('images/tour-operator/' . $this->booking['tenantId'] . '/qrcode/' . $fileName);
				} else {
					# add a booking prefix to the qr-code 
					$bookingPrefix = getSettingsByKey("BOOKING_ID_PREFIX");
					$prefix = (isset($bookingPrefix) && isset($bookingPrefix->value)) ? $bookingPrefix->value : "";

					if ($this->booking['tenantId'] == Constants::TENANT_GCW) {
						$qrcode = QrCode::format('png')->size(200)->generate($prefix . "" . $this->booking['bookingGroupId']);
					} else {
						$qrcode = QrCode::format('png')->size(200)->generate($prefix . "" . $this->booking[$var]);
					}

					$path = "images/tour-operator/" . $this->booking['tenantId'] . "/qrcode/";
					Storage::disk('s3')->put($path . $fileName, $qrcode);
					$codeImage = Storage::disk('s3')->url('images/tour-operator/' . $this->booking['tenantId'] . '/qrcode/' . $fileName);
				}
			}
		}

		return $codeImage;
	}

	/**
	 * Prepare the booking hotel information.
	 *
	 * @param  string  $var
	 * @return string
	 */
	public function getHotelName(): mixed
	{
		$booking = TourBooking::find($this->booking['bookingId']);

		$formattedString = '';

		if (isset($booking->hotel_id) && !empty($booking->hotel_id)) {
			$hotel = Hotel::find($booking->hotel_id);

			if ($hotel) {
				$formattedString = '
					<table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" align="center">
						<tbody>
							<tr>
								<td style="font-size: 16px;font-weight: 600;line-height: 1.25;color: #7b7b7b;font-family: \'Open Sans\', sans-serif;text-align: left;">Hotel Pickup</td>
								<td style="font-size: 16px;font-weight: 700;line-height: 1.25;color: #0E0E0E;font-family: \'Open Sans\', sans-serif;text-align: right;">' . $hotel->name . '</td>
							</tr>
						</tbody>
					</table>';
			}
		}

		return $formattedString;
	}

	/**
	 * Prepare the booking hotel information.
	 *
	 * @param  string  $var
	 * @return string
	 */
	public function getHotelAddress(): mixed
	{
		$booking = TourBooking::find($this->booking['bookingId']);

		$formattedString = '';

		if (isset($booking->hotel_id) && !empty($booking->hotel_id)) {

			$hotel = Hotel::find($booking->hotel_id);

			if ($hotel) {
				$formattedString = '
					<table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" align="center">
						<tbody>
							<tr>
								<td style="font-size: 16px;font-weight: 600;line-height: 1.25;color: #7b7b7b;font-family: \'Open Sans\', sans-serif;text-align: left;"></td>
								<td style="font-size: 16px;font-weight: 700;line-height: 1.25;color: #0E0E0E;font-family: \'Open Sans\', sans-serif;text-align: right;">' . $hotel->address . '</td>
							</tr>
						</tbody>
					</table>';
			}
		}

		return $formattedString;
	}

	/**
	 * Prepare the booking location information.
	 *
	 * @param  string  $var
	 * @return string
	 */
	public function getHotelLocation(): string
	{
		$booking = TourBooking::find($this->booking['bookingId']);

		$formattedString = '';

		if (isset($booking->hotel_id) && !empty($booking->hotel_id)) {

			$hotel = Hotel::find($booking->hotel_id);

			if (isset($hotel->location_url)) {
				$formattedString = $hotel->location_url;
			}
		}

		return $formattedString;
	}
}
