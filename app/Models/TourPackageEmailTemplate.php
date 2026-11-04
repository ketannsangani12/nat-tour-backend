<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourPackageEmailTemplate extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditTrait;

    /**
     * @var string
     */
    protected $table = 'tour_package_email_templates';

    /**
     * @var string[]
     */
    protected $fillable = [
        'tour_package_id',
        'email_template_id',
        'subject',
        'body',
        'params',
        'created_by',
        'created_from'
    ];

    /**
     * @var string[]
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($emailTemplate) {
            $emailTemplate->created_by = auth()->user()->name;
            $emailTemplate->created_from = 'backend';
        });

        static::updating(function ($emailTemplate) {
            $emailTemplate->created_by = auth()->user()->name;
            $emailTemplate->created_from = 'backend';
        });
    }

    /**
     * Scope to only include email templates of a given alias.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $alias
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAlias($query, $alias)
    {
        return $query->whereHas('emailTemplate', function ($query) use ($alias) {
            $query->where('alias', $alias);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
    | These are the relationships defined for this model
    |
    */

    /**
     * @return BelongsTo
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * @return BelongsTo
     */
    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
