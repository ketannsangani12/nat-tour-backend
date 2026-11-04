<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class RoleType extends Model implements Auditable
{
    use HasFactory, AuditTrait, SoftDeletes;

    protected $table = 'role_types';

    protected $fillable = [
        'name',
        'display_name',
        'tip_distribution_percentage',
        'created_at',
        'updated_at',
    ];
}
