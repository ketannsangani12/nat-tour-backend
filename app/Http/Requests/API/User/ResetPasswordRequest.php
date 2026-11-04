<?php

namespace App\Http\Requests\API\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $table;

    /**
     * Get the table name of the user model.
     *
     * @return string
     */
    public function __construct()
    {
        $this->table = 'customers';
    }

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
            'token' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
                'max:50',
            ],
            'password' => [
                'required',
                'min:8',
                'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[@!$#%]).*$/',
                'max:50',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'token.required' => __('validation.token_required'),
            'email.required' => __('validation.email_required'),
            'email.email' => __('validation.email_valid'),
            'email.max' => __('validation.email_long'),
            'password.required' => __('validation.password_required'),
            'password.min' => __('validation.password_min'),
            'password.regex' => __('validation.password_format'),
            'password.confirmed' => __('validation.password_confirmed'),
            'password.max' => __('validation.password_long'),
        ];
    }
}
