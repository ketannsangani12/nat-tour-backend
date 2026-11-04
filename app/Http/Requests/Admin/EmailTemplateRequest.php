<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailTemplateRequest extends FormRequest
{
	/**
	 * @var string
	 */
	protected $table = 'email_templates';

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	/*public function authorize(): bool
	{
		return (session('userType') == 'Admin');
	}*/

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules(): array
	{
		$rules = [
			'alias' => [
				'required',
				'string'
			],
			'class' => [
				'required',
				'string'
			],
			'subject' => [
				'required',
				'string',
				'max:191'
			],
			'body' => [
				'required'
			]
		];


		if ($this->routeIs(routePrefix() . '.master-email-template.store')) {
			$rules['name'] = [
				'required',
				'string',
				Rule::unique($this->table, 'name')
			];
		} else {
			$rules['name'] = [
				'required',
				'string',
				Rule::unique($this->table, 'name')->where(function ($query) {
					return $query->where('id', '!=', $this->route()->parameter('master_email_template'))
						->whereNull('deleted_at');
				})
			];
		}

		return $rules;
	}
}
