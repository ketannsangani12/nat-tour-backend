<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
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
			'slot_time' => 'required|integer|min:1',
			'tourists' => 'required|array',
			'tourists.*' => 'integer|min:0',
			'rate_group' => 'required|array',
			'rate_group.*' => 'integer|min:1'
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
			'slot_time.required' => 'Please select a time slot.',
			'tourists.required' => 'Please select a time slot and tourists.',
			'tourists.*.required' => 'Please select tourists from at least one age group.',
			'rate_group.required' => 'Please select a time slot and tourists.',
			'rate_group.*.required' => 'Please select tourists from at least one age group.',
		];
	}
}
