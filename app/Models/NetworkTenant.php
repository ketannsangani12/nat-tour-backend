<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkTenant extends Model
{
    protected $fillable = ['network_id', 'tenant_id', 'status'];

    public function network()
    {
        return $this->belongsTo(Network::class);
    }
}
