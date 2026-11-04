<?php

namespace App\Console\Commands;

use App\Models\TourSlotBulkOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireQueuedBulkOperations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulk-operations:expire-queued';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire any Tour Slots Bulk Manager operations still queued at the end of the day.';

    /**
     * Execute the console command. Runs inside the tenant context (via tenants:run), so it operates
     * on the current tenant's tour_slot_bulk_operations table.
     *
     * @return void
     */
    public function handle()
    {
        // Any operation still "queued" at end-of-day was never applied — expire it so it doesn't
        // carry over. Ops already processing / applied / failed are left untouched.
        $count = TourSlotBulkOperation::query()
            ->where('status', 'queued')
            ->where('deleted_by', 0)
            ->update(['status' => 'expired']);

        if ($count) {
            Log::info("Expired {$count} queued tour slot bulk operation(s).");
        }
    }
}
