<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use App\Models\GlobalRateSchedule; // Assuming you have this model
use App\Models\Tax;
use App\Models\TourPackageRate;
use Carbon\Carbon;

class ExecuteGlobalRateScheduleEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:execute-scheduled-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute scheduled global rate events based on date and time';

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

        $currentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now(), config('app.timezone'))->setTimezone($tourOperator->timezone);

        // Fetch all events where the date_time is less than or equal to the current time and status is active
        $eventsToExecute = GlobalRateSchedule::where('status', 'Active')
            ->where('date_time', '<=', $currentDateTime)
            ->get();

        foreach ($eventsToExecute as $event) {
            // Execute the event logic (e.g., update rates)
            $this->executeEvent($event);

            // Optionally, update event status to "Executed" or similar
            $event->status = 'Executed';
            $event->updated_by = $event->created_by;
            $event->updated_by_user_type = $event->created_by_user_type;
            $event->save();

            $this->info("Event for tour package {$event->tour_package_id} executed successfully.");
        }
    }

    /**
     * Perform the necessary logic to execute the rate event.
     *
     * @param GlobalRateSchedule $event
     * @return void
     */
    protected function executeEvent(GlobalRateSchedule $event)
    {
        // Set the tax for the event
        $tax = Tax::active()->first();

        // Set the tax for the tour package rates
        $taxPercent = $tax->value ?? 0;
        $taxPercentType = $tax->value_type ?? '';

        // Fetch rates for the event
        $rates = TourPackageRate::where('scheduling_year', $event->scheduling_year)
            ->where('tour_package_id', $event->tour_package_id)
            ->when(
                isset($event->affiliate_id) && $event->affiliate_id !== null,
                function ($query) use ($event) {
                    return match ($event->affiliate_id) {
                        0 => $query->whereNotNull('affiliate_id'), // Case for "all affiliates"
                        default => $query->where('affiliate_id', $event->affiliate_id), // Specific affiliate
                    };
                },
                function ($query) {
                    return $query->whereNull('affiliate_id'); // Case when no affiliate is provided
                }
            )
            ->where('global_rate', 1)
            ->whereNull('custom_date_id')
            ->orderBy('orderby')
            ->get();

        if ($rates->count() > 0) {
            foreach ($rates as $key => $rate) {
                // Calculate new rate and additional charge based on event changes
                $originalRate = $rate->primary_rate;
                $originalAdditionalCharge = $rate->primary_additional_charge;

                // Apply event changes to rate and additional charge
                $updatedRate = $originalRate;
                $updatedAdditionalCharge = $originalAdditionalCharge;

                if ($event->charge_mode === 'percentage') {
                    // Percentage based calculation
                    $percentageChange = ($originalRate * $event->charge_amount) / 100;
                    if ($event->charge_type === 'upcharge') {
                        $updatedRate += $percentageChange;
                    } else if ($event->charge_type === 'downcharge') {
                        $updatedRate -= $percentageChange;
                    }
                } elseif ($event->charge_mode === 'fixed') {
                    // Fixed amount calculation
                    if ($event->charge_type === 'upcharge') {
                        $updatedRate += $event->charge_amount;
                    } else if ($event->charge_type === 'downcharge') {
                        $updatedRate -= $event->charge_amount;
                    }
                }

                // Update additional charge based on taxPercent
                $updatedAdditionalCharge = roundout(($updatedRate * $taxPercent) / 100);
                if ($taxPercentType === 'Fixed Money') {
                    $updatedAdditionalCharge = $taxPercent; // Set as fixed amount
                }

                // Update inputs with new values
                $rate->rate = $updatedRate;
                $rate->additional_charge = $updatedAdditionalCharge;
                $event->updated_by = $event->created_by;
                $event->updated_by_user_type = $event->created_by_user_type;
                $rate->save();
            }
        }
    }
}
