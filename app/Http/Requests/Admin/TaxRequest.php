<?php

namespace App\Http\Requests\Admin;

use App\Rules\NonExponential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRequest extends FormRequest
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
			'value' => [
				'numeric',
				'min:0.01',
				new NonExponential,
			],
			'value_type' => 'required|in:Percent,Fixed Money',
			'status' => 'required|in:Active,Inactive',
		];

		$t = $this;

		if($this->routeIs(routePrefix() . '.tax.store')) {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('taxes')->where(function($qry) use($t) {
					return $qry->where('state_id', $t->input('state'));
				})
			];
		} else {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('taxes')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('tax'))
						->where('state_id', $t->input('state'));
				})
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
			'value' => 'tax value',
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
			'name.unique' => 'This tax is already added for the selected state / province.',
		];
	}
}
