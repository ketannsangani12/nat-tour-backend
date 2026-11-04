<?php

namespace App\Http\Requests\Admin;

use App\Rules\NonExponential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StateRequest extends FormRequest
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
			'country' => 'required|integer|min:1',
			'permit_fee' => [
				'numeric',
				'min:0',
				new NonExponential,
			],
			'status' => 'required|in:Active,Inactive',
		];

		$t = $this;

		if($this->routeIs(routePrefix() . '.state.store')) {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('states')->where(function($qry) use($t) {
					return $qry->where('country_id', $t->input('country'))
						->where('deleted_by', 0);
				})
			];
		} else {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('states')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('state'))
						->where('country_id', $t->input('country'))
						->where('deleted_by', 0);
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
			'name.unique' => 'This state is already added in the selected country.',
		];
	}
}
