<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourOperatorRequest extends FormRequest
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
			'email' => 'email|max:50',
			'phone_number' => 'max:30',
			'postal_code' => 'max:20',
			'city' => 'required',
			'state' => 'required',
			'country' => 'required',
			'logo' => 'image',
		];

		if($this->routeIs('admin.tour-operator.store')) {
			$rules['name'] = 'bail|required|max:100|unique:tour_operators';
			$rules['status'] = 'required|in:Active,Inactive';
		} else { // edit-update
			$rules['name'] = [
				'bail',
				'required',
				'max:100',
				Rule::unique('tour_operators')->ignore($this->route()->parameter('id')),
			];
		}

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
