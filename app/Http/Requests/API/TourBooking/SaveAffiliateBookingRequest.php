<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class SaveAffiliateBookingRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rules(): array
	{
		return [
			'tour_slot_id' => 'required|numeric',
			'affiliate_id' => 'required|numeric',
			'seats' => 'required|numeric'
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function messages(): array
	{
		return [
			'tour_slot_id.required' => __('validation.tour_slot_id_required'),
			'affiliate_id.required' => __('validation.affiliate_id_required'),
			'seats.required' => __('validation.seats_required'),
		];
	}
}
