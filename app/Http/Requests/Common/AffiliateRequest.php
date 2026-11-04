<?php

namespace App\Http\Requests\Common;

use App\Models\Customer;
use App\Utils\BokunUtils;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class AffiliateRequest extends FormRequest
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
			'phone_number' => 'max:30',
			'email' => 'required|email|max:50',
			'status' => 'required|in:Active,Inactive',
			'department_id' => 'required',
		];

		$tourOperator = tourOperator();
		$tourOpId = $tourOperator->id;

		if(strpos(Route::currentRouteName(), 'tour-operator.affiliate.store') !== false) {
			$rules['name'] = [
				'max:80',
				Rule::unique('customers')->where(function($qry) use($tourOpId) {
					return $qry->where('tour_operator_id', $tourOpId)
						->where('is_affiliate', 'Yes')
						->where('deleted_at', null);
				})
			];

			$rules['password'] = 'required|min:8';
			$rules['role'] = 'required';

			if ($this->has('is_ota')) {
				$rules['ota_affiliate_percentage'] = 'required|numeric|gt:0';

				// Ensure no existing OTA affiliate already owns the same generated slug.
				// "Get Your Guide" and "getyourguide" both produce slug 'getyourguide' —
				// allowing both would silently break whichever is mapped second in the OCTO config.
				$rules['name'][] = function ($attribute, $value, $fail) {
					$slug = BokunUtils::toResellerSlug($value);
					if (Customer::where('ota_id', $slug)->exists()) {
						$fail("An OTA affiliate with the identifier \"{$slug}\" already exists. Choose a name that produces a different slug.");
					}
				};
			} else {
				$rules['affiliate_processing_percentage'] = 'required|numeric|gt:0';
				$rules['stripe_affiliate_percentage'] = 'required|numeric|gt:0';
			}
		} else { // edit-update
			$t = $this;

			$rules['name'] = [
				'max:80',
				Rule::unique('customers')->where(function($qry) use($t, $tourOpId) {
					return $qry->where('id', '!=', $t->route()->parameter('affiliate'))
						->where('tour_operator_id', $tourOpId)
						->where('is_affiliate', 'Yes')
						->where('deleted_at', null);
				})
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
