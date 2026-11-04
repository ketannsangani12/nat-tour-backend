<?php
namespace App\Utils;
use Exception;
use Illuminate\Support\Facades\DB;

class ReportUtils {

    public static function dailyReportTotals( $data, $refund, $tourPackageName=null ) {

        $totals = [];

        if(isset($tourPackageName)){
            $totals['tourPackagename'] = $tourPackageName;
        }

        $totals = array_merge($totals, [
            'BookingCount' => $data['total_bookings'],
            'BookingAmount' => $data['ticket_cost_total'],
            'RefundedBookingAmount' => $data['ticket_cost_refund_total'],
            'BookingAmountNet' => $data['ticket_cost_net_total'],
            'ProcessingFeeAmount' => $data['processing_fees_total'],
            'RefundedProcessingFeeAmount' => $data['processing_fees_refund_total'],
            'ProcessingFeeAmountNet' => $data['processing_fees_net_total'],
            'SoftwareFeeAmount' => $data['platform_fees_total'],
            'RefundedSoftwareFeeAmount' => $data['platform_fees_refund_total'],
            'SoftwareFeeAmountNet' => $data['platform_fees_net_total'],
            'DiscountAmount' => $data['discount_total'],
            'PaymentAmount' => $data['payment_total_amount'], 
            //$data['ticket_cost_total'] + $data['processing_fees_total'] + $data['platform_fees_total'],
            'FinalNetAmount' => $data['final_net_total'],
            'RefundAmount' => $refund,
         ]);

         return $totals;
    }


    /**
     * NOTE: this could be a problem if other customers join ater 2022
     * Format fareHarbor block if report is before year 2022 
     * else return zero 
     * @todo update to db settings instead of staic year in the event we onboard clients who have exsiting
     * fareharbor bookings. 
     */
    public static function fareHarborBlockFormat( $data ) {
        if(isset($data)){            
            return $data;        
        }else{
            return [
                'total_bookings' => 0,
                'ticket_cost_total' => 0,
                'ticket_cost_refund_total' => 0,
                'ticket_cost_net_total' => 0,
                'processing_fees_total' => 0,
                'processing_fees_refund_total' => 0,
                'processing_fees_net_total' => 0,
                'platform_fees_total' => 0,
                'platform_fees_refund_total' => 0,
                'platform_fees_net_total' => 0,
                'discount_total' => 0,
                'payment_total_amount' => 0,
                'final_net_total' => 0,
            ];
        }

    }


    /**
     * Sum the refund from the default daily report common indexes 
     * Replaces Code Block: 
     * card       $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total']
     * cash       $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'];
     * checkwr    $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'];
     * invoice    $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'];
     * billable   $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'];
     * tripadvr   $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'];
     * @param array $data output from a daily active report query. 
     * @return int|float
     */
    public static function refundSum( $data ){
        if(!isset($data)){ return 0;}
        return ( $data['ticket_cost_refund_total'] + $data['processing_fees_refund_total'] + $data['platform_fees_refund_total'] );
    }

    /**
     * return an sum of by index from an array of data blocks
     * Replaces this Code block
     * $cardBlock['total_bookings'] + $fhBlock['total_bookings']  + $cashBlock['total_bookings'] + $invoicedBlock['total_bookings'] + $invoicedBillableBlock['total_bookings'] + $checkWireBlock['total_bookings'];
     * $cardBlock['ticket_cost_total'] + $fhBlock['ticket_cost_total'] + $cashBlock['ticket_cost_total'] + $invoicedBillableBlock['ticket_cost_total'] + $invoicedBlock['ticket_cost_total'];
     * $cardBlock['ticket_cost_refund_total'] + $fhBlock['ticket_cost_refund_total'] + $cashBlock['ticket_cost_refund_total'] + $invoicedBlock['ticket_cost_refund_total'] + $invoicedBillableBlock['ticket_cost_refund_total']
     * 
     * NOTE: I found an error: in the ticket_cost_net_total, in the old report  $invoicedBillableBlock was using the value: ticket_cost_refund_total in this update it is using the value from ticket_cost_net_total
     * new: $cardBlock['ticket_cost_net_total'] + $fhBlock['ticket_cost_net_total'] + $cashBlock['ticket_cost_net_total'] + $invoicedBlock['ticket_cost_net_total'] + $invoicedBillableBlock['ticket_cost_net_total'];
     * old: $cardBlock['ticket_cost_net_total'] + $fhBlock['ticket_cost_net_total'] + $cashBlock['ticket_cost_net_total'] + $invoicedBlock['ticket_cost_net_total'] + $invoicedBillableBlock['ticket_cost_refund_total']
     * 
     * $cardBlock['processing_fees_total'] + $fhBlock['processing_fees_total'] + $cashBlock['processing_fees_total'] + $invoicedBlock['processing_fees_total'] + $invoicedBillableBlock['processing_fees_total'] + $checkWireBlock['processing_fees_total'];
     * $cardBlock['processing_fees_refund_total'] + $fhBlock['processing_fees_refund_total'] + $cashBlock['processing_fees_refund_total'] + $invoicedBlock['processing_fees_refund_total']  + $invoicedBillableBlock['processing_fees_refund_total'] + $checkWireBlock['processing_fees_refund_total'];
     * $cardBlock['processing_fees_net_total'] + $fhBlock['processing_fees_net_total'] + $cashBlock['processing_fees_net_total'] + $invoicedBlock['processing_fees_net_total']  + $invoicedBillableBlock['processing_fees_net_total'] + $checkWireBlock['processing_fees_net_total'];
     * $cardBlock['platform_fees_total'] + $fhBlock['platform_fees_total'] + $cashBlock['platform_fees_total'] + $invoicedBlock['platform_fees_total']  + $invoicedBillableBlock['platform_fees_total'] + $checkWireBlock['platform_fees_total'];

     * NOTE:  I found another error: For platform_fees_refund_total note that the old sum was using $invoicedBillableBlock processing_fees_total instead of the correct index of platform_fees_refund_total
     * new: $cardBlock['platform_fees_refund_total'] + $fhBlock['platform_fees_refund_total'] + $cashBlock['platform_fees_refund_total'] + $invoicedBlock['platform_fees_refund_total'] + $invoicedBillableBlock['platform_fees_refund_total']  + $checkWireBlock['platform_fees_refund_total'];
     * old: $cardBlock['platform_fees_refund_total'] + $fhBlock['platform_fees_refund_total'] + $cashBlock['platform_fees_refund_total'] + $invoicedBlock['platform_fees_refund_total'] + $invoicedBillableBlock['processing_fees_total']  + $checkWireBlock['platform_fees_refund_total'];
     * 
     * $cardBlock['platform_fees_net_total'] + $fhBlock['platform_fees_net_total'] + $cashBlock['platform_fees_net_total'] + $invoicedBlock['platform_fees_net_total'] + $invoicedBillableBlock['platform_fees_net_total'] + $checkWireBlock['platform_fees_net_total'];
     * $cardBlock['discount_total'] + $fhBlock['discount_total'] + $cashBlock['discount_total'] + $invoicedBlock['discount_total']  + $invoicedBillableBlock['discount_total'] + $checkWireBlock['discount_total'];
     * $cardBlock['final_net_total'] + $fhBlock['final_net_total'] + $cashBlock['final_net_total'] + $invoicedBlock['final_net_total']  + $invoicedBillableBlock['final_net_total'] + $checkWireBlock['final_net_total'];
     * 
     * @return float
     */
    public static function aggregateSum( $blocks, $index ){

        $sum = 0;
        foreach( $blocks as $block ){
            if($block){
                $sum += $block[$index];
            }
        }
        return $sum;
    }


}