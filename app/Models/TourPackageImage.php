<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageImage extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $fillable = [
		'tour_package_id',
		'image',
		'is_cover'
	];
}
