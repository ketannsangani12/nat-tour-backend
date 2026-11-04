<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Setting extends Model implements Auditable
{
	use HasFactory, AuditTrait;

	protected $table = 'settings';

	protected $fillable = [
		'key',
		'value',
		'order_number',
		'comment',
		'input_type',
		'created_at',
		'updated_at',
		'updated_by'
	];
}
