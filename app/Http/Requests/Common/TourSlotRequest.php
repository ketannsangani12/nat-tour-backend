<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class TourSlotRequest extends FormRequest
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
			'tour_package' => 'required|integer',
			// 'seats' => 'required|integer|min:1',
			'status' => 'required|in:Active,Inactive',
		];

		$t = $this;

		if(strpos(Route::currentRouteName(), routePrefix() . '.slot.store') !== false)
			$action = 'store';
		elseif(strpos(Route::currentRouteName(), routePrefix() . '.slot.repeat') !== false)
			$action = 'repeat';
		else
			$action = 'update';

		if($action == 'store' || $action == 'repeat') {
			$rules += [
				'time_from' => 'required|array|min:1',
				'time_from.*' => 'required',
				'bookable_status' => 'required|array|min:1',
				'bookable_status.*' => 'in:Open,Only by Phone,Closed',
			];

			if($action == 'store') {
				$rules['date_range'] = 'required';
				// $rules['date'] = [
				// 	'bail',
				// 	'required',
				// 	'date',
				// 	Rule::unique('tour_slot_dates')->where(function($qry) use($t) {
				// 		return $qry->where('tour_package_id', $t->input('tour_package'));
				// 	})
				// ];
			} else {// repeat
				$rules['date_from'] = 'required|date';
				$rules['date_to'] = 'required|date';
			}
		} else { // edit-update
			$rules['date'] = [
				'bail',
				'required',
				'date',
				Rule::unique('tour_slot_dates')->where(function($qry) use($t) {
					return $qry->where('id', '!=', $t->route()->parameter('slot'))
						->where('tour_package_id', $t->input('tour_package'));
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
			'date.unique' => 'Tour slot is already added for the date.',
			'date_range.required' => '"Date Range" is required.',
			'time_from.required' => 'At least one time slot is required.',
			'date_from.required' => '"From Date" is required.',
		];
	}
}
