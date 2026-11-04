<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\NonExponential;

class PermissionRequest extends FormRequest
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
    * @return array
    */
    public function rules()
    {
        $rules = [
    
        ];


        if($this->routeIs(routePrefix() . '.permission.store'))
            $rules['display_name'] = 'bail|required|max:30|unique:permissions';
        else {
            $rules['display_name'] = [
                'bail',
                'required',
                'max:30',
                Rule::unique('permissions')->ignore($this->route()->parameter('permission'))
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
            'display_name.unique' => 'This permission is already added.',
        ];
    }
}
