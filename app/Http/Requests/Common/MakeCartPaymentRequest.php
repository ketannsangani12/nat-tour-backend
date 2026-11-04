<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class MakeCartPaymentRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'bookingID' => 'required|numeric',
			'cartID' => 'required',
			'payment_method_cart_payment' => 'required',
			'cardnumber' => 'sometimes|numeric',
			'nameoncard' => 'sometimes',
			'expiration' => 'sometimes',
			'cvv' => 'sometimes|numeric'
		];
	}

	/**
	 * Get the error messages for the defined validation rules.
	 *
	 * @return array
	 */
	public function messages()
	{
		return [
			'booking_id.required' => __('validation.booking_id_required'),
			'cart_id.required' => __('validation.cart_id_required'),
			'payment_method_cart_payment.required' => __('validation.payment_method_cart_payment_required'),
			'cardnumber.numeric' => __('validation.cardnumber_numeric'),
			'cvv.numeric' => __('validation.cvv_numeric'),
		];
	}
}
