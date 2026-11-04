<?php

namespace App\Http\Requests\API\GetYourGuide;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class CreateBookingRequest extends FormRequest
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
            'data.reservationReference' => 'required|string',
            'data.gygBookingReference' => 'required|string',
            'data.currency' => 'required|string',
            'data.dateTime' => 'required|date_format:Y-m-d\TH:i:sP',
            'data.bookingItems' => 'required|array|min:1',
            'data.bookingItems.*.category' => 'required|string',
            'data.bookingItems.*.count' => 'required|integer|min:1',
            'data.bookingItems.*.retailPrice' => 'required|numeric|min:0',
            'data.travelers' => 'required|array|min:1',
            'data.travelers.*.firstName' => 'required|string|min:1',
            'data.travelers.*.lastName' => 'required|string|min:1',
            'data.travelers.*.email' => 'required|email',
            'data.travelers.*.phoneNumber' => 'required|string|min:10',
            'data.comment' => 'nullable|string',
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
            'data.reservationReference.required' => 'Reservation reference is required.',
            'data.gygBookingReference.required' => 'GYG booking reference is required.',
            'data.currency.required' => 'Currency is required.',
            'data.dateTime.required' => 'Date and time are required.',
            'data.dateTime.date_format' => 'Date and time must be in ISO 8601 format (Y-m-d\TH:i:sP).',
            'data.bookingItems.required' => 'At least one booking item is required.',
            'data.bookingItems.*.category.required' => "Each traveler's category is required.",
            'data.bookingItems.*.count.required' => "Each traveler's count is required.",
            'data.bookingItems.*.count.integer' => "Traveler count must be a number.",
            'data.bookingItems.*.retailPrice.required' => "Retail price is required.",
            'data.bookingItems.*.retailPrice.numeric' => "Retail price must be a valid number.",
            'data.travelers.required' => 'At least one traveler is required.',
            'data.travelers.*.firstName.required' => "Each traveler's first name is required.",
            'data.travelers.*.lastName.required' => "Each traveler's last name is required.",
            'data.travelers.*.email.required' => "Each traveler's email is required.",
            'data.travelers.*.email.email' => "Each traveler's email must be a valid email address.",
            'data.travelers.*.phoneNumber.required' => "Each traveler's phone number is required.",
            'data.travelers.*.phoneNumber.string' => "Phone number must be a string.",
            'data.comment.string' => 'Comment is required.',
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
