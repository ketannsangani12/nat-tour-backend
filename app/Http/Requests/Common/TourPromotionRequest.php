<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourPromotionRequest extends FormRequest
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
		$t = $this;

		$rules = [
			'code' => 'required|max:20',
			'max_allowed_usages' => 'required|integer|min:0',
			'discount_value' => 'required|numeric|min:0',
			'discount_value_type' => [
				'required',
				Rule::in([ 'Percent', 'Fixed Money' ])
			],
			'status' => 'required|in:Active,Inactive',
		];

		if(session('userType') == 'Admin')
			$rules['tour_package'] = 'required|integer|min:1';
		
		return $rules;
	}

	/**
	* Get the error messages for the defined validation rules.
	*
	* @return array
	*/
	public function messages()
	{
		return [
			'start_date.required' => 'Either start date or end date is required.',
			'end_date.required' => 'Either start date or end date is required.',
		];
	}
}
