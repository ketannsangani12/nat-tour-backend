<?php

namespace App\Http\Requests\API\GetYourGuide;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class PackageReservationCancellationRequest extends FormRequest
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
            'data.gygBookingReference' => 'required|string',
            'data.reservationReference' => 'required|string',
        ];
    }

    /**
     * Customize error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'data.gygBookingReference.required' => 'GYG booking reference is required.',
            'data.reservationReference.required' => 'Reservation reference is required.',
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
