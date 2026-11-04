<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class MakePaymentRequest extends FormRequest
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
        return [
			'bookingID' => 'required|numeric',
            'cardnumber' => 'sometimes|numeric',
            'nameoncard' => 'sometimes',
            'expiration' => 'sometimes',
            'cvv' => 'sometimes|numeric'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
			'booking_id.required' => __('validation.booking_id_required'),
			'cardnumber.numeric' => __('validation.cardnumber_numeric'),
			'cvv.numeric' => __('validation.cvv_numeric'),
        ];
    }
}
