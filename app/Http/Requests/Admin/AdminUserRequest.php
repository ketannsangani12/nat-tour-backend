<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserRequest extends FormRequest
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
			'name' => 'required:max:50',
			'password' => 'min:8',
			'status' => 'required|in:Active,Inactive',
			'role' => 'required',
			'department_id' => 'required'
		];

		if($this->routeIs('admin.adminprofile')) {
			$rules['password'] = 'required|' . $rules['password'];
		}

		if($this->routeIs('admin.admin-user.store')) {
			$rules['password'] = 'required|' . $rules['password'];
			
			$rules['email'] = [
				'bail',
				'required',
				'email',
				'max:50',
				Rule::unique('admins')->where(function($qry) {
					return $qry->where('deleted_by', 0);
				}),
			];
		} else { // edit-update
			$t = $this;

			$rules['email'] = [
				'bail',
				'required',
				'email',
				'max:50',
				Rule::unique('admins')->where(function($qry) use($t) {
					return $qry->where('deleted_by', 0)
						->where('id', '!=', $t->route()->parameter('admin_user'));
				}),
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
			'department_id' => 'department',
		];
	}
}
