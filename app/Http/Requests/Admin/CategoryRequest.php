<?php

namespace App\Http\Requests\Admin;

use App\Rules\NonExponential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
			'status' => 'required|in:Active,Inactive',
		];

		$t = $this;

		if($this->routeIs(routePrefix() . '.category.store')) {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
                'unique:categories'
			];
			$rules['slug'] = [
				'required',
				'max:80',
                'unique:categories'
			];
		} else {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('categories')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('category'));
				})
			];
			$rules['slug'] = [
				'required',
				'max:80',
				Rule::unique('categories')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('category'));
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
			'name.unique' => 'This category is already added.',
		];
	}
}
