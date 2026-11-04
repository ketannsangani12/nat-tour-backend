<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ReleaseTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Release extends Model implements Auditable
{
    use SoftDeletes, ReleaseTrait, HasFactory, AuditTrait;

    protected $fillable = [
        'title',
        'subtitle',
        'body',
        'image',
        'video',
        'operation_at',
        'operation_by',
        'operation_by_user_type'
    ];
}
