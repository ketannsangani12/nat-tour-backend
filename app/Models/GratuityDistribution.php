<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GratuityDistribution extends Model
{
	use HasFactory;

	protected $table = 'gratuity_distributions';

	protected $fillable = [
		'booking_id',
		'gratuity_id',
        'guide_id',
		'payroll_schedule_id',
		'amount',
        'is_transferred',
		'status',
        'created_by',
		'created_by_user_type'
	];
}
