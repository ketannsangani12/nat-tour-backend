<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

	protected $fillable = [
		'purpose',
		'user_id',
		'user_type',
		'csrf_token',
	];

	protected $hidden = [
		'csrf_token',
	];
}
