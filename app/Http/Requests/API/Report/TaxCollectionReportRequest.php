<?php

namespace App\Http\Requests\API\Report;

use App\Http\Requests\API\Core\FormRequest;

class TaxCollectionReportRequest extends FormRequest
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
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date',
            'tourPackage' => 'numeric'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'dateFrom.required' => __('validation.from_date_required'),
            'dateTo.required' => __('validation.to_date_required'),
            'tourPackage.required' => __('validation.tour_package_required')
        ];
    }
}
