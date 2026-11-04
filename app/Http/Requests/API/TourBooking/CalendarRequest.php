<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class CalendarRequest extends FormRequest
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
        $routeName = Request::route()->getName();

        $rules = [
            'year' => 'sometimes|numeric',
            'month' => 'sometimes|numeric',
            'tourOperatorId' => 'numeric',
        ];

        if ($routeName === 'api.calendar') {
            $rules['year'] = 'required|numeric';
            $rules['month'] = 'required|numeric';
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'yearMonth.required' => __('validation.yearMonth_required'),
            'tourOperatorId.integer' => __('validation.tourOperatorId_integer')
        ];
    }
}
