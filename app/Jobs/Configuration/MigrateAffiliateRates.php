<?php

namespace App\Jobs\Configuration;

use App\Models\TourPackageRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Log;

class MigrateAffiliateRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The object of request.
     *
     * @var object $affiliateRates
     */
    public $affiliateRates;

    /**
     * Create a new job instance.
     *
     * @param  object $affiliateRates
     * @return void
     */
    public function __construct($affiliateRates)
    {
        $this->affiliateRates = $affiliateRates;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            foreach ($this->affiliateRates as $rate) {
                $checkExistance = TourPackageRate::where(column: 'affiliate_id', operator: '=', value: $rate->affiliate_id)
                    ->where(column: 'rate_for', operator: '=', value: $rate->rate_for)
                    ->where(column: 'orderby', operator: '=', value: $rate->orderby)
                    ->where(column: 'mapping_key', operator: '=', value: $rate->mapping_key)
                    ->where(column: 'scheduling_year', operator: '=', value: 2026)
                    ->whereNull('custom_date_id')
                    ->first();

                if ($checkExistance) {
                    $checkExistance->forceDelete();
                }

                $newRate = $rate->replicate();
                $newRate->scheduling_year = 2026; // the new scheduling_year
                $newRate->save();
            }
        } catch (Exception $exception) {
            Log::error(message: $exception);
        }
    }
}
