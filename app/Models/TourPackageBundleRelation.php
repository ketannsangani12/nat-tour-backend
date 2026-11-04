<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageBundleRelation extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'tour_package_bundle_relations';

	protected $fillable = [
		'parent_id',
		'tour_package_id',
		'package_name',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
	];
}
