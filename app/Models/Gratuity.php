<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gratuity extends Model
{
	use HasFactory;

	protected $table = 'gratuities';

	protected $fillable = [
		'gratuity_name',
        'booking_id',
		'customer_id',
		'gratuity_setting_id',
		'gratuity_type',
		'amount',
		'status'
	];
}
