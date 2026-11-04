<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class OperatorProfileRequest extends FormRequest
{
	/**
	* Determine if the user is authorized to make this request.
	*
	* @return bool
	*/
	public function authorize()
	{
		return (session('userType') == 'Tour Operator');
	}

	/**
	* Get the validation rules that apply to the request.
	*
	* @return array
	*/
	public function rules()
	{
		$rules = [
			'first_name' => 'required:max:50',
			'password' => 'min:8',
			'status' => 'required|in:Active,Inactive',
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
			'password' => 'The password must be at least 8 characters',
		];
	}
}
