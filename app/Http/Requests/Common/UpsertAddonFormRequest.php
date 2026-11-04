<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAddonFormRequest extends FormRequest
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
            'id' => 'nullable|integer|exists:addon_forms,id',
            'tour_package_id' => 'required|integer|exists:tour_packages,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
            'fields' => 'present|array',

            // top-level & recursive field rules (children are validated in controller recursion)
            // 'fields.*.id' => 'nullable|integer|exists:addon_fields,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,textbox,number,checkbox,radio,dropdown',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.unit_type' => 'nullable|string',
            'fields.*.category_id' => 'nullable|integer|exists:addon_categories,id',
            'fields.*.package_id' => 'nullable|integer|exists:tour_packages,id',
            'fields.*.visibility' => 'nullable|string|in:both,frontend,backend',
            'fields.*.status' => 'nullable|string|in:Active,Inactive',
            'fields.*.additional_fee' => 'nullable|boolean',
            'fields.*.display_once' => 'nullable|boolean',
            'fields.*.price' => 'nullable|numeric|min:0',
            'fields.*.min' => 'nullable|integer|min:0',
            'fields.*.max' => 'nullable|integer|min:0',
            'fields.*.sort_order' => 'nullable|integer|min:0',

            'fields.*.options' => 'nullable|array',
            'fields.*.options.*.id' => 'nullable|integer|exists:addon_field_options,id',
            'fields.*.options.*.label' => 'required_with:fields.*.options|string|max:255',
            'fields.*.options.*.value' => 'required_with:fields.*.options|string|max:255',
            'fields.*.options.*.sort_order' => 'nullable|integer|min:0',

            'fields.*.rules' => 'nullable|array',
            'fields.*.rules.*.id' => 'nullable|integer|exists:addon_field_rules,id',
            'fields.*.rules.*.operator' => 'required_with:fields.*.rules|string|max:50',
            'fields.*.rules.*.option_id' => 'nullable|integer|exists:addon_field_options,id',
            'fields.*.rules.*.value' => 'nullable|string|max:255',

            'fields.*.rules.*.children' => 'nullable|array',
            // children will be validated because they follow same structure when processed recursively
        ];
    }

    public function messages()
    {
        return [
            'fields.required' => 'At least one field is required.',
            'fields.*.label.required' => 'Field label is required.',
            'fields.*.type.required' => 'Field type is required.',
            'fields.*.options.*.label.required_with' => 'Option label is required.',
            'fields.*.options.*.value.required_with' => 'Option value is required.',
            'fields.*.rules.*.operator.required_with' => 'Rule operator is required.',
        ];
    }
}
