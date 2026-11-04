<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddonFieldRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addon_field_rules';

    protected $fillable = [
        'field_id',
        'option_id',
        'operator',
        'value'
    ];

    /**
     * A rule belongs to a field.
     */
    public function field()
    {
        return $this->belongsTo(AddonField::class, 'field_id');
    }

    /**
     * A rule can have many child fields.
     */
    // pivot to child fields
    public function children()
    {
        return $this->belongsToMany(
            AddonField::class,
            'addon_field_rule_children',
            'rule_id',
            'child_field_id'
        )->withTimestamps();
    }

    protected static function booted()
    {
        static::deleting(function ($rule) {
            // Soft delete all rule children
            $rule->children()->each(fn($child) => $child->delete());
        });
    }
}
