<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;

class BookingCreateRequest extends FormRequest
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
		if(request()->is('api/*')){
			return [
				'slot_time' => 'required|integer|min:1',
				'customer_name' => 'required|max:80',
				'customer_email' => 'required|email|max:50',
				'customer_phone_number' => 'max:30',
				'tourists' => 'required|array',
				//'tourists.*' => 'integer|min:0',
				'rate_group' => 'required|array',
				//'rate_group.*' => 'integer|min:1',
				'discount_percentage' => 'numeric',
				'discount_fixed_money' => 'numeric',
				'booked_by_staff' => 'numeric',
			];
		}else{
			return [
				'slot_time' => 'required|integer|min:1',
				'customer_name' => 'required|max:80',
				'customer_email' => 'required|email|max:50',
				'customer_phone_number' => 'max:30',
				'tourists' => 'required|array',
				'tourists.*' => 'integer|min:0',
				'rate_group' => 'required|array',
				'rate_group.*' => 'integer|min:1',
				'discount_percentage' => 'numeric',
				'discount_fixed_money' => 'numeric',
				'booked_by_staff' => 'numeric',
			];
		}

	}

	/**
	* Get the error messages for the defined validation rules.
	*
	* @return array
	*/
	public function messages()
	{
		return [
			'slot_time.required' => __('validation.slot_time_required'),
			'tourists.required' => __('validation.tourists_required'),
			//'tourists.*.required' => __('validation.tourists.*_required'),
			'rate_group.required' => __('validation.rate_group_required'),
			//'rate_group.*.required' => __('validation.rate_group.*_required'),
		];
	}
}
