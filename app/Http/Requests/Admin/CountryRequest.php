<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
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
			'status'       => 'required|in:Active,Inactive',
			'phone_code'   => 'nullable|max:10',
			'locale'       => 'nullable|max:10',
		];

		if($this->routeIs('admin.country.store'))
			$rules['name'] = 'bail:required|max:80|unique:countries';
		else {
			$rules['name'] = [
				'required',
				'max:80',
				Rule::unique('countries')->ignore($this->route()->parameter('country')),
			];
		}

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
			'name.unique' => 'This country is already added.',
		];
	}
}
