<?php

namespace App\Http\Requests\API\GetYourGuide;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class NotificationRequest extends FormRequest
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
            'data.notificationType' => 'required|string',
            'data.description' => 'required|string',
            'data.supplierName' => 'required|string',
            'data.integrationName' => 'required|string',
            'data.dateTime' => 'required|date_format:Y-m-d\TH:i:sP',
            'data.productDetails' => 'required|array|min:1',
            'data.productDetails.*.productId' => 'required|string',
            'data.productDetails.*.gygTourOptionId' => 'required|string',
            'data.notificationDetails' => 'required|array'
        ];
    }

    /**
     * Customize error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'data.notificationType.required' => 'Notification type is required.',
            'data.description.required' => 'Description is required.',
            'data.supplierName.required' => 'Supplier name is required.',
            'data.integrationName.required' => 'Integration name is required.',
            'data.dateTime.required' => 'Date and time are required.',
            'data.dateTime.date_format' => 'Date and time must be in ISO 8601 format (Y-m-d\TH:i:sP).',
            'data.productDetails.required' => 'At least one product detail is required.',
            'data.productDetails.*.productId.required' => "Each product detail's product ID is required.",
            'data.productDetails.*.gygTourOptionId.required' => "Each product detail's GYG tour option is required.",
            'data.notificationDetails.required' => 'Notification detail is required.'
        ];
    }

    /**
     * Handle a invalid/failed json request validation attempt.
     *
     * @return void
     *
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
