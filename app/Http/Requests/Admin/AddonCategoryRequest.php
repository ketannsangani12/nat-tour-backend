<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddonCategoryRequest extends FormRequest
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
			'sort_order' => 'nullable|integer|min:0',
		];

		$t = $this;

		if($this->routeIs(routePrefix() . '.addon-category.store')) {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
                'unique:addon_categories'
			];
			$rules['slug'] = [
				'required',
				'max:80',
                'unique:addon_categories'
			];
		} else {
			$rules['name'] = [
				'bail',
				'required',
				'max:80',
				Rule::unique('addon_categories')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('addon_category'));
				})
			];
			$rules['slug'] = [
				'required',
				'max:80',
				Rule::unique('addon_categories')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('addon_category'));
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
			'name.unique' => 'This addon category is already added.',
		];
	}
}
