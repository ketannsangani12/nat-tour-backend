<?php

namespace App\Http\Requests\API\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => [
                'required',
                'max:50'
            ],
            'email' => [
                'required',
                'email',
                'max:50',
                Rule::unique($this->table)->where(function ($query) {
                    return $query->whereNotNull('email_verified_at');
                }),
            ],
            'password' => [
                'required',
                'min:8',
                'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[@!$#%]).*$/',
                'max:50',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.first_name_required'),
            'first_name.max' => __('validation.first_name_long'),
            'last_name.required' => __('validation.last_name_required'),
            'last_name.max' => __('validation.last_name_long'),
            'email.required' => __('validation.email_required'),
            'email.email' => __('validation.email_valid'),
            'email.max' => __('validation.email_long'),
            'email.unique' => __('validation.email_unique'),
            'password.required' => __('validation.password_required'),
            'password.min' => __('validation.password_min'),
            'password.regex' => __('validation.password_format'),
            'password.max' => __('validation.password_long'),
        ];
    }
}
