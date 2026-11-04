<?php

namespace App\Http\Requests\Admin;

use App\Rules\NonExponential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
			'permission' => 'required|array',
			/*'display_order' => [
				'required',
				'numeric',
				'integer',
				'between:0,255',
				new NonExponential,
			],
			'status' => 'required|in:Active,Inactive',*/
		];


		if($this->routeIs(routePrefix() . '.role.store')){
			$rules['display_name'] = 'bail|required|max:30|unique:roles';
		    $rules['guard_name'] = 'required';
		} else {
			$rules['display_name'] = [
				'bail',
				'required',
				'max:30',
				Rule::unique('roles')->ignore($this->route()->parameter('role'))
			];
			$rules['guard_name'] = 'required';
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
			'name.unique' => 'This role is already added.',
		];
	}
}
