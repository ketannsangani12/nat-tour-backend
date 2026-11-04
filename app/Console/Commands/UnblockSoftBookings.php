<?php

namespace App\Console\Commands;

use App\Models\TourBooking;
use Exception;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnblockSoftBookings extends Command
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'unblock_soft_bookings';

	/**
	 * The signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'unblock:soft-bookings';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Unblock soft bookings after 48 hours';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {
				$today = date('Y-m-d');
				$date = date('Y-m-d', strtotime("+48 hour", strtotime($today)));

				$tourBookings = DB::table('tour_bookings_master AS tbm')
					->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
					->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
					->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
					->join('states AS st', 'st.id', '=', 'ct.state_id')
					->where('tsd.date', $date)
					->where('tbm.status', 'Booked')
					->where('c.is_affiliate', 'Yes')
					->where('tbm.is_reserved_for_affiliate', 1)
					->select([
						'tbm.id as tbm_id','tst.time_from', 'tst.time_to', 'tsd.date', 'tst.time_slot_seats as seats', 'tp.tour_operator_id', 'tsd.tour_package_id',
						'tbm.tourists as total_tourists','tp.name AS package_name', 'tp.important_notes', 'tp.things_to_bring', 'ct.state_id', 'st.permit_fee',
						'c.name as customer_name','c.email as customer_email','c.phone_number as customer_phone',
						't_o.name AS tour_operator', 't_o.email AS tour_op_email', 't_o.phone_number AS tour_op_phone', 't_o.website AS tour_op_website', 't_o.logo AS tour_op_logo',
						't_o.cancellation_policy', 't_o.id as tour_operator_id', 't_o.tenant_id',
						'tp.stripe_destination_id', 'tp.email as packageEmail', 'tp.from_name as packageFromName', 'tp.from_email as packageFromEmail', 'tp.api_key as packageApiKey',
						DB::raw("(SELECT SUM(tbm.tourists)
								FROM tour_bookings_master AS tbm
								WHERE tbm.tour_slot_id = tst.id
									AND tbm.status != 'Cancelled'
								GROUP BY tbm.tour_slot_id) AS booked_seats")
					])
					->get();

				if ($tourBookings) {
					foreach ($tourBookings as $tourBooking) {
						$tourBooking = TourBooking::find($tourBooking->tbm_id);
						$tourBooking->is_reserved_for_affiliate = 0;
						$tourBooking->save();

						DB::table('tour_booking_history')
							->insert([
								'tour_booking_id' => $tourBooking->id,
								'operation' => 'Note',
								'details' => "After 48 hours after booking, soft booking has been automatically unblocked.",
								'operation_at' => mysqlDT(),
								'operation_by' => auth()->id(),
								'operation_by_user_type' => userType(),
								'updated_at' => Carbon::now(),
								'created_at' => Carbon::now(),
							]);
					}
				}
			}
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
