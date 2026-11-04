<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class MakePaymentAffiliateRequest extends FormRequest
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
			'tourists' => 'required|numeric',
			'cardnumber' => 'sometimes|numeric',
			'nameoncard' => 'sometimes',
			'expiration' => 'sometimes',
			'cvv' => 'sometimes|numeric',
			'discount_percentage' => 'numeric',
			'discount_fixed_money' => 'numeric'
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function messages(): array
	{
		return [
			'bookingID.required' => __('validation.bookingID_required'),
			'tourists.required' => __('validation.tourists_required'),

		];
	}
}
