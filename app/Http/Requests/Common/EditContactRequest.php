<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class EditContactRequest extends FormRequest
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

		$rules = [
			'name' => 'required',
			'age' => 'required',
            'weight' => 'required'
		];


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
			'name.required' => 'Please add contact name.',
			'age.required' => 'Please add age.',
            'weight.required' => 'Please add weight.',
		];
	}
}
