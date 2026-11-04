<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
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
            'search' => 'sometimes',
            'tour_operator_id' => 'required|numeric',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            // 'tour_operator_id.required' => __('validation.tour_operator_id_required'),
            'tour_operator_id.numeric' => __('validation.tour_operator_id_numeric')
        ];
    }
}
