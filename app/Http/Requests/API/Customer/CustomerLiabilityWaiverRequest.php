<?php

namespace App\Http\Requests\API\Customer;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class CustomerLiabilityWaiverRequest extends FormRequest
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
            'name' => 'required|array',
            'age' => 'required|array',
            'age.*' => 'integer',
            'tourist_num' => 'required|array',
            'tourist_num.*' => 'integer',
            'agree' => 'required',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.name_required'),
            'age.required' => __('validation.age_required'),
            'tourist_num.required' => __('validation.tourist_num_required'),
            'agree.required' => __('validation.agree_required')
        ];
    }
}
