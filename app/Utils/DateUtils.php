<?php

namespace App\Utils;

class DateUtils
{
    /**
     * return default start and end date from date param string
     * @param string $startDate
     * @param string $endDate
     * @return array [ start_date: string, end_date: string]
     */
    public static function startEndDateDefaults(string $startDate, string $endDate)
    {
        if ($startDate == "") {
            $sDate = date('Y-m-d');
        } else {
            $sDate = $startDate . " 00:00:00";
        }

        if ($endDate == "") {
            $eDate = date('Y-m-d');
        } else {
            $eDate = $endDate . " 23:59:59";
        }

        return ["start_date" => $sDate, "end_date" => $eDate];
    }

    public static function daysBetweenDates($fromDate, $toDate)
    {
        $start = strtotime($fromDate);
        $end = strtotime($toDate);
        $days_between = ceil(abs($end - $start) / 86400);

        return $days_between;
    }

    /** 
     * Check if a date is in the past 
     * @param $fromDate
     * @param $toDate 
     * @return mixed
     */
    public static function dateIsInThePast($fromDate, $toDate)
    {
        return ($fromDate < $toDate);
    }
}
