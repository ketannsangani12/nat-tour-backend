<?php

namespace App\Console\Commands;

use App\Models\BookingTransact;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Booking\HistoryService;
use App\Services\Board\BoardService;

class MarkAbsentCheckins extends Command
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'checkins_absent';

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'checkins:absent';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Mark checkins absent.';

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
				$now = now();

				$nMinutesLater = null;

				$setting = DB::table('settings')->where('key', 'AUTO_CHECKINS')->first();

				if ($setting && $setting->value > 0) {
					$minutes = (int) $setting->value;
					$nMinutesLater = now()->addMinutes($minutes);
				}

				if ($nMinutesLater) {
					$now = date('H:i:s', strtotime($now));
					$nMinutesLater = date('H:i:s', strtotime($nMinutesLater));

					$bookings =  DB::table('tour_bookings_master AS tbm')
						->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
						->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
						->where('tsd.date', date('Y-m-d'))
						->whereBetween('tst.time_from', [$now, $nMinutesLater])
						->where('tbm.status', 'Booked')
						->get([
							'tbm.id',
							'tst.time_from',
							'tst.time_to'
						]);

					if (count($bookings) && !empty($bookings)) {
						foreach ($bookings as $booking) {
							$bookingTransact = BookingTransact::where('tour_booking_id', $booking->id)
								->where('check_in_status', '=', '')
								->orWhereNull('check_in_status')
								->get();

							if ($bookingTransact->count()) {
								foreach ($bookingTransact as $transact) {
									$statusInfo = [];
									for ($b = 0; $b < $transact->tourists; $b++) {
										$statusInfo[$b] = new \stdClass;
										$statusInfo[$b]->status = 'A';
										$statusInfo[$b]->dateTime = mysqlDT();
									}

									DB::table('tour_bookings_transact')
										->where('id', $transact->id)
										->update([
											'check_in_status' => json_encode($statusInfo)
										]);
								}

								DB::table('tour_booking_history')
								->insert([
									'tour_booking_id' => $booking->id,
									'operation' => 'Note',
									'details' => "Auto check-ins before to the tour's " . $setting->value . " minutes.",
									'operation_at' => mysqlDT(),
									'operation_by' => 1,
									'operation_by_user_type' => 'Admin',
									'updated_at' => Carbon::now(),
									'created_at' => Carbon::now(),
								]);

								$detail = [
									'status' => 'Absent',
									'who' => 'All',
									'created_by_with_name' => ''
								];

								HistoryService::storeCheckInDataInHistory($booking->id, $detail, mysqlDT(), true);
								BoardService::boardBookingUpdate($booking->id);	
							}
						}
					}
				}
			}
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
