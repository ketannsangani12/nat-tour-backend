<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeTerminalLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_location_id',
        'display_name',
        'address',
        'status',
    ];

    protected $casts = [
        'address' => 'array',
    ];

    /**
     * Scope to get only active locations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get formatted address string
     */
    public function getFormattedAddressAttribute()
    {
        if (!$this->address) {
            return '';
        }

        $parts = [];
        if (!empty($this->address['line1'])) $parts[] = $this->address['line1'];
        if (!empty($this->address['line2'])) $parts[] = $this->address['line2'];
        if (!empty($this->address['city'])) $parts[] = $this->address['city'];
        if (!empty($this->address['state'])) $parts[] = $this->address['state'];
        if (!empty($this->address['postal_code'])) $parts[] = $this->address['postal_code'];
        if (!empty($this->address['country'])) $parts[] = $this->address['country'];

        return implode(', ', $parts);
    }
}