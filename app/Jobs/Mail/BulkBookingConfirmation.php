<?php

namespace App\Jobs\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkBookingConfirmation implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 0;

	/**
	 * The date string.
	 *
	 * @var $date
	 */
	public $date;

	/**
	 * The user ID int.
	 *
	 * @var $userId
	 */
	public $userId;

	/**
	 * The user type string.
	 *
	 * @var $userType
	 */
	public $userType;

	/**
	 * Create a new job instance.
	 *
	 * @param  int id
	 * @return void
	 */
	public function __construct($date, $userId, $userType)
	{
		$this->date = $date;
		$this->userId = $userId;
		$this->userType = $userType;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		try {
			$bookings =  DB::table('tour_bookings_master AS tbm')
				->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
				->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
				->where('tsd.date', $this->date)
                ->where('tbm.status', 'Booked')
				->get(['tbm.id']);

			foreach ($bookings as $booking) {
				dispatch(new BulkEmailConfirmation($booking->id, $this->userId, $this->userType));
			}
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
