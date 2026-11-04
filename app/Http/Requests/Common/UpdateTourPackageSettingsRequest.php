<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourPackageSettingsRequest extends FormRequest
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
            'bokun_product_code' => [
                'nullable',
                // must contain at least 1 letter AND 1 number, only alphanumeric
                'regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]+$/',
                Rule::unique('tour_packages', 'bokun_product_code')
                    ->ignore($this->route('id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bokun_product_code.regex' =>
                'Bokun Product Code must contain both letters and numbers, with no spaces or special characters.',
            'bokun_product_code.unique' =>
                'Bokun Product Code already exists.',
        ];
    }

    /**
     * IMPORTANT: return SAME response as your catch()
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            sendJsonErrMsg($validator->errors()->first())
        );
    }
}