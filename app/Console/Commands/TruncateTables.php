<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TruncateTables extends Command
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'truncate_tables';

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'truncate:tables';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Truncate the database tables.';

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
				$dateTime = Carbon::now()->subMinutes(10);

				$jobs = DB::table("jobs")->where("created_at", "<", strtotime($dateTime))->get();

				if (count($jobs) && !empty($jobs)) {
					foreach ($jobs as $job) {
						DB::statement("DELETE FROM jobs WHERE id = $job->id;");
					}
				}

				DB::table('failed_jobs')->truncate();

				DB::table('tokens')->where('updated_at', '<', Carbon::now()->subDays(7))->delete();
			}
		} catch (Exception $exception) {
			Log::error($exception);
		}
	}
}
