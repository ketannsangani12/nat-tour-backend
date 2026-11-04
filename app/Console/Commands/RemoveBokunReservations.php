<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RemoveBokunReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:bokun-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired reservations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
			$deleteExpiredReservations = DB::table('bokun_reservations')
                ->where('expired_at', '<', now())
                ->delete();
		} catch (Exception $exception) {
			Log::error($exception);
		}
    }
}
