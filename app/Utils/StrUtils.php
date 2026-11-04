<?php

namespace App\Utils;

use Exception;

class StrUtils
{
    /**
     * return an IN ( ) query string 
     * 
     * @param string $term query string of terms
     * @param string $sep query list seperator one|two|three . 
     * 
     * @return string "one", "two", "three"
     */
    public static function dbInFilter($term, $sep = "|")
    {
        //explode("+", $status);
        $strs = explode($sep, $term);
        $outp = [];
        foreach ($strs as $s) {
            array_push($outp, '"' . $s . '"');
        }

        return implode(",", $outp);
    }


    /**
     * helper function to format values into float
     * 
     * @param string|int|float $num the value to parse into float
     * @param string|int|float $alt default value to use if any
     * 
     * @return float
     */
    public static function parseFloat($num, $alt = 0)
    {
        $amt = 0;
        try {
            $amt = floatval(preg_replace('/[^0-9\.]/i', '', strval($num)));
        } catch (Exception $e) {
            $amt = 0;
        }

        if ($amt <= 0 || !is_float($amt)) {
            $amt = floatval($alt);
        }

        return $amt;
    }


    /**
     * helper function to format values into float
     * 
     * @param string|int|float $num the value to parse into float
     * @param string|int|float $alt default value to use if any
     * 
     * @return float
     */
    public static function parseInt($num, $alt = 0)
    {
        $amt = 0;
        try {
            $amt = intval(preg_replace('/[^0-9]/i', '', strval($num)));
        } catch (Exception $e) {
            $amt = 0;
        }

        if ($amt <= 0 || !is_int($amt)) {
            $amt = intval($alt);
        }

        return $amt;
    }


    /**
     * Remove Special Chars from phone number: 
     * "+1 (222) 333-4444" -> "+12223334444"
     * @param string $phone  phone number with special chars
     */
    public static function parseIntPhoneNumber($phone)
    {
        return preg_replace('/[^0-9\+]/i', '', strval($phone));
    }
}
