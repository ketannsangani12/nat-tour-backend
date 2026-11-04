<?php

namespace App\Services\Booking;

class PrintService
{
    /**
     * Execute the history service to get the details.
     * 
     * @param mixed $historyDet
     *
     * @return mixed
     */
    public static function data($bookingM)
    {
        if (isset($bookingM->tour_op_logo_print) && $bookingM->tour_op_logo_print != '') {
            $printLogo = tourOperatorPrintLogo($bookingM->tenant_id, $bookingM->tour_op_logo_print);
        } else if (isset($bookingM->tour_op_logo) && $bookingM->tour_op_logo != '') {
            $printLogo = tourOperatorPrintLogo($bookingM->tenant_id, $bookingM->tour_op_logo);
        } else {
            $printLogo = asset('images/no-photo.png');
        }

        $printInfo = [
            'tour_operator' => isset($bookingM->tour_operator) ? $bookingM->tour_operator : "",
            'tour_op_email' => isset($bookingM->tour_op_email) ? $bookingM->tour_op_email : "",
            'tour_op_phone' => isset($bookingM->tour_op_phone) ? $bookingM->tour_op_phone : "",
            'tour_op_website' => isset($bookingM->tour_op_website) ? $bookingM->tour_op_website : "",
            'logo' => $printLogo,
            'payment_via' => $bookingM->via,
        ];

        return $printInfo;
    }
}
