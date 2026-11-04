<?php

namespace App\Http\Requests\API\User;

use App\Http\Requests\API\Core\FormRequest;

class AuthRequest extends FormRequest
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
            'email' => [
                'required',
                'email',
                'max:50',
            ],
            'password' => [
                'required',
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
            'email.required' => __('validation.email_required'),
            'email.email' => __('validation.email_valid'),
            'email.max' => __('validation.email_long'),
            'password.required' => __('validation.password_required'),
            'password.max' => __('validation.password_long'),
        ];
    }
}
