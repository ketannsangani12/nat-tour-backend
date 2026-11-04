<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PriorityService
{
    public static function getPriorityNote(int $tourBookingId): ?object
    {
        $priorityNote = DB::table('tour_booking_history')
            ->where('tour_booking_id', $tourBookingId)
            ->where('operation', 'Priority Note')
            ->orderByDesc('created_at')
            ->first(['details', 'charge_type', 'operation_by', 'operation_by_user_type', 'operation_at']);

        if (!$priorityNote) {
            return null;
        }

        if ($priorityNote->operation_by_user_type === 'Admin') {
            $admin = DB::table('admins')->where('id', $priorityNote->operation_by)->first(['name', 'profile_photo_path']);
            $priorityNote->set_by_name  = $admin->name ?? 'Admin';
            $priorityNote->set_by_photo = $admin->profile_photo_path ?? null;
        } else {
            $staff = DB::table('tour_operator_staff')
                ->where('id', $priorityNote->operation_by)
                ->first(['first_name', 'last_name', 'profile_photo_path']);

            if ($staff) {
                $priorityNote->set_by_name = trim($staff->first_name . ' ' . $staff->last_name);
                if ($staff->profile_photo_path) {
                    $tenantId = tenancy()->tenant->id ?? null;
                    $priorityNote->set_by_photo = $tenantId
                        ? Storage::disk('s3')->url('images/tour-operator/' . $tenantId . '/users/' . $staff->profile_photo_path)
                        : null;
                } else {
                    $priorityNote->set_by_photo = null;
                }
            } else {
                $priorityNote->set_by_name  = DB::table('customers')->where('id', $priorityNote->operation_by)->value('name') ?? 'User';
                $priorityNote->set_by_photo = null;
            }
        }

        return $priorityNote;
    }
}
