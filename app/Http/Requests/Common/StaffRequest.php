<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
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
			'first_name' => 'max:50',
			'last_name' => 'max:50',
			'phone_number' => 'max:30',
			'status' => 'required|in:Active,Inactive',
			// 'permission' => 'required|array',
		];

		if(strpos(Route::currentRouteName(), routePrefix() . '.staff.store') !== false) {
			$rules += [
				'password' => 'required|min:8',
			];
			$rules = [
				'role' => 'required',
				'department_id' => 'required',
			];
			$rules['email'] = [
				'bail',
				'required',
				'email',
				'max:50',
				Rule::unique('tour_operator_staff')->where(function($qry) {
					return $qry->where('status', '!=', "Deleted");
				})
			];
			$rules['manager_id'] = [
				Rule::unique('tour_operator_staff')->where(function($qry) {
					return $qry->where('status', '!=', "Deleted")
								  ->where('manager_id', '!=', 0);
				})
			];
		} else {
			$rules['email'] = [
				'bail',
				'required',
				'email',
				'max:50',
				Rule::unique('tour_operator_staff')->where(function($qry) {
					return $qry->where('status', '!=', "Deleted")
						->where('id', '!=', $this->route()->parameter('id'));
				}),
			];
			$rules['manager_id'] = [
				Rule::unique('tour_operator_staff')->where(function($qry) {
					return $qry->where('status', '!=', "Deleted")
						->where('id', '!=', $this->route()->parameter('id'))
						->where('manager_id', '!=', 0);
				}),
			];
			$rules = [
				'department_id' => 'required',
			];
			if(request('password') !== '')
				$rules['password'] = 'min:8';
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
			'department_id' => 'department'
		];
	}
}
