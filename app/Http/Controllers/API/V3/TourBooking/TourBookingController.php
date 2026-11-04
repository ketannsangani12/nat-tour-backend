<?php

namespace App\Http\Controllers\API\V3\TourBooking;

use App\Http\Controllers\API\V3\ApiController;
use App\Http\Requests\API\TourBooking\BookingCreateRequest;
use App\Http\Requests\API\TourBooking\BookingCheckInStatusRequest;
use App\Http\Requests\API\TourBooking\BookingTipPaymentRequest;
use App\Http\Requests\API\TourBooking\BookingSendEmailRequest;
use App\Http\Requests\API\TourBooking\CalendarRequest;
use App\Http\Requests\API\TourBooking\MakePaymentRequest;
use App\Http\Requests\API\TourBooking\SaveAffiliateBookingRequest;
use App\Http\Requests\Common\ChangePeopleRequest;
use App\Http\Requests\Common\MakeCartPaymentRequest;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use App\Exceptions\Core\GeneralCoreException;
use App\Http\Requests\API\TourBooking\GroupPeopleNewBookingRequest;
use App\Http\Requests\API\TourBooking\SearchRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Transformers\CustomChargeSubmittedFormResource;
use App\Transformers\CustomChargeVisibilityResource;
use App\Repositories\Notify\SMSNotifications;
use App\Http\Controllers\Common\SmsController;

class TourBookingController extends ApiController
{
    protected $tourBookingRepository;
    protected $smsNotifications;
    /**
     * @param TourBookingRepository  $tourBookingRepository
     */
    public function __construct(TourBookingRepository $tourBookingRepository, SMSNotifications $smsNotifications)
    {
        $this->tourBookingRepository = $tourBookingRepository;
        $this->smsNotifications = $smsNotifications;
    }

    /**
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function initBooking(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.init_booking_data_success'))
                ->respond($this->tourBookingRepository->initBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param BookingCreateRequest $request
     * @throws Exception
     */
    public function createBooking(BookingCreateRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.create_booking_success'))
                ->respond($this->tourBookingRepository->createBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Create Combo booking
     *
     * @param $request
     * @return mixed
     */
    public function createComboBooking(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.create_booking_success'))
                ->respond($this->tourBookingRepository->createComboBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $id
     * @param bool $forPrinting
     * @throws Exception
     */
    public function booking(int $id, $forPrinting = false): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->booking($id, $forPrinting));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $id
     * @throws Exception
     */
    public function bookingStatus(int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->bookingStatus($id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $id
     * @throws Exception
     */
    public function resendEmail(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.resend_booking_email_success'))
                ->respond($this->tourBookingRepository->resendEmail($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Check the overbooking for the specific slot user trying to book
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function checkOverbooking(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.check_overbooking_success'))
                ->respond($this->tourBookingRepository->checkOverbooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_reschedule_success'))
                ->respond($this->tourBookingRepository->reschedule($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_cancellation_success'))
                ->respond($this->tourBookingRepository->cancel($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Restore the booking which is cancelled
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_restored'))
                ->respond($this->tourBookingRepository->restoreBooking($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Calculate card amount for Invoice/Cash to CC feature
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function calculateCardAmount(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.card_amount_data_success'))
                ->respond($this->tourBookingRepository->calculateCardAmount($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Change the payment type of booking
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function changePaymentType(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.payment_type_changed'))
                ->respond($this->tourBookingRepository->changeHistoryPaymentType($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_refund_success'))
                ->respond($this->tourBookingRepository->refund($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param BookingCheckInStatusRequest $request
     * @throws Exception
     */
    public function updateCheckInStatus(BookingCheckInStatusRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_checkin_status_update_success'))
                ->respond($this->tourBookingRepository->updateCheckInStatus($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param BookingTipPaymentRequest $request
     * @throws Exception
     */
    public function tipPayment(BookingTipPaymentRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_tippayment_success'))
                ->respond($this->tourBookingRepository->tipPayment($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function saveNote(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_addnote_success'))
                ->respond($this->tourBookingRepository->saveNote($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $id
     * @throws Exception
     */
    public function saveComments(Request $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_addcomment_success'))
                ->respond($this->tourBookingRepository->saveComments($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_search_success'))
                ->respond($this->tourBookingRepository->search($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param BookingSendEmailRequest $request
     * @param int $id
     * @throws Exception
     */
    public function sendEmail(BookingSendEmailRequest $request, int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.send_booking_success'))
                ->respond($this->tourBookingRepository->sendEmail($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function calendar(CalendarRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.create_calendar_success'))
                ->respond($this->tourBookingRepository->calendar($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function editBooking(Request $request): JsonResponse
    {
        try {
            $message = (isset($request->refund_Type) && $request->refund_Type == "dont_refund")
                ? __('tour-operator.api_response.booking_updated')
                : __('tour-operator.api_response.edit_booking_success');

            return $this
                ->setMessage($message)
                ->respond($this->tourBookingRepository->editBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param ChangePeopleRequest $request
     * @throws Exception
     */
    public function changePeopleBooking(ChangePeopleRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.change_people_success'))
                ->respond($this->tourBookingRepository->changePeopleBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function splitPeopleBooking(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.split_people_success'))
                ->respond($this->tourBookingRepository->splitPeopleBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param GroupPeopleNewBookingRequest $request
     * @throws Exception
     */
    public function getGroupOfPeopleNewBook(GroupPeopleNewBookingRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.group_people_booking_success'))
                ->respond($this->tourBookingRepository->getGroupOfPeopleNewBook($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $id
     *
     * @throws Exception
     */
    public function bookingHistory(int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->bookingHistory($id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $id
     * @param bool $forPrinting
     * @throws Exception
     */
    public function printInfo(int $id): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.print_booking_success'))
                ->respond($this->tourBookingRepository->bookingPrint($id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $date
     * @param string $tourPackagesId
     * @param int $PaymentStatus
     * @param string $slotId
     * @throws Exception
     */
    public function dayTimeSlots(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_daytime_slots_success'))
                ->respond($this->tourBookingRepository->agenda($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return mixed
     *
     * @throws Exception
     */
    public function stripeGenerateToken(): mixed
    {
        try {
            $token = $this->tourBookingRepository->stripeGenerateToken();
            echo json_encode(array('secret' => $token['secret']));
            exit;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function stripeCancelCardReader(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.cancel_card_reader_success'))
                ->respond($this->tourBookingRepository->stripeCancelCardReader($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function stripeCreatePaymentIntent(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.create_payment_intent_success'))
                ->respond($this->tourBookingRepository->stripeCreatePaymentIntent($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param MakePaymentRequest $request
     * @throws Exception
     */
    public function paymentNewBooking(MakePaymentRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.make_payment_success'))
                ->respond($this->tourBookingRepository->paymentNewBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param MakeCartPaymentRequest $request
     * @throws Exception
     */
    public function paymentCartBookings(MakeCartPaymentRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.make_payment_cart_success'))
                ->respond($this->tourBookingRepository->paymentCartBookings($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param SaveAffiliateBookingRequest $request
     * @throws Exception
     */
    public function saveAffiliateBooking(SaveAffiliateBookingRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.save_affiliate_booking_success'))
                ->respond($this->tourBookingRepository->saveAffiliateBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookTourBeforeCapture(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->cardReaderBeforeCapture($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookTourAfterCapture(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->cardReaderAfterCapture($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookTourBeforeCaptureManual(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->manualCardBeforeCapture($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function cancelTourManualCard(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_updated'))
                ->respond($this->tourBookingRepository->cancelTourManualCard($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookTourAfterCaptureManual(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->manualCardAfterCapture($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookComboTourBeforeCaptureManual(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->manualCardBeforeCaptureComboTour($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookComboTourAfterCaptureManual(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->manualCardAfterCaptureComboTour($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookComboTourBeforeCapture(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->cardReaderBeforeCaptureComboTour($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function bookComboTourAfterCapture(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.booking_success'))
                ->respond($this->tourBookingRepository->cardReaderAfterCaptureComboTour($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $bookingId
     * @throws Exception
     */
    public function saveReferenceBooking(Request $request, $bookingId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.reference_booking'))
                ->respond($this->tourBookingRepository->saveReferenceBooking($request, $bookingId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $bookingId
     * @throws Exception
     */
    public function affiliateBookings($tourSlotId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.affiliate_Bookings'))
                ->respond($this->tourBookingRepository->affiliateBookings($tourSlotId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return mixed
     *
     * @param Request $request
     * @throws Exception
     */
    public function dayTimeSlotsbooking(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.day_Time_Slots_booking'))
                ->respond($this->tourBookingRepository->dayTimeSlotsbooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param int $bookingId
     * @throws Exception
     */
    public function unblockAffiliateSoftBooking($bookingId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.soft_unblock_affiliate_booking'))
                ->respond($this->tourBookingRepository->affiliateSoftBookingUnblock($bookingId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param int $bookingId
     * @throws Exception
     */
    public function addOrUpdateHotel(Request $request, $bookingId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.add_or_update_hotel_pickup'))
                ->respond($this->tourBookingRepository->hotelPickupUpdate($request->all(), $bookingId));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @param string $cartId
     * @throws Exception
     */
    public function comboBookingInvoice(Request $request, string $cartId): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.combo_receipt_data_success'))
                ->respond($this->tourBookingRepository->comboBookingInvoice($cartId, $request->all()));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function editDiscount(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.edit_discount_success'))
                ->respond($this->tourBookingRepository->editDiscount($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @param Request $request
     * @throws Exception
     */
    public function  makeCardDiscountPayment(Request $request)
    {
        try {
            if ($request->has('discountHistory')) {
                $discountHistoryArray = $request->discountHistory;
                if ($discountHistoryArray['payment_method'] == 'Card') {
                    return match ($discountHistoryArray['payment_type']) {
                        'payment' => $this
                            ->setMessage(__('tour-operator.api_response.edit_discount_success'))
                            ->respond($this->tourBookingRepository->editDiscountCardPayment($request)),
                        default => $this
                            ->setMessage(__('tour-operator.api_response.edit_discount_success'))
                            ->respond($this->tourBookingRepository->editDiscountRefund($request)),
                    };
                }
            }

            throw new Exception('Invalid details. please reload this page and try again');
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws Exception
     */
    /**
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getCustomChargeVisibility()
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond(new CustomChargeVisibilityResource($this->tourBookingRepository->getCustomChargeVisibility()));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  int $bookingId
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getCustomChargeSubmission($bookingId)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond(new CustomChargeSubmittedFormResource($this->tourBookingRepository->getCustomChargeData($bookingId)));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Add custom charge to the booking which is already created
     *
     * @param Request $request
     * @param int $id
     */
    public function addCustomCharge(Request $request, int $id)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.custom_charge_success'))
                ->respond($this->tourBookingRepository->addCustomCharge($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Refund Custom Charge
     *
     * @param Request $request
     * @param int $id
     */
    public function refundCustomCharge(Request $request, int $id)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.custom_charge_success'))
                ->respond($this->tourBookingRepository->refundCustomCharge($request, $id));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getPromoDetail(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond($this->tourBookingRepository->getPromoDetail($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update checkin status of bundle package booking
     *
     * @param Request $request
     */
    public function updatePackageRedeemTicketStatus(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond($this->tourBookingRepository->updatePackageRedeemTicketStatus($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Switch package in existing bookings
     *
     * @param Request $request
     */
    public function switchPackageBooking(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-package.api_response.packages_data_success'))
                ->respond($this->tourBookingRepository->switchPackageBooking($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Request to manager
     *
     * @param Request $request
     */
    public function requestToManager(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.request_data_success'))
                ->respond($this->tourBookingRepository->requestToManager($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * The manual id check
     *
     * @param Request $request
     */
    public function manualIdCheck(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.request_data_success'))
                ->respond($this->tourBookingRepository->manualIdCheck($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * The manual pin check
     *
     * @param Request $request
     */
    public function manualPinCheck(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.request_data_success'))
                ->respond($this->tourBookingRepository->manualPinCheck($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Post method to send plain text sms message
     *
     * @param Request $request
     */
    public function smsSend(Request $request)
    {
        try {
            return $this
                ->setMessage(__('tour-operator.api_response.message_sent_success'))
                ->respond($this->tourBookingRepository->smsSend($request));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Post method to send sms qr code link
     *
     * @param Request $request
     */
    public function smsSendLink(Request $request)
    {
        try {
            $bookingId = intval($request->bookingId);
            $props = (array) SmsController::getBookingInfo($bookingId);
            return $this
                ->setMessage(__('tour-operator.api_response.message_sent_success'))
                ->respond($this->tourBookingRepository->smsSendLink($bookingId, $props));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
