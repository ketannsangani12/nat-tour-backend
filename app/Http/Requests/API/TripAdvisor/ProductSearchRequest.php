<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class ProductSearchRequest extends FormRequest
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
            'filtering.destination' => 'required',
            'filtering.tags' => 'nullable|array',
            'currency' => 'required'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'filtering.destination.required' => 'Destination must be required.',
            'filtering.tags.array' => 'Tags must be an array.',
            'currency.required' => 'Currency must be required.'
        ];
    }
}
