<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Set admins
        DB::table('admins')->insert([
            [
                'name' => 'Native American Tours',
                'email' => 'info@nativeamericantours.com',
                'password' => Hash::make("12345678")
            ],
            [
                'name' => 'Wyatt Gilmore',
                'email' => 'wyattg@nativeamericantours.com',
                'password' => Hash::make("12345678")
            ],
            [
                'name' => 'Lane Gilmore',
                'email' => 'lane@nativeamericantours.com',
                'password' => Hash::make("12345678")
            ],
            [
                'name' => 'Scott Dean',
                'email' => 'scott@nativeamericantours.com',
                'password' => Hash::make("12345678")
            ],
            [
                'name' => 'Lane Gilmore',
                'email' => 'noikes@gmail.com',
                'password' => Hash::make("12345678")
            ]
        ]);

        // Set countries
        DB::table('countries')->insert([
            [
                'name' => 'United States',
                'code' => 'US',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'phone_code' => '1',
                'locale' => 'en-US',
                'created_by' => 1
            ],
            [
                'name' => 'Canada',
                'code' => 'CA',
                'currency' => 'CAD',
                'currency_symbol' => 'CA$',
                'phone_code' => '1',
                'locale' => 'en-CA',
                'created_by' => 1
            ],
        ]);

        // Default state and city — required for tour_operator city_id FK and controller JOINs
        DB::table('states')->insert([
            'country_id' => 1,
            'name'       => 'Arizona',
            'permit_fee' => 8,
            'created_by' => 1,
        ]);
        DB::table('cities')->insert([
            'state_id'   => 1,
            'name'       => 'Page',
            'timezone'   => 'America/Phoenix',
            'created_by' => 1,
        ]);

        // Set states for USA
        // DB::table('states')->insert([
        //     ['country_id' => 1, 'name' => 'Howland Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Delaware', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Alaska', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Maryland', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Baker Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Kingman Reef', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'New Hampshire', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Wake Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Kansas', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Texas', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Nebraska', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Vermont', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Jarvis Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Hawaii', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Guam', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'United States Virgin Islands', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Utah', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Oregon', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'California', 'permit_fee' => 5, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'New Jersey', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'North Dakota', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Kentucky', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Minnesota', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Oklahoma', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Pennsylvania', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'New Mexico', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'American Samoa', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Illinois', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Michigan', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Virginia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Johnston Atoll', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'West Virginia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Mississippi', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Northern Mariana Islands', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'United States Minor Outlying Islands', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Massachusetts', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Arizona', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Connecticut', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Florida', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'District of Columbia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Midway Atoll', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Navassa Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Indiana', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Wisconsin', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Wyoming', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'South Carolina', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Arkansas', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'South Dakota', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Montana', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'North Carolina', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Palmyra Atoll', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Puerto Rico', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Colorado', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Missouri', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'New York', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Maine', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Tennessee', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Georgia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Alabama', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Louisiana', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Nevada', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Iowa', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Idaho', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Rhode Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 1, 'name' => 'Washington', 'permit_fee' => 8, 'created_by' => 1],
        // ]);

        // Set states for Canada
        // DB::table('states')->insert([
        //     ['country_id' => 2, 'name' => 'Ontario', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Manitoba', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'New Brunswick', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Yukon', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Saskatchewan', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Prince Edward Island', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Alberta', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Quebec', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Nova Scotia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'British Columbia', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Nunavut', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Newfoundland and Labrador', 'permit_fee' => 8, 'created_by' => 1],
        //     ['country_id' => 2, 'name' => 'Northwest Territories', 'permit_fee' => 8, 'created_by' => 1],
        // ]);

        // Set cities
        // DB::table('cities')->insert([
        //     [
        //         'state_id'   => 1,
        //         'name'       => 'San Jose',
        //         'timezone'   => 'America/Los_Angeles',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 2,
        //         'name'       => 'Lake Powell Blvd',
        //         'timezone'   => 'America/Phoenix',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 2,
        //         'name'       => 'LeChee',
        //         'timezone'   => 'America/Phoenix',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 3,
        //         'name'       => 'Monument Valley',
        //         'timezone'   => 'America/Denver',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 4,
        //         'name'       => 'Las Vegas',
        //         'timezone'   => 'America/Los_Angeles',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 4,
        //         'name'       => 'Page',
        //         'timezone'   => 'America/Phoenix',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 4,
        //         'name'       => 'Chinle',
        //         'timezone'   => 'America/Phoenix',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 4,
        //         'name'       => 'Kaibeto',
        //         'timezone'   => 'America/Phoenix',
        //         'created_by' => 1
        //     ],
        //     [
        //         'state_id'   => 4,
        //         'name'       => 'Bowie',
        //         'timezone'   => 'America/Denver',
        //         'created_by' => 1
        //     ]
        // ]);

        // Set settings
        DB::table('settings')->insert([
            [
                'key' => 'API_VERSION',
                'value' => 7.5,
                'order_number' => 1,
                'comment' => 'NAT App Version Number iOS'
            ],
            [
                'key' => 'IS_NO_HIDE',
                'value' => 1,
                'order_number' => 2,
                'comment' => '0 - False, 1 - True : NAT Force Update'
            ],
            [
                'key' => 'KIOSK_API_VERSION',
                'value' => 1.1,
                'order_number' => 3,
                'comment' => 'KIOSK App Version Number iOS'
            ],
            [
                'key' => 'KIOSK_FORCE_UPDATE',
                'value' => 1,
                'order_number' => 4,
                'comment' => '0 - False, 1 - True : KIOSK Force Update'
            ],
            [
                'key' => 'AGENDA_VIEW',
                'value' => 1,
                'order_number' => 5,
                'comment' => '1 - Time Slots, 2 - Hotels'
            ],
            [
                'key' => 'SIGNED_RECEIPT',
                'value' => 0,
                'order_number' => 8,
                'comment' => '0 - Disable, 1 - Enable : Signed Receipt'
            ],
            [
                'key' => 'REMINDER_EMAIL_DAYS',
                'value' => 7,
                'order_number' => 9,
                'comment' => 'Enter Comma Separated Days For Booking Reminder Email. 0 = Disable'
            ],
            [
                'key' => 'AUTO_CHECKINS',
                'value' => 0,
                'order_number' => 10,
                'comment' => 'Number of Minutes Before Tour Time For Auto Checkins'
            ],
            [
                'key' => 'CART_VIEW',
                'value' => 0,
                'order_number' => 11,
                'comment' => 'No - Disable, Yes - Enable : Add To Cart System'
            ],
            [
                'key' => 'OLD_BOOKING_ID',
                'value' => 0,
                'order_number' => 12,
                'comment' => 'Old Booking ID'
            ],
            [
                'key' => 'CASH_FEES',
                'value' => 0,
                'order_number' => 13,
                'comment' => 'No - Disable, Yes - Enable : Cash fees to system'
            ],
            [
                'key' => 'BOOKING_ID_PREFIX',
                'value' => 'N-',
                'order_number' => 14,
                'comment' => 'To distinguish corporate reservations, append the prefix to the booking ID'
            ],
            [
                'key' => 'INVOICE_ENABLE_DISABLE',
                'value' => 0,
                'order_number' => 15,
                'comment' => 'No - Disable, Yes - Enable : Invoice'
            ],
            [
                'key' => 'SOLDOUT_SLOT_VISIBILITY',
                'value' => 1,
                'order_number' => 16,
                'comment' => 'No - Disable, Yes - Enable : Frontend Slot Visibility'
            ],
            [
                'key' => 'HOTEL_PICKUP',
                'value' => 0,
                'order_number' => 17,
                'comment' => '0 - No, 1 - Yes : Enable Hotel Pickup'
            ],
            [
                'key' => 'CUSTOM_FIELDS',
                'value' => 0,
                'order_number' => 18,
                'comment' => '0 - Disable, 1 - Enable : Custom Add-ons'
            ],
            [
                'key' => 'MIN_MAX_APPLY',
                'value' => 1,
                'order_number' => 19,
                'comment' => '1 - Both, 2 - Backend, 3 - Frontend'
            ],
            [
                'key' => 'PACKAGE_RELATION',
                'value' => 0,
                'order_number' => 20,
                'comment' => '0 - Disable, 1 - Enable : Package Relation'
            ],
            [
                'key' => 'SWITCH_PACKAGE',
                'value' => 0,
                'order_number' => 21,
                'comment' => 'No - Disable, Yes - Enable : Change package in bookings'
            ],
            [
                'key' => 'CUSTOM_CHARGE',
                'value' => 0,
                'order_number' => 22,
                'comment' => 'No - Disable, Yes - Enable : Custom Charge In Booking'
            ],
            [
                'key' => 'SLOT_VISIBILITY',
                'value' => 1,
                'order_number' => 23,
                'comment' => 'No - Disable, Yes - Enable : Slot Visibility'
            ],
            [
                'key' => 'CALL_TO_BOOK',
                'value' => 0,
                'order_number' => 24,
                'comment' => 'No - Disable, Yes - Enable : Call To Book'
            ],
            [
                'key' => 'CDR_PACKAGES',
                'value' => 0,
                'order_number' => 25,
                'comment' => 'No - Disable, Yes - Enable : Custom Date Rates For Packages'
            ],
            [
                'key' => 'CDR_AFFILIATES',
                'value' => 0,
                'order_number' => 26,
                'comment' => 'No - Disable, Yes - Enable : Custom Date Rates For Affiliates'
            ],
            [
                'key' => 'DEPARTMENT_PACKAGE',
                'value' => 0,
                'order_number' => 27,
                'comment' => 'No - Disable, Yes - Enable : Enable department package feature'
            ],
            [
                'key' => 'MANAGER_REQUEST',
                'value' => 0,
                'order_number' => 28,
                'comment' => 'No - Disable, Yes - Enable : Enable manager request feature'
            ],
            [
                'key' => 'PAYMENT_LINK',
                'value' => 0,
                'order_number' => 29,
                'comment' => 'No - Disable, Yes - Enable : Enable payment link feature'
            ],
            [
                'key' => 'PAYMENT_LINK_DAYS',
                'value' => 0,
                'order_number' => 30,
                'comment' => 'Enter Days Before Tour Date To Expire Payment Link'
            ],
            [
                'key' => 'POS_VIEW',
                'value' => 0,
                'order_number' => 31,
                'comment' => 'No - Disable, Yes - Enable : POS View'
            ],
            [
                'key' => 'CUSTOMER_INFO_MANDATORY',
                'value' => 0,
                'order_number' => 32,
                'comment' => 'No - Disable, Yes - Enable : Customer info required in POS'
            ],
            [
                'key' => 'AFFILIATE_PAYMENT_METHODS',
                'value' => 0,
                'order_number' => 33,
                'comment' => 'Affiliate Payment Methods'
            ],
            [
                'key' => 'EMPLOYEES_PAYMENT_METHODS',
                'value' => 0,
                'order_number' => 34,
                'comment' => 'Employees Payment Methods'
            ],
            [
                'key' => 'BOARD',
                'value' => 0,
                'order_number' => 35,
                'comment' => 'No - Disable, Yes - Enable : Enable board'
            ],
            [
                'key' => 'AGENDA_CHECKIN_OVERVIEW',
                'value' => 0,
                'order_number' => 36,
                'comment' => 'No - Disable, Yes - Enable : Enable agenda check-in overview'
            ],
            // AGENDA_GUIDE_COUNT and DISABLE_CARD_SAVE are inserted by migrations

            [
                'key' => 'DISABLE_AFFILIATE_CARD_SAVE',
                'value' => 0,
                'order_number' => 40,
                'comment' => 'No - Disable, Yes - Enable : Enable DISABLE_AFFILIATE_CARD_SAVE'
            ],
            [
                'key' => 'AGENDA_AMOUNT',
                'value' => 0,
                'order_number' => 45,
                'comment' => 'No - Disable, Yes - Enable : Amount Visibility In Agenda'
            ],
            [
                'key' => 'BLACKOUT_DATES_ENABLED',
                'value' => 0,
                'order_number' => 46,
                'comment' => 'No - Disable, Yes - Enable : Blackout dates'
            ],
            // AUTO_PAYOUT and CASH_PRICE are inserted by migrations

        ]);

        // Set tour_operators
        DB::table('tour_operators')->insert([
            'city_id'     => 1,
            'timezone'    => 'America/Phoenix',
            'name'        => 'Example Tours',
            'email'       => 'book@example.com',
            'phone_number' => '9898989898',
            'website'     => 'example.com',
            'description' => 'Example Info',
            'created_by'  => 1,
        ]);

        // Set tour_operator_staff
        // DB::table('tour_operator_staff')->insert([
        //     [
        //         'tour_operator_id' => 1,
        //         'first_name' => "Wyatt",
        //         'last_name' => "Gilmore",
        //         'email' => 'wyattg@nativeamericantours.com',
        //         'password' => Hash::make("12345678"),
        //         'phone_number' => '9898989898',
        //         'created_by' => 1,
        //         'created_by_user_type' => 'Admin'
        //     ],
        //     [
        //         'tour_operator_id' => 1,
        //         'first_name' => "Lane",
        //         'last_name' => "Gilmore",
        //         'email' => 'noikes@gmail.com',
        //         'password' => Hash::make("12345678"),
        //         'phone_number' => '9898989898',
        //         'created_by' => 1,
        //         'created_by_user_type' => 'Admin'
        //     ],
        //     [
        //         'tour_operator_id' => 1,
        //         'first_name' => "Scott",
        //         'last_name' => "Dean",
        //         'email' => 'scott@nativeamericantours.com',
        //         'password' => Hash::make("12345678"),
        //         'phone_number' => '9898989898',
        //         'created_by' => 1,
        //         'created_by_user_type' => 'Admin'
        //     ]
        // ]);
    }
}
