<?php
namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\DB;
use DateTime;
use DateInterval;

class CalendarWeekFilterUtils {

    /**
     * Return the week number for each month by date
     * @param string $date
     * @param string $rollover
     * @return integer $flag
     */
    public static function getWeeks($date, $rollover, $flag)
    {
        $cut = substr($date, 0, 8);
        $dayLength = 86400;
        $timestamp = strtotime($date);
        $firstDay = strtotime($cut . "00");
        $elapsed = ($timestamp - $firstDay) / $dayLength;
        $weeks = 1;

        for ($i = 1; $i <= $elapsed; $i++) {
            $dayFind = $cut . (strlen($i) < 2 ? '0' . $i : $i);
            $dayTimestamp = strtotime($dayFind);
            $dayName = strtolower(date("l", $dayTimestamp));

            if ($dayName === strtolower($rollover)) {
                $weeks++;
            }
        }

		if($flag == 0) {
			$weeks = 1;
		}
		
        return $weeks;
    }

    /**
     * Return date list for each week by year and month
     * @param integer $month
     * @param string $year
     */
    public static function weekOfMonth($month, $year)
	{
		$dates = [];
		$week = 1;
		$date = new DateTime("$year-$month-01");
		$days = (int)$date->format('t'); // total number of days in the month

		$oneDay = new DateInterval('P1D');

		for ($day = 1; $day <= $days; $day++) {
			$dates[$week][]= $date->format('Y-m-d');

			$dayOfWeek = $date->format('l');
			if ($dayOfWeek === 'Saturday') {
				$week++;
			}

			$date->add($oneDay);
		}

		return $dates;
	}

    /**
     * Return first and last date for a week
     * @param array $weekDatesArray
     * @param integer $weekNumber
     */
    public static function getFirstToLastDateOfWeek($weekDatesArray, $weekNumber)
	{
		foreach ($weekDatesArray as $key => $weekDate) {
            if($key == $weekNumber) {
                $weeklyDates = $weekDate;
            }
        }
        
        foreach ($weeklyDates as $key => $week) {
            $explodeDate = explode('-', $week);
            $weekDates[] = (int)$explodeDate[2];
        }

        $weekStartDate = reset($weeklyDates);
        $weekEndDate = end($weeklyDates);

		return [
            'weekDates' => $weekDates,
            'weekStartDate' => $weekStartDate,
            'weekEndDate' => $weekEndDate
        ];
	}
}