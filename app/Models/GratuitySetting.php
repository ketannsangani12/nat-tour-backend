<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GratuitySetting extends Model
{
	use HasFactory;

	protected $table = 'gratuity_settings';

	protected $fillable = [
		'gratuity_name',
        'gratuity_type',
		'gratuity_value',
		'pre_gratuity_enabled',
		'post_gratuity_enabled',
		'allow_pre_custom_gratuity',
		'allow_post_custom_gratuity',
		'min_gratuity',
		'max_gratuity',
		'default_gratuity',
		'is_mandatory',
        'service_provider_cut',
        'distribution_type',
        'distribution_rules',
		'gratuity_rules',
        'status',
        'created_by',
		'created_by_user_type'
	];
}
