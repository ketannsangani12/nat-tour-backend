<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tour_operators')->exists()) {
            return;
        }

        DB::table('tour_operators')->insert([
            'id'                              => 1,
            'tenant_id'                       => 'nat',
            'city_id'                         => 1,
            'name'                            => 'Native American Tours',
            'shortdesc'                       => null,
            'email'                           => 'book@example.com',
            'phone_number'                    => '8888689378',
            'website'                         => 'https://nativeamericantours.com',
            'description'                     => 'That\'s just one of the amazing experiences you\'ll have at the West Rim of the Canyon. Explore all of the bucket list attractions and start planning your visit.',
            'cancellation_policy'             => '',
            'cancellation_within_days'        => 1,
            'stripe_webhook_secret'           => null,
            'stripe_terminal_location_id'     => null,
            'custom_checkout_terms'           => null,
            'statement_descriptor'            => 'NAT',
            'card_reader_prefix'              => 'NAT',
            'logo'                            => null,
            'logo_navigation'                 => null,
            'logo_print'                      => null,
            'address'                         => '5001 Diamond Bar Rd',
            'postal_code'                     => '86434',
            'timezone'                        => 'US/Arizona',
            'social_media_urls'               => null,
            'seo_title'                       => null,
            'seo_description'                 => null,
            'seo_keywords'                    => null,
            'reports'                         => null,
            'checkins'                        => null,
            'is_affiliate'                    => null,
            'service_commission_percentage'   => 0.00,
            'stripe_commission_percentage'    => 0.0000,
            'affiliate_processing_percentage' => 0.00,
            'stripe_affiliate_percentage'     => 0.0000,
            'status'                          => 'Active',
            'stripe_destination_id'           => null,
            'stripe_invoice_enabled'          => 0,
            'tripadvisor_supplier_id'         => null,
            'tripadvisor_reseller_id'         => null,
            'supplier_id'                     => null,
            'get_your_guide_supplier_id'      => null,
            'created_at'                      => '2024-03-25 16:54:37',
            'created_by'                      => 1,
            'updated_at'                      => '2025-02-03 10:13:16',
            'updated_by'                      => 7,
            'updated_by_user_type'            => 'Admin',
            'deleted_at'                      => null,
            'deleted_by'                      => 0,
            'deleted_by_user_type'            => 'Tour Operator',
        ]);
    }

    public function down(): void
    {
        DB::table('tour_operators')->where('id', 1)->delete();
    }
};
