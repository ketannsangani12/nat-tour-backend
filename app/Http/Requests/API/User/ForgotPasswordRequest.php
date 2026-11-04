<?php

namespace App\Http\Requests\API\User;

use App\Http\Requests\API\Core\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function messages(): array
    {
        return [
            'email.required' => __('User::validation.email_required'),
            'email.email' => __('User::validation.email_valid'),
            'email.max' => __('User::validation.email_long'),
        ];
    }
}
