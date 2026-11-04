<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class TourPackageRequest extends FormRequest
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
			'hours' => 'required|numeric|min:0|max:255',
			'minutes' => 'required|numeric|min:0|max:59',
		];

		$tourOpId = auth()->user()->tour_operator_id;

		if (strpos(Route::currentRouteName(), 'tour-operator.package.store') !== false) {
			$rules += [
				'name' => [
					'bail',
					'required',
					'max:100',
					Rule::unique('tour_packages')->where(function($qry) use($tourOpId) {
						return $qry->where('tour_operator_id', $tourOpId);
					})
				],
			];
		} else { // edit-update
			$t = $this;

			$rules += [
				'name' => [
					'bail',
					'required',
					'max:100',
					Rule::unique('tour_packages')->where(function($qry) use($t, $tourOpId) {
						return $qry->where('id', '!=', $t->route()->parameter('id'))
							->where('tour_operator_id', $tourOpId);
					})
				],
			];
		}

		return $rules;
	}
}
