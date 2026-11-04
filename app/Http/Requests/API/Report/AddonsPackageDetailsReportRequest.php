<?php

namespace App\Http\Requests\API\Report;

use App\Http\Requests\API\Core\FormRequest;

class AddonsPackageDetailsReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
            'tourPackage.required' => __('validation.tour_package_required'),
        ];
    }
}
