<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class GroupPeopleNewBookingRequest extends FormRequest
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
            'tour_package_id'  => 'required|integer',
            'tour_operator_id' => 'required|integer',
            'affiliateId'      => 'required|integer',
            'date'             => 'required|date_format:Y-m-d'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'tour_package_id.required' => __('validation.tour_package_id_required'),
            'tour_package_id.integer' => __('validation.tour_package_id_integer'),
            'tour_operator_id.required' => __('validation.tour_operator_id_required'),
            'tour_operator_id.integer' => __('validation.tour_operator_id_integer'),
            'affiliateId.required' => __('validation.affiliateId_required'),
            'affiliateId.integer' => __('validation.affiliateId_integer'),
            'date.required' => __('validation.date_required'),
            'date.date_format' => __('validation.date_date_format'),
        ];
    }
}
