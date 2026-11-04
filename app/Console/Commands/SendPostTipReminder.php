<?php

namespace App\Console\Commands;

use App\Jobs\Mail\TourBookingPostTipReminderMails;
use App\Jobs\Mail\TourBookingReminderMails;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPostTipReminder extends Command
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'send_post_tip_reminder';

	/**
	 * The signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'send:post-tip-reminder';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send Post Tip Reminder email once tour is completed';

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
				$tourBookings = DB::table('tour_bookings_master AS tbm')
					->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
					->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
					->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
					->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
					->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
					->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
					->join('booking_tour_guides as btg', 'btg.booking_id', '=', 'tbm.id')
					->where('tsd.date', $today)
					->where('t.status', 'Completed')
					->where('tbm.status', 'Booked')
					->where('c.is_affiliate', 'No')
					->select([
						'tbm.id as tbm_id',
						'tp.tour_operator_id',
						'tsd.tour_package_id',
						'tp.name AS package_name',
						'tbm.name as customer_name',
						'tbm.email as customer_email',
						'tbm.phone_number as customer_phone',
						't_o.name AS tour_operator',
						't_o.email AS tour_op_email',
						't_o.phone_number AS tour_op_phone',
						't_o.website AS tour_op_website',
						't_o.logo AS tour_op_logo',
						't_o.id as tour_operator_id',
						't_o.tenant_id',
						DB::raw('MIN(btg.guide_id) as guide_id'),
						'tp.email as packageEmail',
						'tp.from_name as packageFromName',
						'tp.from_email as packageFromEmail'
					])
					->groupBy(
						'tbm.id'
					)
					->get();
				if ($tourBookings->count() > 0) {
					$tourOperatorLogo = null;
					if (isset($tourBookings[0]->tour_op_logo)) {
						$tourOperatorLogo = tourOperatorLogo($tourBookings[0]->tenant_id, $tourBookings[0]->tour_op_logo);
					}
					// foreach ($tourBookings as $tourBooking) {
					// 	dispatch(new TourBookingPostTipReminderMails($tourBooking, $tourOperatorLogo));
					// 	usleep(100000); // https://www.php.net/manual/en/function.usleep.php
					// }
				}
			}
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
