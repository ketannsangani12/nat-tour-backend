<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class GetAttractionsRequest extends FormRequest
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
            'destId' => 'nullable|integer'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
        ];
    }
}