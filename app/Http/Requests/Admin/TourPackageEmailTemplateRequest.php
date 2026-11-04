<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourPackageEmailTemplateRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $table = 'tour_package_email_templates';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return (session('userType') == 'Admin' || session('userType') == 'Tour Operator');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'subject' => [
                'required',
                'string',
                'max:191'
            ],
            'body' => [
                'required'
            ]
        ];
        if ($this->routeIs('admin.tour-operator.email-template.store')) {
            $rules['email_template_id'] = [
                'required',
                Rule::unique($this->table, 'email_template_id')->where(function ($query) {
                     $query->where('tour_package_id', '=', $this->route()->parameter('tour_package')->id)
                        ->whereNull('deleted_at');
                })
            ];
        } else {
        	$rules['email_template_id'] = [
                'required',
                Rule::unique($this->table, 'email_template_id')->where(function ($query) {
                    return $query->where('id', '!=', $this->route()->parameter('email_template'))
                        ->where('tour_package_id', '=', $this->route()->parameter('tour_package')->id)
                        ->whereNull('deleted_at');
                })
            ];
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'email_template_id.unique' => 'The master email template has already been taken.',
        ];
    }
}
