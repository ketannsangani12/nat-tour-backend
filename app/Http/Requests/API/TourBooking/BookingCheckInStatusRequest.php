<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class BookingCheckInStatusRequest extends FormRequest
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
            'check_in_status' => 'required|in:N,1,2,A',
            'tpr_id' => 'numeric',
            'index' => 'numeric',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'booking_id.required' => __('validation.booking_id_required'),
            'check_in_status.required' => __('validation.check_in_status_required'),
            'tpr_id.numeric' => __('validation.tpr_id_numeric'),
            'index.numeric' => __('validation.index_numeric')
        ];
    }
}
