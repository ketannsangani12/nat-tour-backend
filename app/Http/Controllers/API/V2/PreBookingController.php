<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\API\V2\BaseController;
use App\Models\PreBooking;
use Illuminate\Http\Request;

class PreBookingController extends BaseController
{
    /**
     * @param \Illuminate\Http\Request $request
     */
    public function createPreBooking(Request $request)
    {
        $preBooking = PreBooking::where('payment_intent_id', $request->payment_intent_id)->first();

        if (!$preBooking) {
            PreBooking::create([
                'payment_intent_id' => $request->payment_intent_id,
                'stripe_customer_id' => $request->stripe_customer_id,
                'data' => $request->all(),
            ]);
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function deletePreBooking(Request $request)
    {
        $preBooking = PreBooking::where('payment_intent_id', $request->payment_intent_id)
            ->where('status', 'pending')
            ->first();

        if ($preBooking) {
            $preBooking->delete();
        }
    }
}
