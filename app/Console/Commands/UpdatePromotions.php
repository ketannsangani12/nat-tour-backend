<?php

namespace App\Console\Commands;

use App\Models\TourPromotion;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdatePromotions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promotion:inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute this event to inactive the promotion when end date passed';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $tourOperator = tourOperator();
        if (!$tourOperator) {
            return;
        }

        $timezone = $tourOperator->timezone ?? config('app.timezone');
        if (!in_array($timezone, timezone_identifiers_list())) {
            $timezone = config('app.timezone');
        }

        // Get current date in the specified timezone
        $currentDateTime = Carbon::today($timezone);

        // Fetch promotions where end_date is less than or equal to current date
        $promotionsToExecute = TourPromotion::where('status', 'Active')
            ->whereDate('end_date', '<=', $currentDateTime)
            ->get();

        foreach ($promotionsToExecute as $promotion) {
            $promotion->status = 'Inactive';
            $promotion->updated_by = $promotion->created_by;
            $promotion->updated_by_user_type = $promotion->created_by_user_type;
            $promotion->save();
        }
    }
}
