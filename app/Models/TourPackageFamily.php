<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageFamily extends Model implements Auditable
{
	use HasFactory, AuditTrait;
	protected $table = "tour_package_families";

	protected $fillable = [
		'tour_operator_id',
		'parent_package_id',
		'tour_package_id',
		'time_from',
		'is_parent',
		'group_index',
		'relation_year',
	];

	/**
	 * Get the tour slot date data using relationship.
	 * 
	 * @return BelongsTo
	 */
	public function tourPackage(): BelongsTo
	{
		return $this->belongsTo(TourPackage::class, 'tour_package_id', 'id', BelongsTo::class);
	}
}
