<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GratuityPackageAssignments extends Model
{
	use HasFactory;

	protected $table = 'gratuity_package_assignments';

	protected $fillable = [
		'gratuity_setting_id',
		'package_id'
	];
}
