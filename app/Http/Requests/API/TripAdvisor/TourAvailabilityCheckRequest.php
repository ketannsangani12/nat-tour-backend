<?php

namespace App\Http\Requests\API\TripAdvisor;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;

class TourAvailabilityCheckRequest extends FormRequest
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
            'productCode' => 'required',
            'travelDate' => 'required|date_format:Y-m-d',
            'currency' => 'required',
            'paxMix' => 'array|min:1',
            'paxMix.*.ageBand' => 'required|string|max:255',
            'paxMix.*.numberOfTravelers' => 'required|integer',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'productCode.required' => 'Product Code must be required.',
            'travelDate.required' => 'Travel Date must be required.',
            'currency.required' => 'Currency must be required.',
            'paxMix.required' => 'At least one age-group passenger is required.',
            'paxMix.array' => 'The pax must be in array.'
        ];
    }
}
