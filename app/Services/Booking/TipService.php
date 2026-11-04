<?php

namespace App\Services\Booking;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Booking\TransactionService;
use App\Services\Booking\HistoryService;
use App\Models\Transaction;
use App\Models\TourBookingHistory;
use Illuminate\Support\Facades\Auth;

class TipService
{
    /**
     * Store tip in gratuity table
     *
     * @param integer|string $tourBookingId
     *
     * @return void
     */
    public static function tourBookingTip($tourBookingId, $customerId, $tipDetails = [], $paymentVia = '', $status = ''): void
    {
        DB::table('gratuities')
            ->insertGetId([
                'booking_id' => $tourBookingId,
                'gratuity_setting_id' => $tipDetails['tip_setting_id'] ?? null,
                'customer_id' => $customerId,
                'gratuity_type' => $tipDetails['tip_type'] ?? 'pre',
                'amount' => $tipDetails['tip_amount'],
                'fees' => $tipDetails['tip_fees'],
                'message' => $tipDetails['tip_message'],
                'payment_method' => $paymentVia,
                'status' => $status,
                'created_by' => Auth::id(),
                'created_by_user_type' => userType(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
    }

    /**
     * Store custom charge in transactions and history table
     *
     * @param mixed $booking
     *
     * @return void
     */
    public static function createBookingStoreTip($booking, $paymentMethod, $paymentIntent = null): void
    {
        $tipFees = 0;
        $tipDetails = [];
        $isCashFees = cashFees();

        if ($paymentMethod == 'Card' || ($paymentMethod == 'Cash' && $isCashFees == 1)) {
            $bookingId = $booking["booking_id"];
        } else {
            $bookingId = $booking->id;
        }

        $tips = static::getTipsDetails($bookingId);

        if (count($tips) > 0) {
            foreach ($tips as $key => $tip) {
                $tipAmount = $tip->amount;
                $tipDetails['amount'] = $tipAmount;

                if ($paymentMethod == 'Card' || ($paymentMethod == 'Cash' && $isCashFees == 1)) {
                    $tipFees = $tip->fees;
                } else {
                    $tipFees = 0;
                }

                $tipDetails['fees'] = $tipFees;
                $tipDetails['message'] = $tip->message;
                $tipDetails['status'] = 'paid';

                // Update gratuity record
                $getTip = DB::table('gratuities')
                    ->where('id', $tip->id)
                    ->update([
                        'transaction_number' => isset($paymentIntent->id) ? $paymentIntent->id : null,
                        'application_fee_id' => isset($paymentIntent->charges->data[0]->application_fee) ? $paymentIntent->charges->data[0]->application_fee : null,
                        'fees' => $tipFees,
                        'payment_method' => $paymentMethod,
                        'status' => 'paid',
                    ]);

                if ($getTip) {
                    $tipTransactionId = TransactionService::storeTipInTransaction($bookingId, $tipAmount, $tipFees, $paymentMethod, $paymentIntent);
                    DB::commit();

                    // Save custom charges in history
                    HistoryService::storeTipInHistory($bookingId, $paymentMethod, $tipAmount, $tipFees, $tipDetails, $paymentIntent, $tipTransactionId);
                    DB::commit();
                }
            }
        }
    }
    /**
     * Service method to get tip list of open/paid
     *
     * @param integer $bookingId
     * @param mixed $transaction
     *
     * @return mixed
     */
    public static function getTipsDetails($bookingId, $transaction = []): mixed
    {
        $tips = DB::table('gratuities')
            ->where('booking_id', $bookingId)
            ->get();
        return $tips;
    }

    /**
     * Execute the service to get overall and remaining custom charge and fees
     *
     * @param mixed $bookingId
     *
     * @return array
     */
    public static function getTipAmounts($bookingId, $transactions = []): array
    {
        $transactions = static::tipTransactions($bookingId);
        $tipAmountAndFees = static::tipCalculation($bookingId, $transactions);

        return [
            'tip_total' => $tipAmountAndFees['tipAmount'],
            'tip_fees' => $tipAmountAndFees['tipFees'],
            'tip_amount' => $tipAmountAndFees['tipAmount'] - $tipAmountAndFees['tipFees'],
            'pay_later_tip_total' => $tipAmountAndFees['payLaterTipAmount'],
            'pay_later_tip_fees' => $tipAmountAndFees['payLatertipFees'],
        ];
    }
    /**
     * Get records for charge_type of tip in transactions table
     *
     * @param int|string $bookingId
     *
     * @return object
     */
    public static function tipTransactions($bookingId): object
    {
        $transactions = Transaction::with('bookingHistory')
            ->where('tour_booking_id', $bookingId)
            ->whereIn('status', ['Completed', 'Failed', 'Refund'])
            ->where('charge_type', 'tip')
            ->orderBy('id', 'DESC')
            ->get();

        return $transactions;
    }

    /**
     * Service method to calculate custom charge with refunds
     *
     * @param integer $bookingId
     * @param mixed $transaction
     *
     * @return array
     */
    public static function tipCalculation($bookingId, $transaction = []): array
    {
        $tipAmount = 0;
        $tipFees = 0;
        $tipPayLaterAmount = 0;
        $tipPayLaterFees = 0;

        if (count($transaction) > 0) {
            foreach ($transaction as $trans) {
                $tipAmount += $trans->amount;
                $tipFees += $trans->fee_amount;
            }
        } else {
            // For Pay Later
            $tipList = static::getTipsDetails($bookingId);

            if (count($tipList) > 0) {
                foreach ($tipList as $tip) {
                    $tipPayLaterAmount += $tip->amount + $tip->fees;
                    $tipAmount += $tip->amount;
                    $tipPayLaterFees += $tip->fees;
                }
            }
        }
        return [
            'tipAmount' => $tipAmount,
            'tipFees' => $tipFees,
            'payLatertipFees' => $tipPayLaterFees,
            'payLaterTipAmount' => $tipPayLaterAmount
        ];
    }
    /**
     * Service method to get booking assigned guides
     *
     * @param integer $bookingId
     * @param mixed $transaction
     *
     * @return mixed
     */

    /*public static function getBookingAssignedGuides($bookingId): mixed
    {
        $assignedGuides = DB::table('booking_tour_guides as btg')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
            ->leftJoin('stations as s', 's.id', '=', 'btg.station_id')
            ->where('btg.booking_id', $bookingId)
            ->groupBy(
                'btg.guide_id',
                'tos.first_name',
                'tos.last_name'
            )
            ->select([
                // representative row id
                DB::raw('MIN(btg.id) as booking_guide_id'),

                'btg.guide_id as guide_id',

                // SUM tourists
                DB::raw('SUM(btg.tourists) as tourists'),
                DB::raw('SUM(btg.infants) as infants'),

                // safe aggregations
                DB::raw('MAX(btg.distribution_percentage) as distribution_percentage'),
                DB::raw('MAX(btg.distribution_amount) as distribution_amount'),
                DB::raw('MAX(s.name) as station_name'),

                DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as guide_name"),
            ])
            ->get();

        return $assignedGuides;
    }*/

    public static function getBookingAssignedGuides( $bookingId, $date = null, $fromTime = null): mixed 
    {
        $assignedGuides = DB::table('booking_tour_guides as btg')
            ->join('tour_bookings_master as tbm', 'tbm.id', '=', 'btg.booking_id')
            ->join('tour_operator_staff as tos', 'tos.id', '=', 'btg.guide_id')
            ->leftJoin('stations as s', 's.id', '=', 'btg.station_id')

            // Station name override join
            ->leftJoin('board_station_specific_name as bssn', function ($join) use ($date, $fromTime) {
                $join->on('bssn.station_id', '=', 'btg.station_id');

                if ($date) {
                    $join->where('bssn.date', '=', $date);
                }

                if ($fromTime) {
                    $join->where('bssn.time_from', '=', $fromTime);
                }
            })

            ->where('btg.booking_id', $bookingId)

            ->groupBy(
                'btg.guide_id',
                'tos.first_name',
                'tos.last_name'
            )

            ->select([
                DB::raw('MIN(btg.id) as booking_guide_id'),
                'btg.guide_id as guide_id',

                // Sum tourists
                DB::raw('SUM(DISTINCT btg.tourists) as tourists'),
                DB::raw('SUM(DISTINCT btg.infants) as infants'),

                DB::raw('MAX(btg.distribution_percentage) as distribution_percentage'),
                DB::raw('MAX(btg.distribution_amount) as distribution_amount'),

                // Override station name if exists
                DB::raw('MAX(COALESCE(bssn.name, s.name)) as station_name'),

                DB::raw("CONCAT(tos.first_name,' ',tos.last_name) as guide_name"),
            ])
            ->get();

        return $assignedGuides;
    }

    /**
     * Update distribution amounts for guides in booking
     *
     * @param integer $bookingId
     *
     * @return void
     */
    public static function updateDistributionAmounts($bookingId): void
    {
        $assignedGuides = static::getBookingAssignedGuides($bookingId);

        if (count($assignedGuides) > 0) {
            $gratuity = DB::table('gratuities as g')
                ->join('gratuity_settings as gs', 'gs.id', '=', 'g.gratuity_setting_id')
                ->where('g.booking_id', $bookingId)
                ->first(['g.id', 'g.gratuity_setting_id', 'g.amount', 'g.gratuity_type', 'g.amount', 'g.fees', 'gs.distribution_type']);
            $tipAmount = ($gratuity) ? $gratuity->amount : 0;

            if ($tipAmount > 0) {
                // Equal distribution among all guides
                $distributionPercentage = roundout(100 / count($assignedGuides), 2);
                $distributionAmount = roundout($tipAmount / count($assignedGuides), 2);

                foreach ($assignedGuides as $guide) {
                    DB::table('booking_tour_guides')
                        ->where('id', $guide->booking_guide_id)
                        ->update([
                            'distribution_percentage' => $distributionPercentage,
                            'distribution_amount' => $distributionAmount,
                            'updated_at' => Carbon::now()
                        ]);
                }
            }
        }
    }
}
