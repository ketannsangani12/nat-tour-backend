<?php

namespace App\Services\Webhook;

use App\Constants\Constants;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\TourBooking;
use App\Contracts\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentIntentSucceededForTip
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($paymentIntent)
    {
        if (isset($paymentIntent->metadata->app)) {
            $bookingId = trim($paymentIntent->metadata['booking_id']);

            try {
                $tourBooking = TourBooking::with('tourPackage')
                    ->where('id', $bookingId)
                    ->first();

                if (!empty($tourBooking)) {

                    $retrieveOptions = ['expand' => ['charges.data.transfer']];
                    $paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntent->id, $retrieveOptions);

                    $crd = $cardtype = '';
					$paymentMethod = '';
                    if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) {
                        $crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->last4 : '';
                        $cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card_present->brand : '';
						$paymentMethod = 'Card Reader';
                    } else if (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) {
                        $crd = (isset($paymentIntentRelational->charges->data[0]->payment_method_details->card->last4)) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->last4 : '';
                        $cardtype = !empty($paymentIntentRelational->charges->data[0]->payment_method_details->card->brand) ? $paymentIntentRelational->charges->data[0]->payment_method_details->card->brand : '';
						$paymentMethod = 'Manual Card';
                    }

                    if ($tourBooking->created_by_user_type == "Admin") {
                        $user = Admin::find($tourBooking->created_by);
                        $created_by_with_name = $user->name . ': (' . "Admin" . ')';
                    } elseif ($tourBooking->created_by_user_type == "Tour Operator") {
                        $user = Staff::find($tourBooking->created_by);
                        $created_by_with_name = $user->name . ': (' . "Tour Operator" . ')';
                    } else {
                        $user = Customer::find($tourBooking->created_by);
                        $created_by_with_name = $user->name . ': (' . "Affiliate" . ')';
                    }

                    DB::table('tour_booking_history')
                        ->insertGetId([
                            'tour_booking_id' => $bookingId,
                            'operation' => 'Tip',
                            'details' => json_encode([
                                'amount' => $paymentIntentRelational->metadata->amount,
                                'payment_type' => 'Card',
                                'card_number' => $crd,
                                'card_type' => $cardtype,
                                'created_by_with_name' => $created_by_with_name
                            ], JSON_NUMERIC_CHECK),
                            'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
                            'operation_by' => $tourBooking->created_by,
                            'operation_by_user_type' => $tourBooking->created_by_user_type,
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                        ]);

                    // Add connected account metadata values
                    static::addStripeConnectedMetadata($bookingId, $stripe, $paymentIntentRelational,$paymentMethod);
                }
            } catch (Exception $exception) {
                Log::error($exception);
                if ($bookingId != '') {
                    $tourBooking = TourBooking::find($bookingId);
                    DB::table('tour_booking_history')
                        ->insert([
                            'tour_booking_id' => $bookingId,
                            'operation' => 'Error',
                            'details' => $exception->getMessage(),
                            'operation_at' => mysqlDT(), // laravel doesn't seems to honouring timezone given in config > app.php!
                            'operation_by' => Auth::id() ?? 1,
                            'operation_by_user_type' => $tourBooking->created_by_user_type ?? 'Admin',
                            'updated_at' => Carbon::now(),
                            'created_at' => Carbon::now(),
                        ]);
                }
            }
        }
    }

    /**
     * Add stripe connected account metadata
     *
     * @param int $bookingId
     * @param mixed $stripe
     * @param mixed $paymentIntentRelational
     * @param mixed $paymentMethod
     * @return void
     */
    public static function addStripeConnectedMetadata($bookingId, $stripe, $paymentIntentRelational,$paymentMethod=''): void
    {
        if (isset($paymentIntentRelational->charges->data[0]->transfer->destination_payment)) {

            $tourBooking = TourBooking::with([
                    'tourPackage.tourOperator',
                    'tourSlotTime.tourSlotDate',
                    'customer'
                ])
                ->find($bookingId);

            $tourPackage = $tourBooking->tourPackage;

            $customer = $tourBooking->customer;

            $From = ($paymentMethod == 'Manual Card') ? 'iOS Manual Card' : 'iOS Card Reader';

            $stripeMetadata = [
                'description' => 'Booking Id: ' . $bookingId .
                    "\nTour Package: " . $tourPackage->name .
                    "\nTour Date: " . $tourBooking->tourSlotTime->tourSlotDate->date .
                    "\nTour Time: " . $tourBooking->tourSlotTime->time_from .
                    "\nTourists: " . $tourBooking->tourists .
                    "\nEmail: " . $customer->email .
                    "\nFrom: ".$From,
                'metadata' => [
                    'booking_id' => $bookingId,
                    'tour_package' => $tourPackage->name,
                    'tour_date' => $tourBooking->tourSlotTime->tourSlotDate->date,
                    'tour_time' => $tourBooking->tourSlotTime->time_from,
                    'tourists' => $tourBooking->tourists + $tourBooking->infants,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone_number,
                    'email' => $customer->email,
                    'amount' => $paymentIntentRelational->metadata->amount,
                    'fee' => $paymentIntentRelational->metadata->fee,
                    'split' => $paymentIntentRelational->metadata->split,
                    'is_pax_added' => $paymentIntentRelational->metadata->is_pax_added,
                    'from' => $From,
                    'section' => $paymentIntentRelational->metadata->section,
                    'app' => Constants::STATUS_ONE,
                    'tenant_id' => tenant()->id,
                ],
            ];

            $chargeParams = $stripeMetadata;
            app(PaymentGatewayInterface::class)->updateConnectedPayment(
                $paymentIntentRelational->charges->data[0]->transfer->destination_payment,
                $chargeParams,
                $paymentIntentRelational->charges->data[0]->transfer->destination
            );
        }
    }
}
