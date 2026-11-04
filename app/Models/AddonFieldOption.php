<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddonFieldOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addon_field_options';

    protected $fillable = [
        'field_id',
        'label',
        'value',
        'additional_fee',
        'price',
        'sort_order'
    ];

    /**
     * An option belongs to a field.
     */
    public function field()
    {
        return $this->belongsTo(AddonField::class, 'field_id');
    }
}
