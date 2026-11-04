<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClosureEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The data object.
     *
     * @var $tourBookings
     */
    public $tourBookings;

    /**
     * The user ID.
     *
     * @var $userId
     */
    public $userId;

    /**
     * The user type.
     *
     * @var $userType
     */
    public $userType;

    /**
     * Create a new job instance.
     *
     * @param  object  $tourBookings
	 * @param  int  $userId
     * @param  string  $userType
     * @return void
     */
    public function __construct($tourBookings, $userId, $userType)
    {
        $this->tourBookings = $tourBookings;
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
        foreach ($this->tourBookings as $tourBooking) {
            dispatch(new InsideClosureEmails($tourBooking, $this->userId, $this->userType));
        }
    }
}
