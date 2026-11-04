<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class AddonCategory extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'addon_categories';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'sort_order',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(AddonField::class, 'category_id');
    }
}
