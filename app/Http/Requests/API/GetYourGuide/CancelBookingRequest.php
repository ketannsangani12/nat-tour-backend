<?php

namespace App\Http\Requests\API\GetYourGuide;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class CancelBookingRequest extends FormRequest
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
            'data.bookingReference' => 'required|string',
            'data.gygBookingReference' => 'required|string',
            'data.productId' => 'required|string',
        ];
    }

    /**
     * Customize error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'data.bookingReference.required' => 'Booking reference is required.',
            'data.gygBookingReference.required' => 'GYG booking reference is required.',
            'data.productId.required' => 'Product ID is required.',
            'data.productId.string' => 'Product ID must be a string.',
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
