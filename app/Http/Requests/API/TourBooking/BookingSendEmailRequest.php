<?php

namespace App\Http\Requests\API\TourBooking;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class BookingSendEmailRequest extends FormRequest
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
            'email_subject' => 'required|min:1',
            'email_content' => 'required|min:1'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'email_subject.required' => __('validation.email_subject_required'),
            'email_content.required' => __('validation.email_content_required')
        ];
    }
}
