<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    protected $fillable = ['name', 'slug', 'frontend_url', 'status'];

    public function tenants()
    {
        return $this->hasMany(NetworkTenant::class);
    }
}
