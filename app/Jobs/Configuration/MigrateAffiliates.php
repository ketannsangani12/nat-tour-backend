<?php

namespace App\Jobs\Configuration;

use App\Constants\Constants;
use App\Models\Customer;
use App\Models\TourPackageRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Log;

class MigrateAffiliates implements ShouldQueue
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
     * @var $data
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param  array $data
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            switch ($this->data['affiliate_id']) {
                case Constants::STATUS_ALL:
                    $customers = Customer::isAffiliate()->get();
                    foreach ($customers as $customer) {
                        $affiliateRates = TourPackageRate::where(column: 'affiliate_id', operator: '=', value: $customer->id)
                            ->where(column: 'scheduling_year', operator: '=', value: 2025)
                            ->whereNull('custom_date_id')
                            ->get();

                        if ($affiliateRates->count()) {
                            dispatch(job: new MigrateAffiliateRates(affiliateRates: $affiliateRates));
                        }
                    }
                    break;
                default:
                    $affiliateRates = TourPackageRate::where(column: 'affiliate_id', operator: '=', value: $this->data['affiliate_id'])
                        ->where(column: 'scheduling_year', operator: '=', value: 2025)
                        ->whereNull('custom_date_id')
                        ->get();

                    if ($affiliateRates->count()) {
                        dispatch(job: new MigrateAffiliateRates(affiliateRates: $affiliateRates));
                    }
                    break;
            }
        } catch (Exception $exception) {
            Log::error(message: $exception);
        }
    }
}
