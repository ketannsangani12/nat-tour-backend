<?php

namespace App\Services\ThreeWayPayment;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\BookingTransact;
use App\Models\Payout;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ThreeWayPaymentService
{
    /**
     * calculate partner fee for single pax
     * 
     * @return float
     */
    public static function calculatePartnerFees($rateGroup, $permitFees = 0, $isGroupRate, $partnerFeeType, $partnerFeesValue)
    {
        $partnerFees = 0;
        $subTotal = ($isGroupRate == '1') ? $rateGroup->rate + $rateGroup->additional_charge : $rateGroup->rate + $rateGroup->additional_charge + $permitFees;
        $partnerFees = ($partnerFeeType == 'Percentage') ? roundout(($subTotal * $partnerFeesValue) / 100, 2) : $partnerFeesValue;
        return $partnerFees;
    }

    /**
     * fetch partner fees for booking
     * 
     * @return float
     */
    public static function fetchPartnerFeesForBooking($bookingId, $transactionId)
    {
        $payoutTransaction = Payout::where('tour_booking_id', $bookingId)
            ->where('transaction_id', $transactionId)
            ->where('payout_status', 'Completed')
            ->first();
        $tourBookingTransact = BookingTransact::where('tour_booking_id', $bookingId)->where('deleted_by', 0)->get();
        $partnerFeesTotal = 0;
        foreach ($tourBookingTransact as $pax) {
            if ($payoutTransaction) {
                $partnerFees = $pax->tourists * $pax->partner_fee;
                $partnerFeesTotal += $partnerFees;
            }
        }
        return $partnerFeesTotal;
    }

    /**
     * Transfer ticket cost to partner connected account
     * 
     * @return mixed
     */
    public static function transferToPartner($data)
    {
        $amount = $data['amount'];
        $platformFees = $data['platform_fees'];
        $commissionAmount = $data['partner_fees'];
        $bookingId = $data['booking_id'];
        $transactionId = $data['transaction_id'];
        $packageId = $data['package_id'];
        $partnerDestinationId = $data['partner_destination_id'] ?? '';
        $paymentMethod = $data['payment_method'];
        $createdByUserType = userType();

        //Calculate payout amount
        $totalCollectedAmount = $amount - $platformFees;
        $payoutAmount = $totalCollectedAmount - $commissionAmount;
        $payoutAmount = round($payoutAmount, 2);
        if ($paymentMethod == 'Card') {
            $transferGroup = $data['transfer_group'] ?? '';
            $metaData = [
                'booking_id' => $bookingId,
                'total_amount' => $amount,
                'platform_fee' => $platformFees,
                'partner_fee' => $commissionAmount,
                'payout_amount' => $payoutAmount
            ];
            $transferParams = [
                'amount'         => $payoutAmount * 100,
                'currency'       => 'usd',
                'destination'    => $partnerDestinationId,
                'transfer_group' => $transferGroup,
                'description'    => "Tour guide payment for Booking #$bookingId",
                'metadata'       => $metaData,
            ];
            $transfer = app(PaymentGatewayInterface::class)->createTransfer($transferParams);
        }
        DB::table('payouts')->insert([
            'tour_booking_id' => $bookingId,
            'package_id' => $packageId,
            'transaction_id' => $transactionId,
            'stripe_account_id' => $partnerDestinationId,
            'transfer_id' => $transfer->id ?? '',
            'commission_amount' => $commissionAmount,
            'payout_amount' => $payoutAmount,
            'total_amount' => $totalCollectedAmount,
            'payment_method' => $paymentMethod,
            'payout_status' => 'Completed',
            'created_by' => Auth::id(),
            'created_by_user_type' => $createdByUserType,
            'created_at' => Carbon::now(),
        ]);
    }
    /**
     * Update partner payout when change payment history type
     * 
     * @return mixed
     */
    public static function updatePartnerPayout($bookingId, $transactionId, $paymentMethod, $amount, $platformFees, $transferGroup = '', $partnerDestinationId = '')
    {
        $payout = Payout::where('tour_booking_id', $bookingId)->where('transaction_id', $transactionId)->where('payout_status', 'Completed')->first();
        if ($paymentMethod == 'Card') {
            $metaData = [
                'booking_id' => $bookingId,
                'total_amount' => $amount,
                'platform_fee' => $platformFees,
                'partner_fee' => $payout->commission_amount,
                'payout_amount' => $payout->payout_amount,
                'tenant_id' => tenant()->id,
            ];
            $transferParams = [
                'amount'         => $payout->payout_amount * 100,
                'currency'       => 'usd',
                'destination'    => $partnerDestinationId,
                'transfer_group' => $transferGroup,
                'description'    => "Tour guide payment for Booking #$bookingId",
                'metadata'       => $metaData,
            ];
            $transfer = app(PaymentGatewayInterface::class)->createTransfer($transferParams);
        }
        $payout->payment_method  = $paymentMethod;
        $payout->stripe_account_id = $partnerDestinationId;
        $payout->transfer_id = $transfer->id ?? '';
        $payout->updated_at = Carbon::now();
        $payout->save();
    }
    /**
     * partner fees payout
     * 
     * @return mixed
     */
    public static function storeTransferInPayout($data)
    {
        $amount = $data['amount'];
        $platformFees = $data['platform_fees'];
        $commissionAmount = $data['partner_fees'];
        $bookingId = $data['booking_id'];
        $transactionId = $data['transaction_id'];
        $packageId = $data['package_id'];
        $partnerDestinationId = $data['partner_destination_id'] ?? '';
        $paymentMethod = $data['payment_method'];
        $transferId = $data['transfer_id'] ?? '';
        $createdByUserType = userType();

        //Calculate payout amount
        $totalCollectedAmount = $amount - $platformFees;
        $payoutAmount = $totalCollectedAmount - $commissionAmount;
        $payoutAmount = round($payoutAmount, 2);

        DB::table('payouts')->insert([
            'tour_booking_id' => $bookingId,
            'package_id' => $packageId,
            'transaction_id' => $transactionId,
            'stripe_account_id' => $partnerDestinationId,
            'transfer_id' => $transferId,
            'commission_amount' => $commissionAmount,
            'payout_amount' => $payoutAmount,
            'total_amount' => $totalCollectedAmount,
            'payment_method' => $paymentMethod,
            'payout_status' => 'Completed',
            'created_by' => Auth::id(),
            'created_by_user_type' => $createdByUserType,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Reverse the transfer from destination account to platform account
     * 
     * @param int $tourBookingId
     * @param string $transactionId
     * @param float $amount
     */
    public static function reverseTransfer($tourBookingId, $transactionId, $amount)
    {
        $payout = DB::table('payouts')->where('tour_booking_id', $tourBookingId)->where('transaction_id', $transactionId)->where('payout_status', 'Completed')->first();
        if ($payout) {
            $transferId = $payout->transfer_id;
            $payoutAmount = $payout->payout_amount;
            $totalReversedAmount = DB::table('payouts')->where('tour_booking_id', $tourBookingId)->where('transfer_id', $transferId)->where('payout_status', 'Reversed')->sum('payout_amount');
            $remainingAmount = round($payoutAmount - $totalReversedAmount, 2);
            $amount = round($amount, 2);
            if ($amount > $remainingAmount) {
                throw new Exception('Unable to reverse transfer. Please enter amount less than payout amount.');
            }
            $reversalParams = [
                'amount'      => $amount * 100,
                'description' => 'Transfer reversal for Booking #' . $tourBookingId,
            ];
            app(PaymentGatewayInterface::class)->reverseTransfer($transferId, $reversalParams);
        }
    }
    /**
     * Store reverser transfer in payout table
     * 
     * @param int $tourBookingId
     * @param string $transactionId
     * @param string $refundTransactionId
     * @param float $amount
     */
    public static function storeReverseTransferInPayout($tourBookingId, $transactionId, $refundTransactionId, $ticketCost, $partnerFees = 0, $refundType = '', $paymentMethod = '')
    {
        $tourBooking = DB::table('tour_bookings_master')->where('id', $tourBookingId)->first();
        $payout = DB::table('payouts')->where('tour_booking_id', $tourBookingId)->where('transaction_id', $transactionId)->where('payout_status', 'Completed')->first();
        $createdByUserType = userType();
        if ($refundType == 'partial_refund') {
            $totalAmount = $ticketCost;
        } else {
            $totalAmount = $ticketCost + $partnerFees;
        }
        if ($payout) {

            DB::table('payouts')->insert([
                'tour_booking_id' => $tourBookingId,
                'package_id' => $tourBooking->tour_package_id,
                'transaction_id' => $refundTransactionId,
                'stripe_account_id' => $payout->stripe_account_id,
                'transfer_id' => $payout->transfer_id,
                'payout_amount' => $ticketCost,
                'total_amount' => $totalAmount,
                'commission_amount' => $partnerFees,
                'payment_method' => ($paymentMethod != '') ? $paymentMethod : $payout->payment_method,
                'payout_status' => 'Reversed',
                'created_by' => Auth::id(),
                'created_by_user_type' => $createdByUserType,
                'created_at' => Carbon::now(),
            ]);
        }
    }
    /**
     * Store reverser transfer in payout table
     * 
     * @param int $tourBookingId
     * @param string $transactionId
     * @param string $refundTransactionId
     * @param float $amount
     */
    public static function storeReverseTransferInSwitchPackage($tourBookingId, $refundTransactionId, $ticketCost, $partnerFees = 0, $refundType = '')
    {
        $tourBooking = DB::table('tour_bookings_master')->where('id', $tourBookingId)->first();
        $payout = DB::table('payouts')->where('tour_booking_id', $tourBookingId)->where('package_id', $tourBooking->tour_package_id)->where('payout_status', 'Completed')->first();
        $createdByUserType = userType();
        if ($refundType == 'partial_refund') {
            $totalAmount = $ticketCost;
        } else {
            $totalAmount = $ticketCost + $partnerFees;
        }
        if ($payout) {

            DB::table('payouts')->insert([
                'tour_booking_id' => $tourBookingId,
                'package_id' => $payout->package_id,
                'transaction_id' => $refundTransactionId,
                'stripe_account_id' => $payout->stripe_account_id,
                'transfer_id' => $payout->transfer_id,
                'payout_amount' => $ticketCost,
                'total_amount' => $totalAmount,
                'commission_amount' => $partnerFees,
                'payment_method' => $payout->payment_method,
                'payout_status' => 'Reversed',
                'created_by' => Auth::id(),
                'created_by_user_type' => $createdByUserType,
                'created_at' => Carbon::now(),
            ]);
        }
    }
    /**
     * Store reverser transfer for switch package in case of switcing to normal
     * 
     * @param int $tourBookingId
     * @param string $transactionId
     * @param string $refundTransactionId
     * @param float $amount
     */
    public static function storeReverseTransferOfPartnerFeesOnly($tourBookingId, $transactionId, $refundTransactionId, $partnerFees, $paymentMethod = '')
    {
        $payout = DB::table('payouts')->where('tour_booking_id', $tourBookingId)->where('transaction_id', $transactionId)->first();
        $createdByUserType = userType();
        if ($payout) {
            PayoutService::storeRefundInPayout(
                $tourBookingId,
                $payout->package_id,
                $refundTransactionId,
                $payout->stripe_account_id,
                0.00,
                0.00,
                $partnerFees,
                ($paymentMethod != '') ? $paymentMethod : $payout->payment_method,
                $payout->transfer_id ?? '',
                $createdByUserType
            );
        }
    }

    /**
     * Transfer ticket cost to other partner connected account
     * 
     * @return mixed
     */
    public static function transferBetweenConnectedAccounts($data)
    {
        $oldTourPackageId = $data['old_tour_package_id'];
        $newTourPackageId = $data['new_tour_package_id'];
        $amount = $data['amount'];
        $platformFees = $data['platform_fees'];
        $partnerFees = $data['partner_fees'];
        $bookingId = $data['booking_id'];
        $oldPackageDestinationId = $data['old_package_destination_id'] ?? '';
        $newPackageDestinationId = $data['new_package_destination_id'] ?? '';
        $paymentMethod = $data['payment_method'];
        $transferGroup = $data['transfer_group'] ?? '';
        $createdByUserType = userType();

        $payout = DB::table('payouts')->where('tour_booking_id', $bookingId)->where('package_id', $oldTourPackageId)->where('payout_status', 'Completed')->first();
        $payoutAmount = $payout->payout_amount;

        if ($payoutAmount > 0) {
            if ($paymentMethod == 'Card') {
                $transferId = $payout->transfer_id;
                $gateway = app(PaymentGatewayInterface::class);
                $reversalParams = [
                    'amount'      => $payoutAmount * 100,
                    'description' => "Transfer reversal of switch package for Booking #$bookingId",
                ];
                $gateway->reverseTransfer($transferId, $reversalParams);
                $metaData = [
                    'booking_id' => $bookingId,
                    'total_amount' => $amount,
                    'platform_fee' => $platformFees,
                    'partner_fee' => $partnerFees,
                    'payout_amount' => $payoutAmount,
                ];
                $transferParams = [
                    'amount'         => $payoutAmount * 100,
                    'currency'       => 'usd',
                    'destination'    => $newPackageDestinationId,
                    'transfer_group' => $transferGroup,
                    'description'    => "Tour guide payment of switch package for Booking #$bookingId",
                    'metadata'       => $metaData,
                ];
                $transfer = $gateway->createTransfer($transferParams);
            }


            //Insert into payout table for old package
            PayoutService::storeRefundInPayout(
                $bookingId,
                $oldTourPackageId,
                NULL,
                $oldPackageDestinationId,
                $payoutAmount,
                $payout->total_amount,
                $partnerFees,
                $paymentMethod,
                $payout->transfer_id ?? '',
                $createdByUserType
            );

            //Insert into payout table for old package
            PayoutService::storePaymentInPayout(
                $bookingId,
                $newTourPackageId,
                NULL,
                $newPackageDestinationId,
                $payoutAmount,
                $payout->total_amount,
                $partnerFees,
                $paymentMethod,
                $transfer->id ?? '',
                $createdByUserType
            );
        }
    }

    /**
     * Reverse Transfer ticket cost to platform account for old package
     * 
     * @return mixed
     */
    public static function reverseTransferToPlatformAccount($data)
    {

        $oldTourPackageId = $data['old_tour_package_id'];
        $partnerFees = $data['partner_fees'];
        $bookingId = $data['booking_id'];
        $oldPackageDestinationId = $data['old_package_destination_id'] ?? '';
        $paymentMethod = $data['payment_method'];
        $createdByUserType = userType();

        $payout = DB::table('payouts')->where('tour_booking_id', $bookingId)->where('package_id', $oldTourPackageId)->where('payout_status', 'Completed')->first();
        $payoutAmount = $payout->payout_amount;

        if ($payoutAmount > 0) {
            if ($paymentMethod == 'Card') {
                $transferId = $payout->transfer_id;
                $reversalParams = [
                    'amount'      => $payoutAmount * 100,
                    'description' => "Transfer reversal of switch package for Booking #$bookingId",
                ];
                app(PaymentGatewayInterface::class)->reverseTransfer($transferId, $reversalParams);
            }

            //Insert into payout table for old package
            $payoutId =  PayoutService::storeRefundInPayout(
                $bookingId,
                $oldTourPackageId,
                NULL,
                $oldPackageDestinationId,
                $payoutAmount,
                $payout->total_amount,
                $partnerFees,
                $paymentMethod,
                $payout->transfer_id ?? '',
                $createdByUserType
            );

            return $payoutId;
        }
    }
    /**
     * Transfer ticket cost to new partner connected account for new package
     * 
     * @return mixed
     */
    public static function transferToNewPartner($data)
    {

        $oldTourPackageId = $data['old_tour_package_id'];
        $newTourPackageId = $data['new_tour_package_id'];
        $amount = $data['amount'];
        $platformFees = $data['platform_fees'];
        $partnerFees = $data['partner_fees'];
        $bookingId = $data['booking_id'];
        $paymentIntentId = $data['payment_intent_id'] ?? '';
        $newPackageDestinationId = $data['new_package_destination_id'] ?? '';
        $paymentMethod = $data['payment_method'];
        $createdByUserType = userType();

        $payout = DB::table('payouts')->where('tour_booking_id', $bookingId)->where('package_id', $oldTourPackageId)->where('payout_status', 'Completed')->first();
        $payoutAmount = $payout->payout_amount;

        if ($payoutAmount > 0) {
            if ($paymentMethod == 'Card') {
                $transferId = $payout->transfer_id;
                $retrieveOptions = ['expand' => ['charges.data.transfer']];
                $paymentIntent = app(PaymentGatewayInterface::class)->retrievePayment($paymentIntentId, $retrieveOptions);
                $transferGroup = $paymentIntent->transfer_group ?? null;
                $metaData = [
                    'booking_id' => $bookingId,
                    'total_amount' => $amount,
                    'platform_fee' => $platformFees,
                    'partner_fee' => $partnerFees,
                    'payout_amount' => $payoutAmount,
                ];
                $transferParams = [
                    'amount'         => $payoutAmount * 100,
                    'currency'       => 'usd',
                    'destination'    => $newPackageDestinationId,
                    'transfer_group' => $transferGroup,
                    'description'    => "Tour guide payment of switch package for Booking #$bookingId",
                    'metadata'       => $metaData,
                ];
                $transfer = app(PaymentGatewayInterface::class)->createTransfer($transferParams);
            }

            //Insert into payout table for old package
            PayoutService::storePaymentInPayout(
                $bookingId,
                $newTourPackageId,
                NULL,
                $newPackageDestinationId,
                $payoutAmount,
                $payout->total_amount,
                $partnerFees,
                $paymentMethod,
                $transfer->id ?? '',
                $createdByUserType
            );
        }
    }
    /**
     * calculate net partner fee for single booking
     * 
     * @return mixed
     */
    public static function getNetPartnerFees($tourBookingId)
    {
        $tourBookingHistory = DB::table('tour_booking_history as tbh')
            ->where('tbh.tour_booking_id', $tourBookingId)
            ->whereNull('tbh.charge_type')
            ->get();

        $dontRefundPartnerFees = 0;
        $totalPartnerFees = 0;
        $totalRefundedPartnerFees = 0;
        if (count($tourBookingHistory) > 0) {
            foreach ($tourBookingHistory as $history) {
                $jsonDataFee = json_decode($history->details, true);
                if ($history->payment_method == "Don't Refund") {
                    $dontRefundPartnerFees += $jsonDataFee['refund_partner_fees_keep_us'] ?? 0;
                }
                if ($history->operation == 'Refund' && $history->payment_method != "Don't Refund") {
                    $totalRefundedPartnerFees += $history->partner_fees;
                }
                if ($history->operation == 'Payment') {
                    $totalPartnerFees += $history->partner_fees;
                }
            }
        }
        $netPartnerCommission = $totalPartnerFees - $dontRefundPartnerFees - $totalRefundedPartnerFees;
        return $netPartnerCommission;
    }
    /**
     * calculate net partner fee for single booking
     * 
     * @return mixed
     */
    public static function getNetPartnerAmountsForBooking($tourBookingId)
    {
        $tourBookingHistory = DB::table('tour_booking_history as tbh')
            ->where('tbh.tour_booking_id', $tourBookingId)
            ->whereNull('tbh.charge_type')
            ->get();

        $dontRefundPartnerFees = 0;
        $totalPartnerFees = 0;
        $totalTicketCostAmount = 0;
        $refTicketCostAmount = 0;
        $totalRefundedPartnerFees = 0;
        if (count($tourBookingHistory) > 0) {
            foreach ($tourBookingHistory as $history) {
                $jsonDataFee = json_decode($history->details, true);
                if ($history->payment_method == "Don't Refund") {
                    $dontRefundPartnerFees += $jsonDataFee['refund_partner_fees_keep_us'] ?? 0;
                }
                if ($history->operation == 'Refund' && $history->payment_method != "Don't Refund") {
                    $totalRefundedPartnerFees += $history->partner_fees;
                    $refTicketCostAmount += $history->ticket_cost - $history->partner_fees;
                }
                if ($history->operation == 'Payment') {
                    $totalPartnerFees += $history->partner_fees;
                    $totalTicketCostAmount += $history->ticket_cost - $history->partner_fees;
                }
            }
        }
        $netPartnerCommission = $totalPartnerFees - $dontRefundPartnerFees - $totalRefundedPartnerFees;
        $netTicketCost = $totalTicketCostAmount - $refTicketCostAmount;
        return compact('netPartnerCommission', 'netTicketCost');
    }
    /**
     * calculate net partner fee for single transaction
     * 
     * @return mixed
     */
    public static function getNetPartnerAmountsForTransaction($tourBookingId, $transactionNumber)
    {
        $payout = DB::table('payouts as po')
            ->join('transactions as t', 't.id', '=', 'po.transaction_id')
            ->where('po.tour_booking_id', $tourBookingId)
            ->where('t.transaction_number', $transactionNumber)
            ->where('po.payout_status', 'Completed')
            ->first();
        $reversedPayouts = DB::table('payouts as po')
            ->join('transactions as t', 't.id', '=', 'po.transaction_id')
            ->where('po.tour_booking_id', $tourBookingId)
            ->where('t.transaction_number', $transactionNumber)
            ->where('po.payout_status', 'Reversed')
            ->get();
        $refunds = DB::table('tour_booking_history as tbh')
            ->join('transactions as t', 't.id', '=', 'tbh.transaction_id')
            ->where('tbh.tour_booking_id', $tourBookingId)
            ->where('t.transaction_number', $transactionNumber)
            ->where('tbh.operation', 'Refund')
            ->get();
        $totalPartnerCommission = $payout->commission_amount;
        $totalTicketCost = $payout->payout_amount;

        $totalReversedPartnerCommission = 0;
        $totalReversedPartnerFees = 0;
        $totalReversedTicketCost = 0;
        foreach ($reversedPayouts as $reversedPayout) {
            $totalReversedPartnerCommission += $reversedPayout->commission_amount;
            $totalReversedTicketCost += $reversedPayout->payout_amount;
        }
        $dontRefundPartnerFees = 0;
        foreach ($refunds as $refund) {
            if ($refund->payment_method == "Don't Refund") {
                $dontRefundPartnerFees += $jsonDataFee['refund_partner_fees_keep_us'] ?? 0;
            }
        }

        $netPartnerCommission = $totalPartnerCommission - $dontRefundPartnerFees - $totalReversedPartnerCommission;
        $netTicketCost = $totalTicketCost - $totalReversedTicketCost;

        return compact('netPartnerCommission', 'netTicketCost');
    }
}
