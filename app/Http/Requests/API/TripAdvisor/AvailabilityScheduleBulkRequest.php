<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityScheduleBulkRequest extends FormRequest
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
            "productCodes" => "required|array|min:1",
            "productCodes.*" => "required|string|distinct|min:1",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'productCodes.required' => 'Atleast one product code is required.',
            'productCodes.*' => 'Product codes must be in array.'
        ];
    }
}