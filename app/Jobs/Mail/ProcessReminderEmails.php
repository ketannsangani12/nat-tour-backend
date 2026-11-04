<?php

namespace App\Jobs\Mail;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReminderEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    // Never retry — a retry would re-dispatch all email jobs.
    // TourBookingReminderMails uses insertOrIgnore to prevent duplicate emails,
    // but re-dispatching hundreds of jobs wastes resources.
    public $tries = 1;

    public function handle(): void
    {
        try {
            if (!isset(tenant()->id) || empty(tenant()->id) || tenant()->id === 'live') {
                return;
            }

            $setting = DB::table('settings')->where('key', 'REMINDER_EMAIL_DAYS')->first();
            if (!$setting || trim($setting->value) === '0') {
                return;
            }
            $days = explode(',', $setting->value);

            $today = date('Y-m-d');
            $dates = [];
            foreach ($days as $day) {
                $dates[] = date('Y-m-d', strtotime("+{$day} day", strtotime($today)));
            }

            // Shared NOT EXISTS subquery — checked against tour_booking_history to
            // skip bookings that already received a reminder email.
            $alreadySentSubquery = function ($query) {
                $query->select(DB::raw(1))
                    ->from('tour_booking_history as tbh')
                    ->whereRaw('tbh.tour_booking_id = tbm.id')
                    ->where('tbh.operation', 'Email')
                    ->where('tbh.details', 'LIKE', 'Reminder % days prior to booking');
            };

            // --- Step 1: lightweight pre-query to get package IDs + operator info ---
            // This avoids loading full row data just to discover which packages exist.
            $previewRows = DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
                ->whereIn('tsd.date', $dates)
                ->where('tbm.status', 'Booked')
                ->where('c.is_affiliate', 'No')
                ->whereNotNull('tbm.email')
                ->where('tbm.email', '!=', '')
                ->whereNotExists($alreadySentSubquery)
                ->select(['tsd.tour_package_id', 't_o.logo AS tour_op_logo', 't_o.tenant_id'])
                ->get();

            if ($previewRows->isEmpty()) {
                return;
            }

            $tenantId          = $previewRows->first()->tenant_id;
            $tourOpLogo        = $previewRows->first()->tour_op_logo;
            $allTourPackageIds = $previewRows->pluck('tour_package_id')->unique()->values()->toArray();

            $tourOperatorLogo = null;
            if (!empty($tourOpLogo)) {
                $tourOperatorLogo = tourOperatorLogo($tenantId, $tourOpLogo);
            }

            // --- Step 2: batch-load package logos and images (2 queries total) ---
            $allPackageLogos  = [];
            $allPackageImages = [];

            $tourPkgLogos = DB::table('tour_package_photos')
                ->whereIn('tour_package_id', $allTourPackageIds)
                ->where('placement', 2)
                ->where('status', 'Active')
                ->where('deleted_by', 0)
                ->latest()
                ->get(['tour_package_id', 'path']);

            foreach ($tourPkgLogos as $logo) {
                $allPackageLogos[$logo->tour_package_id] = tourPackageLogo($tenantId, $logo->tour_package_id, $logo->path);
            }

            $tourPkgImages = DB::table('tour_package_photos')
                ->whereIn('tour_package_id', $allTourPackageIds)
                ->where('placement', 7)
                ->where('status', 'Active')
                ->where('deleted_by', 0)
                ->latest()
                ->get(['tour_package_id', 'path']);

            foreach ($tourPkgImages as $image) {
                $allPackageImages[$image->tour_package_id] = getDiskUrl("images/tour-operator/$tenantId/package/{$image->tour_package_id}/" . $image->path);
            }

            foreach ($allTourPackageIds as $packageId) {
                if (!isset($allPackageImages[$packageId])) {
                    $allPackageImages[$packageId] = getDiskUrl("images/tour-operator/$tenantId/package/$packageId/images/no-photo.png");
                }
            }

            // --- Step 3: process bookings in chunks ---
            // chunkById avoids loading thousands of rows into memory at once.
            // The correlated booked_seats subquery has been removed — it scanned
            // tour_bookings_master once per result row but was never read by the job.
            DB::table('tour_bookings_master AS tbm')
                ->join('tour_slot_times AS tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
                ->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
                ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
                ->join('cities AS ct', 'ct.id', '=', 't_o.city_id')
                ->join('states AS st', 'st.id', '=', 'ct.state_id')
                ->whereIn('tsd.date', $dates)
                ->where('tbm.status', 'Booked')
                ->where('c.is_affiliate', 'No')
                ->whereNotNull('tbm.email')
                ->where('tbm.email', '!=', '')
                ->whereNotExists($alreadySentSubquery)
                ->select([
                    'tbm.id as tbm_id',
                    'tst.time_from',
                    'tst.time_to',
                    'tsd.date',
                    'tst.time_slot_seats as seats',
                    'tp.tour_operator_id',
                    'tsd.tour_package_id',
                    'tbm.tourists as total_tourists',
                    'tbm.infants as total_infants',
                    'tp.name AS package_name',
                    'tp.important_notes',
                    'tp.things_to_bring',
                    'ct.state_id',
                    'st.permit_fee',
                    'tbm.name as customer_name',
                    'tbm.email as customer_email',
                    'tbm.phone_number as customer_phone',
                    't_o.name AS tour_operator',
                    't_o.email AS tour_op_email',
                    't_o.phone_number AS tour_op_phone',
                    't_o.website AS tour_op_website',
                    't_o.logo AS tour_op_logo',
                    't_o.cancellation_policy',
                    'tp.service_commission_percentage',
                    't_o.id as tour_operator_id',
                    't_o.tenant_id',
                    'tp.stripe_destination_id',
                    'tp.email as packageEmail',
                    'tp.from_name as packageFromName',
                    'tp.from_email as packageFromEmail',
                    'tp.api_key as packageApiKey',
                ])
                ->chunkById(100, function ($chunk, $chunkIndex) use (
                    $tourOperatorLogo,
                    $allPackageLogos,
                    $allPackageImages
                ) {
                    // Batch-fetch rate/seat breakdown for all bookings in this chunk.
                    // Previously each TourBookingReminderMails job did this query
                    // individually — that was N queries for N bookings.
                    $bookingIds = $chunk->pluck('tbm_id')->toArray();

                    $transactsByBooking = DB::table('tour_bookings_transact as tbt')
                        ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                        ->whereIn('tbt.tour_booking_id', $bookingIds)
                        ->select('tbt.tour_booking_id', 'tpr.rate_for', 'tbt.tourists', 'tbt.infants')
                        ->get()
                        ->groupBy('tour_booking_id');

                    // Stagger dispatch: delay each chunk by 10 seconds so queue workers
                    // don't all attempt mail sends simultaneously.
                    $delay = $chunkIndex * 10;

                    foreach ($chunk as $tourBooking) {
                        $tourListwithSeat = $transactsByBooking->get($tourBooking->tbm_id, collect());

                        TourBookingReminderMails::dispatch(
                            $tourBooking,
                            $tourOperatorLogo,
                            $allPackageLogos,
                            $allPackageImages,
                            $tourListwithSeat
                        )->delay(now()->addSeconds($delay));
                    }
                }, 'tbm.id', 'tbm_id');

        } catch (Exception $e) {
            Log::error('ProcessReminderEmails job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
