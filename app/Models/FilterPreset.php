<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilterPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'name',
        'report_type',
        'filters'
    ];

    protected $casts = [
        'filters' => 'array'
    ];

    /**
     * Scope for current user's presets
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @param string $userType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userId, $userType)
    {
        return $query->where('user_id', $userId)
                     ->where('user_type', $userType);
    }

    /**
     * Scope for specific report type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $reportType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForReport($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}
