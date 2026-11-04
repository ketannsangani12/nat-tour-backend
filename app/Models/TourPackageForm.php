<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TourPackage;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageForm extends Model implements Auditable
{
    use HasFactory, AuditTrait;

    protected $table = 'tour_package_form';

	protected $fillable = [
		'form_name',
		'form_package_id',
		'form_fields'
	];

	/**
	 * Get the tour package data using relationship.
	 * @return BelongsTo
	 */
	public function tourPackage(): BelongsTo
	{
		return $this->belongsTo(TourPackage::class, 'form_package_id', 'id', BelongsTo::class);
	}

}
