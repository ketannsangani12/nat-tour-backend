<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddonFieldRuleChildren extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addon_field_rule_children';

    protected $fillable = [
        'rule_id',
        'child_field_id'
    ];

    /**
     * A child field belongs to a rule.
     */
    public function rule()
    {
        return $this->belongsTo(AddonFieldRule::class, 'rule_id');
    }
}
