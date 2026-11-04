<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EvidenceSubmissionRequest extends FormRequest
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
            'evidence_type' => [
                'required',
                'string',
                'in:receipt,customer_communication,service_documentation,shipping_documentation,duplicate_charge_documentation,refund_policy,cancellation_policy,customer_signature,uncategorized_file'
            ],
            'evidence_text' => 'nullable|string|max:5000',
            'evidence_file' => [
                'nullable',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,jpg,jpeg,png,doc,docx'
            ],
            'description' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'evidence_type' => 'evidence type',
            'evidence_text' => 'evidence text',
            'evidence_file' => 'evidence file',
            'description' => 'description'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'evidence_type.required' => 'Please select an evidence type.',
            'evidence_type.in' => 'Please select a valid evidence type.',
            'evidence_file.max' => 'The evidence file must not be larger than 10MB.',
            'evidence_file.mimes' => 'The evidence file must be a PDF, JPG, PNG, DOC, or DOCX file.',
            'evidence_text.max' => 'The evidence text must not exceed 5000 characters.',
            'description.max' => 'The description must not exceed 1000 characters.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one form of evidence is provided
            if (!$this->filled('evidence_text') && !$this->hasFile('evidence_file')) {
                $validator->errors()->add('evidence', 'Please provide either evidence text or upload an evidence file.');
            }
        });
    }
}