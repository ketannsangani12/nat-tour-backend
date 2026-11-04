<?php

return [
    'email_template' => [
        'page_title' => 'Email Template',
        'alias' => [
            'tour_booking_acknoledgement' => 'New Booking Template',
            'tour_booking_cancelled' => 'Booking Cancellation Template',
            'people_remove_from_booking' => 'People Removed From Booking Template',
            'booking_rescheduled' => 'Rescheduled Booking Template',
            'tip_for_customer' => 'Tip For Customer Template',
            'tip_for_guide' => 'Tip For Guide Template',
            'tour_booking_refund' => 'Tour Booking Refund',
            'reminder_before_seven_days' => 'Reminder Before 7 days',
            'lower_antelope_canyon_closure' => 'Lower Antelope Canyon Closure',
            'pos_order_receipt' => 'POS Order Receipt',
            'payment_link' => 'Payment Link Template',
            'news_letter' => 'News Letter',
            'new_payment_link' => 'New Payment Link Template',
             'tip_payment_link' => 'Tip Payment Link Template',
            'tap_to_pay_payment' => 'Tap To Pay Payment Template',
            'post_tip' => 'Post Tip Template'
        ],
        'class' => [
            'App\Models\TourBooking' => 'Tour Booking',
        ],
        'tags' => [
            'App\Models\TourBooking' => [
                '{tour_operator}',
                '{tour_operator_logo}',
                '{tour_operator_phone}',
                '{tour_operator_email}',
                '{tour_operator_website}',
                '{url_booking_info}',
                '{receiver_name}',
                '{customer_phone}',
                '{customer_email}',
                '{booking_id}',
                '{tour_package_photo}',
                '{tour_package}',
                '{tour_date}',
                '{tour_date_time}',
                '{arrival_time}',
                '{number_of_tourist}',
                '{tour_list_with_seat }',
                '{deleted_for}',
                '{deleted_people}',
                '{url_liability_waiver}',
                '{important_notes}',
                '{things_to_bring}',
                '{card_number}',
                '{cancellation_policy}',
                '{cancellation_url}',
                '{guide_name}',
                '{customer_head}',
                '{map}',
                '{refunded_amount}',
                '{rescheduled_date_time}',
                '{guide_phone}',
                '{guide_email}',
                '{cust_pay_amount}',
                '{current_date}',
                '{qr_code}',
                '{hotel}',
                '{hotel_address}',
                '{hotel_location}',
                '{tour_package_image}',
                '{reason_of_payment}',
                '{payment_link_url}',
                '{expired_at}',
                '{payment_status}'
            ]
        ]
    ],
    'tour_package_email_template' => [
        'page_title' => 'Email Templates For Package'
    ],
    'invoice_status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'viewed' => 'Viewed',
        'approved' => 'Approved',
        'partial' => 'Partial',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'unpaid' => 'Unpaid',
        'cancelled' => 'Cancelled'
    ],
    'stripe_keys' => [
        'local' => [
            [
                'public_key' => env('STRIPE_API_PUBLIC_KEY', 'pk_test_51KGGWAFWb9Y3fHFESHkUfEqAwitemWWsiUfpiMUgY5FE3H1x2UuXnSKyrhJmY2SINV1vA79LMvtqtoHfOL1bUJJM00uJzlRQPY'),
                'secret_key' => env('STRIPE_API_SECRET_KEY')
            ]
        ],
        'development' => [
            [
                'public_key' => env('STRIPE_API_PUBLIC_KEY', 'pk_test_51KGGWAFWb9Y3fHFESHkUfEqAwitemWWsiUfpiMUgY5FE3H1x2UuXnSKyrhJmY2SINV1vA79LMvtqtoHfOL1bUJJM00uJzlRQPY'),
                'secret_key' => env('STRIPE_API_SECRET_KEY')
            ]
        ],
        'staging' => [
            [
                'public_key' => env('STRIPE_API_PUBLIC_KEY', 'pk_test_51KGGWAFWb9Y3fHFESHkUfEqAwitemWWsiUfpiMUgY5FE3H1x2UuXnSKyrhJmY2SINV1vA79LMvtqtoHfOL1bUJJM00uJzlRQPY'),
                'secret_key' => env('STRIPE_API_SECRET_KEY')
            ]
        ],
        'production' => [
            [
                'public_key' => env('STRIPE_API_PUBLIC_KEY', null),
                'secret_key' => env('STRIPE_API_SECRET_KEY', null)
            ]
        ]
    ],
    'old_bookings' => [
        'booking_id' => env('OLD_BOOKING_ID', '103719'),
    ],
    'email_types' => [
        'lower_antelope_canyon_closure' => 1,
        'tour_booking_acknowledgement' => 2,
        'tour_booking_cancelled' => 3,
        'booking_rescheduled' => 4,
        'reminder_before_seven_days' => 5,
        'tour_booking_refund' => 6,
        'people_remove_from_booking' => 7,
        'tip_for_customer' => 8,
        'tip_for_guide' => 9,
        'custom_email' => 10,
        'pos_order_receipt' => 11,
        'payment_link' => 12,
        'tip_payment_link' => 13,
        'tap_to_pay_payment' => 14,
        'post_tip' => 15
    ],
    'stripe_destination_id' => 'acct_1KiPPfF2s9WqF2Qr', // OLD DIXIE STRIPE DESTINATION ID
    'payment_sections' => [
        'new_booking' => 1,
        'make_payment' => 2,
        'change_people' => 3,
        'tip_payment' => 4,
        'discount_payment' => 5,
        'payment_link' => 6
    ],
    'prevent_sending_emails' => [
        'anil@gmail.com'
    ],
    'database' => [
        'local' => 'nat_central',
        'development' => 'nat_central',
        'staging' => 'nat_central',
        'production' => 'our_admin'
    ],
    'environment' => [
        'local' => '.tour-backend.test',
        'development' => '.ntv.tours',
        'staging' => '.testingnat.com',
        'production' => '.nativeamericantours.com'
    ],
    'environment_iframe' => [
        'local' => 'http://localhost:8080/?',
        'development' => 'https://book.ntv.tours/?',
        'staging' => 'https://book.testingnat.com/?',
        'production' => 'https://book.nativeamericantours.com/?'
    ],
    'surcharge_dates' => [
        '2024-01-01',
        '2024-05-24',
        '2024-05-25',
        '2024-05-26',
        '2024-05-27',
        '2024-07-01',
        '2024-07-02',
        '2024-07-03',
        '2024-07-04',
        '2024-07-05',
        '2024-07-06',
        '2024-07-07',
        '2024-08-30',
        '2024-08-31',
        '2024-09-01',
        '2024-09-02',
        '2024-11-24',
        '2024-11-25',
        '2024-11-26',
        '2024-11-27',
        '2024-11-28',
        '2024-11-29',
        '2024-11-30',
        '2024-12-22',
        '2024-12-23',
        '2024-12-24',
        '2024-12-25',
        '2024-12-26',
        '2024-12-27',
        '2024-12-28',
        '2024-12-29',
        '2024-12-30',
        '2024-12-31'
    ],
    'agenda_view' => [
        'slot_wise' => 1,
        'hotel_wise' => 2
    ],
    'categories' => [
        1 => 'Tour',
        2 => 'Stay',
        3 => 'Meal',
        4 => 'Watercraft'
    ],
    'release' => [
        'page_title' => "What's New",
    ],
    'payment_methods' => [
        'Card' => 'card',
        'Cash' => 'cash',
        'Pay Later' => 'paylater',
        'Invoiced' => 'invoiced',
        'Billable' => 'billable',
    ],
    'default_customers' => [
        'gcw' => [
            'name' => 'POS',
            'email' => 'pos@grandcanyonwest.com'
        ],
    ],
    'mapping_keys' => [
        0 => 'Default',
        1 => 'Adults',
        2 => 'Children',
        3 => 'Infant',
        4 => 'Adults - Military Teachers',
        5 => 'Children - Military Teachers',
        6 => 'Native Americans',
        7 => 'Locals',
        8 => 'Tour Guides',
        9 => 'Veteran',
        10 => 'Senior Citizen'
    ],
    'request_permissions' => [
        'Cancel' => 'No',
        'Refund' => 'No',
        'Reschedule' => 'No',
        'Refund Custom Charge' => 'No',
        'Ticket Redeem' => 'No',
        'Check-ins' => 'No',
        'Discount' => 'No',
        'Promo Code' => 'No',
        'Fix Discount' => 'No',
        'Percent Discount' => 'No',    
        'Switch' => 'No',
        'Rebook' => 'No',
        'Closure Email' => 'No',
        'Delete' => 'No',
        'Permit' => 'No',
        'Change' => 'No',
        'Split Booking' => 'No',
        'Refund Addons' => 'No',
        'Tax On Custom Charge' => 'No',
        'POS Cancel Order' => 'No',
        'POS Refund Order' => 'No',
    ],
    'policy_mappings' => [
        'Cancel' => 'cancel-booking',
        'Refund' => 'refund-booking',
        'Reschedule' => 'reschedule-booking',
        'Refund Custom Charge' => 'refund-custom-charge',
        'Ticket Redeem' => 'ticket-redeem',
        'Check-ins' => 'check-ins',
        'Discount' => 'discount',
        'Promo Code' => 'promo-code',
        'Fix Discount' => 'fix-discount',
        'Percent Discount' => 'percent-discount',    
        'Switch' => 'switch',
        'Rebook' => 'rebook',
        'Closure Email' => 'closure-email',
        'Delete' => 'delete',
        'Permit' => 'permit',
        'Change' => 'change',
        'Split Booking' => 'split-booking',
        'Refund Addons' => 'refund-addons',
        'Tax On Custom Charge' => 'tax-on-custom-charge',
        'POS Cancel Order' => 'pos-cancel-order',
        'POS Refund Order' => 'pos-refund-order',
    ],
    'pos_permissions_mappings' => [
        'Cancel' => 'POS Cancel Order',
        'Refund' => 'POS Refund Order'
    ],
    'agenda_exclude_fields' => [
         'is_affiliate', 'is_reserved_for_affiliate', 'tripadvisor_id', 'viator_bookingRef', 
         'hotel_address', 'hotel_name', 'reference_booking', 'cart_id', 'cartBookingsCount',
         'liability_waiver_signing', 'is_group_booking', 'date', 'time_from', 'time_to', 
         'package_has_slots', 'package_has_waiver', 'package_has_permit', 'bundle_package', 
         'is_check_in', 'checkin_type', 'hours', 'has_contacts', 'booking_status', 
         'payment_status', 'booked_seats', 'packages', 'afl_seats', 'get_your_guide_booking_id',
         'affiliateFeature', 'package_list', 'redeemed_tickets', 'not_redeemed_tickets', 
         'bookingid', 'packageid', 'touristsAgeInfo', 'groupRates', 'checkInsTotal', 
         'notCheckInsTotal', 'allNotCheckedIn', 'allFirstCheckedIn', 'checkInsCount', 'bookingCheckIn',
         'get_your_guide_reference_booking', 'ota_reference_booking'
    ],
    'payment_link_reasons' => [
        'create_booking' => <<<TEXT
            A payment link has been generated for {tour_name}, which includes {pax_details}, with a total amount of {amount}.

            This link is valid until {expiration_date} at {expiration_time}. After that, it will automatically expire for security reasons.
            TEXT,
        'resend_link' => <<<TEXT
            A payment link has been generated for {tour_name} booking #{booking_id}, which includes {pax_details}, with a total amount of {amount}.

            This link is valid until {expiration_date} at {expiration_time}. After that, it will automatically expire for security reasons.
            TEXT,
        'create_combo_booking' => <<<TEXT
            A payment link has been generated a total amount of {amount}.

            This link is valid until {expiration_date} at {expiration_time}. After that, it will automatically expire for security reasons.

            TEXT,
         
    ],
    // IMPORTANT — do NOT rename 'title' or 'prefix' after bookings exist for that OTA.
    // Both values are written into tour_booking_history.payment_type and tour_payment_transactions.via
    // at booking creation time. Renaming them will silently break the OTA report (payment_type no longer
    // matches the filter list) and the booking-info button-disable logic (via !== ota_title) for all
    // previously created bookings. If a rename is unavoidable, migrate the existing DB records to match.
    //
    // logo: filename of the OTA's logo image stored in public/images/otas/.
    //       Use a consistent extension across all OTAs (e.g. all .png or all .jpg) — mixed extensions work but are harder to manage.
    'otas' => [
        [
            'prefix' => 'GYG',
            'alias' => ['getyourguide', 'getyourguide.com', 'get your guide'],
            'title' => 'GetYourGuide',
            'logo' => 'getyourguide.png'
        ],
        [
            'prefix' => 'TRIP',
            'alias' => ['viator', 'tripadvisor', 'viator.com', 'tripadvisor.com'],
            'title' => 'TripAdvisor',
            'logo' => 'tripadvisor.png'
        ],
        [
            'prefix' => 'HEAD',
            'alias' => ['headout', 'headout.com'],
            'title' => 'Headout',
            'logo' => 'headout.png'
        ],
        [
            'prefix' => 'CHD', // booking prefix for CHD bookings
            'alias'  => ['chd', 'national park express', 'national park express chd'], // MUST match the affiliate's ota_id
            'title'  => 'CHD', // CHD's official/online name (shown in NAT)
                    // It should be the official reseller name, but if the affiliate is created with a different name, then use the affiliate name (e.g., CHD) or create the affiliate name to match the original reseller name, such as “National Park Express CHD.” I would not recommend using two different names for the reseller and affiliate, as this would affect booking management and reporting in our system.
            'logo'   => 'chd.png'
        ],

        [
            'prefix' => 'LC', // booking prefix for Lagunacreek
            'alias'  => ['LC', 'Lagunacreek', 'Laguna Creek'], // MUST match the affiliate's ota_id
            'title'  => 'Lagunacreek', // Lagunacreek's official/online name (shown in NAT)
            'logo'   => 'lagunacreek.png'
        ],


        [ // Mostly for test bookings created through Bokun or APIs when no OTA channel is specified in the request
            'prefix' => 'BOKUNTEST',
            'title'  => 'Bokun',
            'logo' => 'bokun.png'
        ],
        
        [ // For test bookings made via the OCTO API when no known Reseller(OTA) alias is matched
            'prefix' => 'OCTOTEST',
            'alias'  => ['octo', 'octo.travel', 'octo api'],
            'title'  => 'Octo',
            'logo'   => 'octo.png'
        ]
    ]
 ];    
