<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCustomerLocation;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerLocationController extends Controller
{
    /**
     * Return how many customers still need their location updated.
     */
    public function status()
    {
        return response()->json([
            'remaining' => $this->pendingQuery()->count(),
        ]);
    }

    /**
     * Dispatch a queue job for each of the next 100 pending customers.
     * Returns immediately — processing happens in the background.
     */
    public function batchUpdate()
    {
        $customers = $this->pendingQuery()->limit(100)->get('id');

        foreach ($customers as $customer) {
            ProcessCustomerLocation::dispatch($customer->id);
        }

        $remaining = $this->pendingQuery()->count();

        return response()->json([
            'dispatched' => $customers->count(),
            'remaining'  => $remaining,
        ]);
    }

    /**
     * Customers who have a Stripe transaction but no location data yet.
     */
    private function pendingQuery()
    {
        return Customer::whereNull('country')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('transactions')
                  ->join('tour_bookings_master', 'transactions.tour_booking_id', '=', 'tour_bookings_master.id')
                  ->whereColumn('tour_bookings_master.customer_id', 'customers.id')
                  ->whereNotNull('transactions.transaction_number')
                  ->where('transactions.transaction_number', '!=', '')
                  ->where('transactions.transaction_number', 'like', 'pi_%');
            });
    }
}
