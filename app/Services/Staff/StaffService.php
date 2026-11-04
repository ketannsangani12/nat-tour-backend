<?php

namespace App\Services\Staff;

use App\Models\Staff;

class StaffService
{
    /**
     * Get tour operator
     * 
     * @return object
     */
    public static function getStaff(): object
    {
        $staff = Staff::where('status', 'Active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name']);

        return $staff;
    }
}
