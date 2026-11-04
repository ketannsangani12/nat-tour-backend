<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NonExponential implements Rule
{
	/**
	 * Determine if the validation rule passes.
	 *
	 * @param  string  $attribute
	 * @param  mixed  $value
	 * @return bool
	 */
	public function passes($attribute, $value)
	{
		return stripos($value, 'e') === false;
	}

	/**
	 * Get the validation error message.
	 *
	 * @return string
	 */
	public function message()
	{
		return 'Exponential number is not allowed.';
	}
}
