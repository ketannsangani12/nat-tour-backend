<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AddonField extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addon_fields';

    protected $fillable = [
        'form_id',
        'parent_field_id',
        'category_id',
        'package_id',
        'label',
        'type',
        'description',
        'required',
        'unit_type',
        'visibility',
        'status',
        'additional_fee',
        'display_once',
        'price',
        'min',
        'max',
        'sort_order'
    ];

    /**
     * A field belongs to a form.
     */
    public function form()
    {
        return $this->belongsTo(AddonForm::class, 'form_id');
    }

    // ✅ Parent field reference
    public function parent()
    {
        return $this->belongsTo(AddonField::class, 'parent_field_id');
    }

    public function category()
    {
        return $this->belongsTo(AddonCategory::class, 'category_id');
    }

    public function linkedPackage()
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    /**
     * A field has many options.
     */
    public function options()
    {
        return $this->hasMany(AddonFieldOption::class, 'field_id')->orderBy('sort_order', 'asc');
    }

    /**
     * A field has many rules.
     */
    public function rules()
    {
        return $this->hasMany(AddonFieldRule::class, 'field_id')->orderBy('id', 'asc');
    }

    // // children via parent_field_id (hierarchy)
    public function children()
    {
        return $this->hasMany(AddonField::class, 'parent_field_id')->orderBy('sort_order', 'asc');
    }

    protected static function booted()
    {
        static::deleting(function ($field) {
            DB::transaction(function () use ($field) {

                // 1️⃣ Recursively delete child fields
                foreach ($field->children as $child) {
                    $child->delete();
                }

                // 2️⃣ Soft delete options
                $field->options()->each(fn($opt) => $opt->delete());

                // 3️⃣ Soft delete rules (AddonFieldRule handles its children)
                foreach ($field->rules as $rule) {
                    $rule->delete();
                }

                // 4️⃣ Soft delete pivot entries (addon_field_rule_children)
                DB::table('addon_field_rule_children')
                    ->where('child_field_id', $field->id)
                    ->update(['deleted_at' => now()]);
            });
        });
    }
}