<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddonForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'addon_forms';

    protected $fillable = [
        'tour_package_id',
        'name',
        'description',
        'status'
    ];

    /**
     * A form has many fields.
     */
    public function fields()
    {
        return $this->hasMany(AddonField::class, 'form_id')->whereNull('parent_field_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Form belongs to a tour package.
     */
    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    protected static function booted()
    {
        static::deleting(function ($form) {
            // Soft delete related fields
            foreach ($form->fields as $field) {
                $field->delete();
            }
        });
    }
}

