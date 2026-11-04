<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminProfileRequest extends FormRequest
{
	/**
	* Determine if the user is authorized to make this request.
	*
	* @return bool
	*/
	public function authorize()
	{
		return (session('userType') == 'Admin');
	}

	/**
	* Get the validation rules that apply to the request.
	*
	* @return array
	*/
	public function rules()
	{ 
		$rules = [
			'name' => 'required:max:50',
			'status' => 'required|in:Active,Inactive',
		];

		return $rules;
	}
}
