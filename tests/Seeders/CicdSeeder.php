<?php

namespace Tests\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\RoleSeeder;
use Carbon\Carbon;

class CicdSeeder extends Seeder
{
    /**
     * Seed minimal test data for CI/CD tenant.
     */
    public function run(): void
    {
        $today = Carbon::today();

        echo "Seeding CI/CD tenant with minimal data... \n";

        // 1. Admin
        DB::table('admins')->insertOrIgnore([
            'name' => 'CI Test Admin',
            'email' => 'ci@test.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Country
        DB::table('countries')->insertOrIgnore([
            'name' => 'United States',
            'code' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'phone_code' => '1',
            'locale' => 'en-US',
            'created_by' => 1,
        ]);

        // 3. State — TenantSeeder now inserts Arizona; only insert here if it didn't run
        if (DB::table('states')->count() === 0) {
            DB::table('states')->insert([
                'country_id' => 1,
                'name'       => 'Arizona',
                'permit_fee' => 8,
                'created_by' => 1,
            ]);
        }

        // 4. City — TenantSeeder now inserts Page; only insert here if it didn't run
        if (DB::table('cities')->count() === 0) {
            DB::table('cities')->insert([
                'state_id'   => 1,
                'name'       => 'Page',
                'timezone'   => 'America/Phoenix',
                'created_by' => 1,
            ]);
        }

        // 5. Settings (copied from TenantSeeder)
        DB::table('settings')->insertOrIgnore([
            ['key' => 'API_VERSION', 'value' => '7.5', 'order_number' => 1, 'comment' => 'NAT App Version Number iOS'],
            ['key' => 'IS_NO_HIDE', 'value' => '1', 'order_number' => 2, 'comment' => '0 - False, 1 - True : NAT Force Update'],
            ['key' => 'KIOSK_API_VERSION', 'value' => '1.1', 'order_number' => 3, 'comment' => 'KIOSK App Version Number iOS'],
            ['key' => 'KIOSK_FORCE_UPDATE', 'value' => '1', 'order_number' => 4, 'comment' => '0 - False, 1 - True : KIOSK Force Update'],
            ['key' => 'AGENDA_VIEW', 'value' => '1', 'order_number' => 5, 'comment' => '1 - Time Slots, 2 - Hotels'],
            ['key' => 'SIGNED_RECEIPT', 'value' => '0', 'order_number' => 8, 'comment' => '0 - Disable, 1 - Enable : Signed Receipt'],
            ['key' => 'REMINDER_EMAIL_DAYS', 'value' => '7', 'order_number' => 9, 'comment' => 'Enter Comma Separated Days For Booking Reminder Email. 0 = Disable'],
            ['key' => 'AUTO_CHECKINS', 'value' => '0', 'order_number' => 10, 'comment' => 'Number of Minutes Before Tour Time For Auto Checkins'],
            ['key' => 'CART_VIEW', 'value' => '0', 'order_number' => 11, 'comment' => 'No - Disable, Yes - Enable : Add To Cart System'],
            ['key' => 'OLD_BOOKING_ID', 'value' => '0', 'order_number' => 12, 'comment' => 'Old Booking ID'],
            ['key' => 'CASH_FEES', 'value' => '0', 'order_number' => 13, 'comment' => 'No - Disable, Yes - Enable : Cash fees to system'],
            ['key' => 'BOOKING_ID_PREFIX', 'value' => 'N-', 'order_number' => 14, 'comment' => 'To distinguish corporate reservations, append the prefix to the booking ID'],
            ['key' => 'INVOICE_ENABLE_DISABLE', 'value' => '0', 'order_number' => 15, 'comment' => 'No - Disable, Yes - Enable : Invoice'],
            ['key' => 'SOLDOUT_SLOT_VISIBILITY', 'value' => '1', 'order_number' => 16, 'comment' => 'No - Disable, Yes - Enable : Frontend Slot Visibility'],
            ['key' => 'HOTEL_PICKUP', 'value' => '0', 'order_number' => 17, 'comment' => '0 - No, 1 - Yes : Enable Hotel Pickup'],
            ['key' => 'CUSTOM_FIELDS', 'value' => '0', 'order_number' => 18, 'comment' => '0 - Disable, 1 - Enable : Custom Add-ons'],
            ['key' => 'MIN_MAX_APPLY', 'value' => '1', 'order_number' => 19, 'comment' => '1 - Both, 2 - Backend, 3 - Frontend'],
            ['key' => 'PACKAGE_RELATION', 'value' => '0', 'order_number' => 20, 'comment' => '0 - Disable, 1 - Enable : Package Relation'],
            ['key' => 'SWITCH_PACKAGE', 'value' => '0', 'order_number' => 21, 'comment' => 'No - Disable, Yes - Enable : Change package in bookings'],
            ['key' => 'CUSTOM_CHARGE', 'value' => '0', 'order_number' => 22, 'comment' => 'No - Disable, Yes - Enable : Custom Charge In Booking'],
            ['key' => 'SLOT_VISIBILITY', 'value' => '1', 'order_number' => 23, 'comment' => 'No - Disable, Yes - Enable : Slot Visibility'],
            ['key' => 'CALL_TO_BOOK', 'value' => '0', 'order_number' => 24, 'comment' => 'No - Disable, Yes - Enable : Call To Book'],
            ['key' => 'CDR_PACKAGES', 'value' => '0', 'order_number' => 25, 'comment' => 'No - Disable, Yes - Enable : Custom Date Rates For Packages'],
            ['key' => 'CDR_AFFILIATES', 'value' => '0', 'order_number' => 26, 'comment' => 'No - Disable, Yes - Enable : Custom Date Rates For Affiliates'],
            ['key' => 'DEPARTMENT_PACKAGE', 'value' => '0', 'order_number' => 27, 'comment' => 'No - Disable, Yes - Enable : Enable department package feature'],
            ['key' => 'MANAGER_REQUEST', 'value' => '0', 'order_number' => 28, 'comment' => 'No - Disable, Yes - Enable : Enable manager request feature'],
            ['key' => 'PAYMENT_LINK', 'value' => '0', 'order_number' => 29, 'comment' => 'No - Disable, Yes - Enable : Enable payment link feature'],
            ['key' => 'PAYMENT_LINK_DAYS', 'value' => '0', 'order_number' => 30, 'comment' => 'Enter Days Before Tour Date To Expire Payment Link'],
            ['key' => 'POS_VIEW', 'value' => '0', 'order_number' => 31, 'comment' => 'No - Disable, Yes - Enable : POS View'],
            ['key' => 'CUSTOMER_INFO_MANDATORY', 'value' => '0', 'order_number' => 32, 'comment' => 'No - Disable, Yes - Enable : Customer info required in POS'],
            ['key' => 'AFFILIATE_PAYMENT_METHODS', 'value' => '0', 'order_number' => 33, 'comment' => 'Affiliate Payment Methods'],
            ['key' => 'EMPLOYEES_PAYMENT_METHODS', 'value' => '0', 'order_number' => 34, 'comment' => 'Employees Payment Methods'],
            ['key' => 'BOARD', 'value' => '0', 'order_number' => 35, 'comment' => 'No - Disable, Yes - Enable : Enable board'],
            ['key' => 'AGENDA_CHECKIN_OVERVIEW', 'value' => '0', 'order_number' => 36, 'comment' => 'No - Disable, Yes - Enable : Enable agenda check-in overview'],
            ['key' => 'AGENDA_GUIDE_COUNT', 'value' => '0', 'order_number' => 37, 'comment' => 'No - Disable, Yes - Enable : Enable agenda check-in overview'],
            ['key' => 'DISABLE_CARD_SAVE', 'value' => '0', 'order_number' => 39, 'comment' => 'No - Disable, Yes - Enable : Enable DISABLE CARD SAVE'],
            ['key' => 'DISABLE_AFFILIATE_CARD_SAVE', 'value' => '0', 'order_number' => 40, 'comment' => 'No - Disable, Yes - Enable : Enable DISABLE_AFFILIATE_CARD_SAVE'],
            ['key' => 'AGENDA_AMOUNT', 'value' => '0', 'order_number' => 45, 'comment' => 'No - Disable, Yes - Enable : Amount Visibility In Agenda'],
            ['key' => 'BLACKOUT_DATES_ENABLED', 'value' => '0', 'order_number' => 46, 'comment' => 'No - Disable, Yes - Enable : Blackout dates'],
            ['key' => 'CASH_PRICE', 'value' => '0', 'order_number' => 47, 'comment' => 'No - Disable, Yes - Enable : Enable Cash Price'],
        ]);

        // 5b. Category (required by POS)
        DB::table('categories')->insertOrIgnore([
            'name' => 'General',
            'slug' => 'general',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Tax
        DB::table('taxes')->insertOrIgnore([
            'state_id' => 1,
            'name' => 'AZ',
            'value' => 0,
            'value_type' => 'Percent',
            'created_by' => 1,
        ]);

        // 7. Tour Operator — TenantSeeder may have already inserted one with city_id = NULL
        // when the tenant was created. Update it instead of inserting a duplicate.
        $existingOperatorId = DB::table('tour_operators')->value('id');
        $cityId = DB::table('cities')->value('id') ?? 1;
        $operatorData = [
            'city_id'               => $cityId,
            'timezone'              => 'America/Phoenix',
            'name'                  => 'CI Test Tours',
            'email'                 => 'ci@test.com',
            'phone_number'          => '5551234567',
            'website'               => 'citest.example.com',
            'description'           => 'CI test tour operator',
            'stripe_destination_id' => 'acct_1MbB2tFQSbCtu8Yl',
            'statement_descriptor'  => 'CITEST',
            'created_by'            => 1,
        ];
        if ($existingOperatorId) {
            DB::table('tour_operators')->where('id', $existingOperatorId)->update($operatorData);
        } else {
            DB::table('tour_operators')->insert($operatorData);
        }

        // 8. Roles and Permissions
        $this->call(RoleSeeder::class);

        // 9. Tour Package
        $operatorId = DB::table('tour_operators')->value('id');
        DB::table('tour_packages')->insert([
            'tour_operator_id' => $operatorId,
            'name' => 'CI Test Tour',
            'duration' => 2,
            'status' => 'Active',
            'type' => 'Regular',
            'service_commission_percentage' => 9.82,
            'created_by' => 1,
            'created_by_user_type' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 10. Tour Package Rates
        DB::table('tour_package_rates')->insert([
            [
                'tour_package_id' => 1,
                'rate_for' => 'Adult',
                'age_from' => 18,
                'age_to' => 64,
                'rate' => 50.00,
                'primary_rate' => 50.00,
                'scheduling_year' => $today->year,
                'status' => 'Active',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tour_package_id' => 1,
                'rate_for' => 'Child',
                'age_from' => 3,
                'age_to' => 17,
                'rate' => 25.00,
                'primary_rate' => 25.00,
                'scheduling_year' => $today->year,
                'status' => 'Active',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tour_package_id' => 1,
                'rate_for' => 'Senior',
                'age_from' => 65,
                'age_to' => 99,
                'rate' => 40.00,
                'primary_rate' => 40.00,
                'scheduling_year' => $today->year,
                'status' => 'Active',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 11. Tour Slot Dates (today + 2 days, 20 seats each)
        $dates = [];
        for ($i = 0; $i < 3; $i++) {
            $dates[] = [
                'tour_package_id' => 1,
                'date' => $today->copy()->addDays($i)->toDateString(),
                'seats' => 20,
                'status' => 'Active',
                'created_by' => 1,
                'created_by_user_type' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('tour_slot_dates')->insert($dates);

        // 12. Tour Slot Times (3 time slots per date)
        $timeSlots = [
            ['from' => '09:00:00', 'to' => '11:00:00'],
            ['from' => '12:00:00', 'to' => '14:00:00'],
            ['from' => '15:00:00', 'to' => '17:00:00'],
        ];

        $slotDates = DB::table('tour_slot_dates')->get();
        $times = [];
        foreach ($slotDates as $slotDate) {
            foreach ($timeSlots as $slot) {
                $times[] = [
                    'tour_slot_id' => $slotDate->id,
                    'tour_package_id' => 1,
                    'date' => $slotDate->date,
                    'time_from' => $slot['from'],
                    'time_to' => $slot['to'],
                    'time_slot_seats' => 20,
                    'bookable_status' => 'Open',
                    'created_by' => 1,
                    'created_by_user_type' => 'Admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('tour_slot_times')->insert($times);
    }
}
