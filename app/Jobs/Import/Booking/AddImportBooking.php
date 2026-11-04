<?php

namespace App\Jobs\Import\Booking;

use App\Models\BookingTransact;
use App\Models\Customer;
use App\Models\TourBooking;
use App\Models\TourBookingHistory;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use App\Models\TourSlotTime;
use App\Models\Transaction;
use App\Services\TimeSlot\TimeSlotAvailabilityService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
// use Faker\Factory as Faker;
use Illuminate\Support\Str;

class AddImportBooking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The data object.
     *
     * @var $tourBooking
     */
    public $tourBooking;

    /**
     * The packages array.
     *
     * @var $packages
     */
    public $packages;

    /**
     * The input date string.
     *
     * @var $inputDate
     */
    public $inputDate;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $tourBooking
     * @param  mixed  $packages
     * @param  mixed  $inputDate
     * @return void
     */
    public function __construct($tourBooking, $packages, $inputDate)
    {
        $this->tourBooking = $tourBooking;
        $this->packages = $packages;
        $this->inputDate = $inputDate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $val = (array) $this->tourBooking;
            $packages = (array) $this->packages;

            $findPackageId = $packages[$val['Name']];

            $tourBooking = TourBooking::where('group_id', $val['OrderNo'])->count();

            $package = TourPackage::find($findPackageId);

            // $date = '2025' . date('-m-d', strtotime($val['Event Start Date Time']));
            $date = date('Y-m-d', strtotime((string) $this->inputDate));
            $time = date('H:i:s', strtotime($val['Event Start Date Time']));

            $slot = TourSlotTime::where('tour_package_id', $findPackageId)
                ->whereDate('date', $date)
                ->whereTime('time_from', $time)
                ->first();

            $packageRate = TourPackageRate::where('orderby', 1)->first();

            if ($tourBooking == 0 && $val['Amount'] > 0 && $package && $slot && $packageRate) {
                // Create a new Faker instance
                // $faker = Faker::create();

                $firstNames = ['Devat', 'Peter', 'Scott', 'Wyatt', 'Lane', 'Ketan', 'Illiana', 'Sumit', 'Avni', 'Dimpal', 'Khooshbu', 'John', 'Jane', 'Alice', 'Bob', 'Charlie', 'David', 'Emily', 'Eve', 'Frank', 'Grace'];
                $lastNames = ['Karetha', 'Dupris', 'Dean', 'Gilmore', 'Gilmore', 'Sangani', 'Witkiewicz', 'Shrivastav', 'Patel', 'Bhinde', 'Ladha', 'Doe', 'Smith', 'Johnson', 'Brown', 'Taylor', 'Davis', 'Miller', 'Wilson', 'Moore', 'Anderson'];

                $randomFirstName = $firstNames[array_rand($firstNames)];
                $randomLastName = $lastNames[array_rand($lastNames)];

                $domains = ['gmail.com', 'yahoo.com', 'outlook.com'];

                $countryCodes = ['+1', '+44', '+91', '+33', '+49'];

                // Create a new Customer instance
                $customer = new Customer();
                $customer->tour_operator_id = 1;
                $customer->name = "$randomFirstName $randomLastName"; // $faker->name; // Generates a random name
                $customer->email = Str::random(10) . '@' . $domains[array_rand($domains)]; // $faker->email; // Generates a random email
                $customer->phone_number = $countryCodes[array_rand($countryCodes)] . ' (' . rand(100, 999) . ') ' . rand(100, 999) . '-' . rand(1000, 9999); // $faker->phoneNumber; // Generates a random phone number
                $customer->ip_address = '0.0.0.0';
                $customer->created_by = 7;
                $customer->created_by_user_type = 'Admin';
                $customer->save();

                if (isset($slot->id)) {
                    $findSlotId = $slot->id;

                    $tourists = $val['Qty'];
                    $total = $val['Amount'];
                    $serviceCommission = 0; // roundout($val['Amount'] * $package->stripe_commission_percentage / 100, 2);
                    $ticketCost = $val['Amount'] - $serviceCommission;

                    $booking = new TourBooking();
                    $booking->tour_package_id = $findPackageId;
                    $booking->tour_slot_id = $findSlotId;
                    $booking->customer_id = $customer->id;
                    $booking->group_id = $val['OrderNo'];
                    $booking->name = $customer->name;
                    $booking->email = $customer->email;
                    $booking->phone_number = $customer->phone_number;
                    $booking->tourists = $tourists;
                    $booking->service_commission = $serviceCommission;
                    $booking->total = $total;
                    $booking->booking_total = $total;
                    $booking->external_booking_id = str_pad(random_int(1, 999999999999999999), 18, '0', STR_PAD_LEFT);
                    $booking->status = 'Booked';
                    $booking->visitor = '0.0.0.0';
                    $booking->request_from = 'IMPORT';
                    if ($package->expire_in_days > 0) {
                        $expireInDays = (int) $package->expire_in_days;
                        $booking->expire_at = Carbon::parse($date)->addDays($expireInDays);
                    }
                    $booking->created_by = 7;
                    $booking->created_by_user_type = 'Admin';
                    $booking->save();

                    if ($booking->save()) {
                        $packageRate = TourPackageRate::where('orderby', 1)->first();

                        $bookingTransaction = new BookingTransact();
                        $bookingTransaction->tour_booking_id = $booking->id;
                        $bookingTransaction->tour_package_rate_id = $packageRate->id;
                        $bookingTransaction->tourists = $tourists;
                        // $bookingTransaction->nat_fees_percentage = $package->service_commission_percentage;
                        // $bookingTransaction->stripe_fees_percentage = $package->stripe_commission_percentage;
                        $bookingTransaction->rate = $ticketCost / $tourists;
                        $bookingTransaction->total = $ticketCost;
                        $bookingTransaction->created_by = 7;
                        $bookingTransaction->created_by_user_type = 'Admin';
                        $bookingTransaction->save();

                        $transaction = new Transaction();
                        $transaction->tour_booking_id = $booking->id;
                        $transaction->amount = $total;
                        // $transaction->fee_amount = $serviceCommission;
                        $transaction->via = 'Cash';
                        $transaction->status = 'Completed';
                        $transaction->visitor = '0.0.0.0';
                        $transaction->request_from = 'IMPORT';
                        $transaction->created_by = 7;
                        $transaction->created_by_user_type = 'Admin';
                        $transaction->save();

                        $history = new TourBookingHistory();
                        $history->tour_booking_id = $booking->id;
                        $history->tourists = $tourists;
                        $history->operation = 'Added';
                        $history->details = json_encode([
                            "sub_operation" => "Tourists Added",
                            "discount_percentage" => 0,
                            "discount2_value" => 0,
                            "added" => ["Guest person $tourists"],
                            "newTourists" => $tourists,
                            "created_by_with_name" => "Lane Gilmore: (Admin)",
                        ]);
                        $history->operation_at = date("Y-m-d H:i:s");
                        $history->operation_by = 7;
                        $history->operation_by_user_type = 'Admin';
                        $history->save();

                        $history = new TourBookingHistory();
                        $history->tour_booking_id = $booking->id;
                        $history->transaction_id = $transaction->id;
                        $history->tourists = $tourists;
                        $history->operation = 'Payment';
                        $history->ticket_cost = $ticketCost;
                        $history->payment_type = 'Cash';
                        $history->payment_method = 'Full Payment';
                        $history->details = json_encode([
                            "amount" => $total,
                            "payment_type" => "Cash",
                            "created_by_with_name" => "Lane Gilmore: (Admin)",
                            "payment_method" => "Full Payment",
                        ]);
                        $history->operation_at = date("Y-m-d H:i:s");
                        $history->operation_by = 7;
                        $history->operation_by_user_type = 'Admin';
                        $history->save();

                        TimeSlotAvailabilityService::updateSlotSummary($booking->tour_slot_id, $booking->tourists);
                    } else {
                        dump("No booking found");
                    }
                } else {
                    dump("No slot found");
                }
            } else {
                dump("No package found");
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}
