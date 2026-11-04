<?php

namespace App\Http\Requests\API\TourSlot;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class TourSlotRequest extends FormRequest
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
            'date' => 'required|integer',
            'tourPackageId' => 'required|integer',
            'getRateGroups' => 'required|integer',
            'affiliateId' => 'integer',
            'tourOperatorId' => 'sometimes'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'date.required' => __('validation.date_required'),
            'tourPackageId.required' => __('validation.tour_package_id_required'),
            'getRateGroups.required' => __('validation.getRateGroups_required'),
            'affiliateId.integer' => __('validation.affiliateId_required')
        ];
    }
}
