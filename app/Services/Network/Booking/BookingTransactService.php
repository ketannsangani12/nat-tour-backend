<?php

namespace App\Services\Network\Booking;

use App\Models\TourBooking;
use App\Models\TourPackage;
use App\Models\Customer;
use App\Models\BookingTransact;

class BookingTransactService
{
    /**
     * Create booking transactions
     * 
     * @param TourBooking $bookingMaster
     * @param array $request
     * @param TourPackage $package
     * @param Customer $customer
     * 
     * @return void
     */
    public function createBookingTransactions(TourBooking $bookingMaster, array $request, TourPackage $package, Customer $customer)
    {
        foreach ($request['rateGroupSelections'] as $rateGroupSelection) {
            $rateGroup = $rateGroupSelection['rateGroup'];
    
            $transact = new BookingTransact();
            $transact->tour_booking_id = $bookingMaster->id;
            $transact->tour_package_rate_id = $rateGroup['id'];
            $transact->tourists = $rateGroupSelection['quantity'];
            $transact->rate = $rateGroup['rate'];
            $transact->total = $rateGroupSelection['total'];
            $transact->save();
        }
    }
}
