<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DisputeStatusUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('view-payment-dispute');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:warning_needs_response,under_review,charge_refunded,lost,won'
            ],
            'notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'dispute status',
            'notes' => 'notes',
            'internal_notes' => 'internal notes'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Please select a dispute status.',
            'status.in' => 'Please select a valid dispute status.',
            'notes.max' => 'The notes must not exceed 1000 characters.',
            'internal_notes.max' => 'The internal notes must not exceed 1000 characters.'
        ];
    }
}