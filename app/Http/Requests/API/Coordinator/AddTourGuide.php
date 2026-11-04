<?php

namespace App\Http\Requests\API\Coordinator;

use App\Http\Requests\API\Core\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Validation\Validator;

class AddTourGuide extends FormRequest
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
            'first_name' => 'required',
            'email' => 
                'required|email|unique:tour_operator_staff',
            'password' => [
                'required',
                'min:8',
            ],
            'phone_number' => 'required',
            'total_seat' => 'required',
            'status' => 'required',
        ];
    }
}
