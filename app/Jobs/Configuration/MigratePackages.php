<?php

namespace App\Jobs\Configuration;

use App\Constants\Constants;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\Log;

class MigratePackages implements ShouldQueue
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
            switch ($this->data['package_id']) {
                case Constants::STATUS_ALL:
                    $packages = TourPackage::active()->get();
                    foreach ($packages as $package) {
                        $packageRates = TourPackageRate::where(column: 'tour_package_id', operator: '=', value: $package->id)
                            ->where(column: 'scheduling_year', operator: '=', value: 2025)
                            ->whereNull(columns: ['affiliate_id', 'custom_date_id'])
                            ->get();

                        if ($packageRates->count()) {
                            dispatch(job: new MigratePackageRates(packageRates: $packageRates));
                        }
                    }
                    break;
                default:
                    $packageRates = TourPackageRate::where(column: 'tour_package_id', operator: '=', value: $this->data['package_id'])
                        ->where(column: 'scheduling_year', operator: '=', value: 2025)
                        ->whereNull(columns: ['affiliate_id', 'custom_date_id'])
                        ->get();

                    if ($packageRates->count()) {
                        dispatch(job: new MigratePackageRates(packageRates: $packageRates));
                    }
                    break;
            }
        } catch (Exception $exception) {
            Log::error(message: $exception);
        }
    }
}
