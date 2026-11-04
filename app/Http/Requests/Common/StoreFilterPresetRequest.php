<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class StoreFilterPresetRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $data = [];

        // Trim the name before validation
        if ($this->has('name')) {
            $data['name'] = trim($this->input('name'));
        }

        // Cast string booleans to actual booleans (jQuery serializes true/false as "true"/"false")
        $filters = $this->input('filters', []);
        if (is_array($filters)) {
            foreach (['exclude_all_affiliates', 'exclude_all_third_party', 'soft_blocks', 'reallocated_funds'] as $field) {
                if (isset($filters[$field])) {
                    $filters[$field] = filter_var($filters[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
            $data['filters'] = $filters;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'report_type' => 'required|string|max:50',
            'filters' => 'required|array',
            'filters.tour_operator' => 'nullable|integer',
            'filters.tour_package' => 'nullable|integer',
            'filters.affiliate' => 'nullable|integer',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date',
            'filters.tour_packages' => 'nullable|array',
            'filters.tour_packages.*' => 'nullable|integer',
            'filters.affiliates' => 'nullable|array',
            'filters.affiliates.*' => 'nullable|string',
            'filters.exclude_all_affiliates' => 'nullable|boolean',
            'filters.third_party_platforms' => 'nullable|array',
            'filters.third_party_platforms.*' => 'nullable|string',
            'filters.exclude_all_third_party' => 'nullable|boolean',
            'filters.payment_types' => 'nullable|array',
            'filters.payment_types.*' => 'nullable|string',
            'filters.view_by' => 'nullable|string|in:tourdate,purchasedate,createddate',
            'filters.booking_status' => 'nullable|string',
            'filters.checkin_statuses' => 'nullable|array',
            'filters.checkin_statuses.*' => 'nullable|string',
            'filters.checkin_status' => 'nullable|string',
            'filters.waiver_status' => 'nullable|string',
            'filters.third_party_platform' => 'nullable|string',
            'filters.amount_min' => 'nullable|numeric',
            'filters.amount_max' => 'nullable|numeric',
            'filters.rate' => 'nullable|integer',
            'filters.permit_fee' => 'nullable|integer',
            'filters.tax' => 'nullable|integer',
            'filters.soft_blocks' => 'nullable|boolean',
            'filters.reallocated_funds' => 'nullable|boolean',
            'filters.charge_types' => 'nullable|array',
            'filters.charge_types.*' => 'nullable|string|in:tours,custom,addons',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'Preset name is required.',
            'name.max' => 'Preset name cannot exceed 100 characters.',
            'report_type.required' => 'Report type is required.',
            'filters.required' => 'Filter data is required.',
            'filters.view_by.in' => 'View by must be either tour date, purchase date or created date.',
        ];
    }
}
