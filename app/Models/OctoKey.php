<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $tenant_id
 * @property string $reseller
 * @property string $api_key_hash
 * @property string $key_prefix
 * @property bool   $is_active
 */
class OctoKey extends Model
{
    protected $connection = 'mysql'; // always central DB, never switched to tenant

    protected $table = 'octo_keys';

    protected $fillable = [
        'tenant_id',
        'reseller',
        'api_key_hash',
        'key_prefix',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Returns the key masked for UI display, e.g. native-american-tour********
     */
    public function getMaskedKeyAttribute(): string
    {
        return $this->key_prefix . str_repeat('*', 8);
    }
}
