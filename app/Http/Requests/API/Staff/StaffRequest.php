<?php

namespace App\Http\Requests\API\Staff;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
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
			'first_name' => 'max:50',
			'last_name' => 'max:50',
			'phone_number' => 'max:30',
			'status' => 'required|in:Active,Inactive',
			'email' => [
				'required',
				'email',
				'max:50',
				// Rule::unique('tour_operator_staff')->where(function ($qry) {
				// 	return $qry->where('deleted_by', 0)
				// 		->whereNot('id', '!=', $this->id);
				// })
			]
		];

		return $rules;
	}

	/**
	 * Get custom attributes for validator errors.
	 *
	 * @return array
	 */
	public function attributes()
	{
		return [
			'email' => 'email address',
		];
	}
}
