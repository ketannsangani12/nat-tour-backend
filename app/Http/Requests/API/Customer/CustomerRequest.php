<?php

namespace App\Http\Requests\API\Customer;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
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
            'name' => 'max:80',
            'email' => 'required|email|max:50',
            'phone_number' => 'max:30'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.name_required'),
            'email.required' => __('validation.email_required'),
            'phone_number.max' => __('validation.phone_number_max')
        ];
    }
}
