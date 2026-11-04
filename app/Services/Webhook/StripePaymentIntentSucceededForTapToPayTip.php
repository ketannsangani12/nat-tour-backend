<?php

namespace App\Services\Webhook;

use App\Constants\Constants;
use App\Models\Staff;
use App\Contracts\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentIntentSucceededForTapToPayTip
{
    /**
     * Execute the service.
     *
     * @return void
     */
    public static function run($paymentIntent)
    {
        if (isset($paymentIntent->metadata->app)) {
            $guideId = trim($paymentIntent->metadata['guide_id']);
            try {
                $guide = Staff::find($guideId);
                $gratuityCount = DB::table('gratuities')
                    ->where('transaction_number', $paymentIntent->id)
                    ->count();
                if ($guide && $gratuityCount == 0) {
                    $retrieveOptions = ['expand' => ['charges.data.transfer']];
                    $paymentIntentRelational = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntent->id, $retrieveOptions);

                    $tipAmount = $paymentIntent->metadata['tip_amount'];
                    $fees = $paymentIntent->metadata['fee'];
                    $amount = $paymentIntent->metadata['amount'];
                    $section = $paymentIntent->metadata['section'];

                    DB::table('gratuities')->insertGetId([
                        'booking_id' => NULL,
                        'customer_id' => NULL,
                        'gratuity_setting_id' => NULL,
                        'guide_id' => $guideId,
                        'amount' => $tipAmount,
                        'fees' => $fees,
                        'transaction_number' => $paymentIntent->id,
                        'application_fee_id' => $paymentIntent->charges->data[0]->application_fee ?? null,
                        'gratuity_type' => 'direct',
                        'payment_method' => 'Card',
                        'status' => 'paid',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'created_by_user_type' => 'Customer'
                    ]);

                    // Add connected account metadata values
                    static::addStripeConnectedMetadata($guideId, $tipAmount, $fees, $amount, $section, $stripe, $paymentIntentRelational);
                }
            } catch (Exception $exception) {
                Log::error($exception);
            }
        }
    }

    /**
     * Add stripe connected account metadata
     *
     * @param int $bookingId
     * @param mixed $stripe
     * @param mixed $paymentIntentRelational
     * @return void
     */
    public static function addStripeConnectedMetadata($guideId, $tipAmount, $fees, $amount, $section, $stripe, $paymentIntentRelational): void
    {
        if (isset($paymentIntentRelational->charges->data[0]->transfer->destination_payment)) {

            $guideInfo = Staff::find($guideId);

            $From = 'iOS Tap to Pay';

            $stripeMetadata = [
                'description' => 'Guide Id: ' . $guideId .
                    "\nGuide Name: " . $guideInfo->first_name . " " . $guideInfo->last_name .
                    "\nFrom: " . $From,
                'metadata' => [
                    'guide_id' => $guideInfo->id,
                    'guide_name' => $guideInfo->first_name . " " . $guideInfo->last_name,
                    'tip_amount' => $tipAmount,
                    'fee' => $fees,
                    'amount' => $amount,
                    'from' => $From,
                    'section' => $section,
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
