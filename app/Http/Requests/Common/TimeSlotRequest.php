<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeSlotRequest extends FormRequest
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
		$rules = [];
		$t = $this;

		if(strpos(\Route::currentRouteName(), routePrefix() . '.time-slot.store') !== false){
			$action = 'store';
		}else{
			$action = 'update';
		}
		if($action == 'store') {
			$rules += [
				'time_from' => 'required|array|min:1',
				'time_from.*' => 'required',
				'time_to' => 'nullable',
        		'time_to.*' => 'after:time_from.*',
				'status' => 'required|array|min:1',
				'status.*' => 'in:Active,Inactive',
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
			'time_from.required' => 'At least one time slot is required.',
			'time_to.*.after' => 'To(end) time must be after start(from) time.'
		];
	}
}
