<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;

class BookingPackageRedeemStatusRequest extends FormRequest
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
            'booking_id' => 'required|numeric',
            'parent_package_id' => 'required|numeric',
            // 'status' => 'required|in:Active,Inactive',
            'package_id' => 'numeric'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'booking_id.required' => __('validation.booking_id_required'),
            'parent_package_id.required' => __('validation.parent_package_id_required'),
            // 'status.required' => __('validation.status_required'),
            'package_id.numeric' => __('validation.package_id_numeric')
        ];
    }
}