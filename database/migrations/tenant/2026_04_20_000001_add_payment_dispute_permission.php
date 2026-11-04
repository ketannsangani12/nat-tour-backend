<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $guards = ['admin', 'tour_operator_staff'];

        foreach ($guards as $guard) {
            Permission::firstOrCreate(
                ['name' => 'view-payment-dispute', 'guard_name' => $guard],
                [
                    'display_name' => 'View Payment Disputes',
                    'description'  => 'Access the Payment Dispute feature and menu',
                    'category'     => 'Profile',
                    'created_from' => 'BACKEND',
                    'created_by' => null,
                    'created_at'   => Carbon::now(),
                    'updated_at'   => Carbon::now()
                ]
            );
        }
    }

    public function down(): void
    {
        Permission::where('name', 'view-payment-dispute')->delete();
    }
};
