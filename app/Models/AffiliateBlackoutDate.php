<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AffiliateBlackoutDate extends Model
{
    
    public function setFromDateAttribute($value)
    {
        $this->attributes['from_date'] = Carbon::createFromFormat('m/d/Y', $value)->format('Y-m-d');
    }

    public function setToDateAttribute($value)
    {
        $this->attributes['to_date'] = Carbon::createFromFormat('m/d/Y', $value)->format('Y-m-d');
    }
    
    public function getFromDateAttribute($value)
    {
        return Carbon::parse($value)->format('m/d/Y');
    }

    public function getToDateAttribute($value)
    {
        return Carbon::parse($value)->format('m/d/Y');
    }

}
