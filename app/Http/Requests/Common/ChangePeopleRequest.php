<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class ChangePeopleRequest extends FormRequest
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
		if (request()->is('api/*')) {
			$rules = [
				'slot_time' => 'required|integer|min:1',
				'tourists' => 'required',
				//'tourists.*' => 'integer|min:0',
				'rate_group' => 'required',
				'payment_amount' => 'required',
				'refund_amount' => 'required',
				//'rate_group.*' => 'integer|min:1',
				'discount_percentage' => 'numeric',
				'discount_fixed_money' => 'numeric',
			];
			$rules = $rules + [
					'permit_fee' => 'required',
					//'permit_fee.*' => 'integer|min:0',
					'bookingID' => 'required',
					'tour_operator_id' => 'required',
					'total_tourist' => 'required|integer|min:1'
				];
		} else {
			$rules = [
				'slot_time' => 'required|integer|min:1',
				'tourists' => 'required|array',
				'tourists.*' => 'integer|min:0',
				'rate_group' => 'required|array',
				'rate_group.*' => 'integer|min:1',
				'discount_percentage' => 'numeric',
				'discount_fixed_money' => 'numeric',
			];

			$rules = $rules + [
					//'permit_fee' => 'required|array',
					//'permit_fee.*' => 'integer|min:0',
					'bookingID' => 'required',
					'tour_operator_id' => 'required',
					'total_tourist' => 'required|integer|min:1',
					//'subtt' => 'required|numeric',
					//'fee' => 'required|numeric',
					'validation_check' => 'required|in:true,false'
				];
		}

		// Priced add-on selections carried by the Change People form — pricing itself is always
		// resolved server-side from addon_fields/addon_field_options, never trusted from the client,
		// so only identifiers are validated here.
		$rules = $rules + [
			'addons_added' => 'nullable|array',
			'addons_added.*.field_id' => 'nullable|integer|exists:addon_fields,id',
			'addons_added.*.option_id' => 'nullable|integer|exists:addon_field_options,id',
			'addons_updated' => 'nullable|array',
			'addons_updated.*.db_id' => 'nullable|integer|exists:tour_booking_addon_fields,id',
			'addons_updated.*.field_id' => 'nullable|integer|exists:addon_fields,id',
			'addons_updated.*.option_id' => 'nullable|integer|exists:addon_field_options,id',
			'addons_removed' => 'nullable|array',
			'addons_removed.*.db_id' => 'nullable|integer|exists:tour_booking_addon_fields,id',
		];

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
			'slot_time.required' => 'Please select a time slot.',
			'tourists.required' => 'Please select a time slot and tourists.',
			'tourists.*.required' => 'Please select tourists from at least one age group.',
			'rate_group.required' => 'Please select a time slot and tourists.',
			'rate_group.*.required' => 'Please select tourists from at least one age group.',
		];
	}
}
