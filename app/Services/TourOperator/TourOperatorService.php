<?php

namespace App\Services\TourOperator;

class TourOperatorService
{
    /**
     * Get tour operator
     * 
     * @return object
     */
    public static function getTourOperator(): ?object
    {
        $tourOperator = \App\Models\TourOperator::first();

        return $tourOperator;
    }
}
