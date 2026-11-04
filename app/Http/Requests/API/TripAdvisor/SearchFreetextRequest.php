<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class SearchFreetextRequest extends FormRequest
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
            'searchTerm' => 'required',
            'currency' => 'required',
            'searchTypes' => 'array|min:1',
            'searchTypes.*.searchType' => 'required|string'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'searchTerm.required' => 'Search for any destination, attraction or product.',
            'currency.required' => 'Currency must be required.'
        ];
    }
}