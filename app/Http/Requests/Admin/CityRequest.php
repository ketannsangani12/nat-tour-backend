<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CityRequest extends FormRequest
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
			'state' => 'required|integer|min:1',
			'status' => 'required|in:Active,Inactive',
		];

		$t = $this;

		if($this->routeIs(routePrefix() . '.city.store')) {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('cities')->where(function($qry) use($t) {
					return $qry->where('state_id', $t->input('state'));
				})
			];
		} else {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('cities')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('city'))
						->where('state_id', $t->input('state'));
				})
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
			'name.unique' => 'This city is already added in the selected state / province.',
		];
	}
}
