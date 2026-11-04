<?php

namespace App\Http\Requests\API\GetYourGuide;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class PackageReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.productId' => 'required|string',
            'data.dateTime' => 'required|date_format:Y-m-d\TH:i:sP',
            'data.bookingItems' => 'required|array|min:1',
            'data.bookingItems.*.category' => 'required|string',
            'data.bookingItems.*.count' => 'required|integer|min:1',
            'data.gygBookingReference' => 'required|string'
        ];
    }

    /**
     * Customize error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'data.productId.required' => 'Product ID is required.',
            'data.productId.string' => 'Product ID must be a string.',
            'data.dateTime.required' => 'Date and time are required.',
            'data.dateTime.date_format' => 'Date and time must be in ISO 8601 format (Y-m-d\TH:i:sP).',
            'data.bookingItems.required' => 'At least one booking item is required.',
            'data.bookingItems.*.category.required' => "Each traveler's category is required.",
            'data.bookingItems.*.count.required' => "Each traveler's count is required.",
            'data.bookingItems.*.count.integer' => "Traveler count must be a number.",
            'data.gygBookingReference.required' => 'GYG booking reference is required.',
        ];
    }

    /**
     * Handle a invalid/failed json request validation attempt.
     *
     * @param  Validator  $validator
     */
    protected function failedValidation(Validator $validator)
    {
        foreach ($validator->errors()->messages() as $key => $error) {
            throw new HttpResponseException(response()->json([
                'errorCode' => 'VALIDATION_FAILURE',
                'errorMessage' => $error[0],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }
}
