<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class BookingTipPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {

		if(request()->is('api/*')){
			$rules = [
				'bookingID' => 'required|numeric',
				'amount' => 'required|numeric',
				'staffname' => 'required',
				'paymentintentid'=>'required'
			];
		}else{

            if (request()->has('payment_intent_id') && request()->payment_intent_id) {
                $rules = [ 'amount' => 'required|numeric'];
            } else {
                $rules = [
                    'bookingID' => 'required|numeric',
                    'amount' => 'required|numeric',
                    'staffname' => 'required',
                ];
            }
		}
		return $rules;
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'bookingID.required' => __('validation.bookingID_required'),
            'amount.required' => __('validation.amount_required'),
            'staffname.required' => __('validation.staffname_required'),
            'cardnumber.required' => __('validation.cardnumber_required'),
            'nameoncard.required' => __('validation.nameoncard_required'),
            'expiration.required' => __('validation.expiration_required'),
            'cvv.required' => __('validation.cvv_required'),
			'paymentintentid.required' => __('validation.paymentintentid_required'),
        ];
    }
}
