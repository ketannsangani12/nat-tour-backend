<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ExecuteTipsPayout extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'execute_tips_payout';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'execute:tips-payout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute tips payout process';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if (isset(tenant()->id) && tenant()->id !== 'live' && !empty(tenant()->id)) {

                $payrollSchedules = DB::table('gratuity_payroll_schedules as gps')
                    ->where('gps.status', '=', 'Schedule')
                    ->where('payroll_date', Carbon::today()->format('Y-m-d'))
                    ->get();
                if ($payrollSchedules) {
                    foreach ($payrollSchedules as $schedule) {
                        DB::table('gratuity_payroll_schedules')
                            ->where('id', $schedule->id)
                            ->update(['status' => 'Paid', 'updated_at' => Carbon::now()]);


                        DB::table('gratuity_distributions')
                            ->where('payroll_schedule_id', $schedule->id)
                            ->where('status', 'Schedule')
                            ->update(['status' => 'Paid', 'updated_at' => Carbon::now()]);
                    }
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
