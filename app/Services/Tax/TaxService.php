<?php

namespace App\Services\Tax;

use Illuminate\Support\Facades\DB;

class TaxService
{
    /**
     * Get tour operator
     * 
     * @return object
     */
    public static function getTaxes(): object
    {
        $taxes = DB::table('taxes AS t')
            ->where('t.status', 'Active')
            ->orderBy('t.name')
            ->get(['t.name', 't.value', 't.value_type']);

        return $taxes;
    }
}
