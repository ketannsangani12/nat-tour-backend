<?php

namespace App\Jobs;

use App\Services\Booking\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends the reschedule email to the customer of every booking whose slot time was changed by a
 * "Convert Times" bulk operation.
 *
 * Dispatched by ApplyBulkOperationJob AFTER the slot rows are updated and committed, so the
 * (relatively slow) per-booking email build — a tour_bookings_transact join, package-photo lookups
 * and tenant_route() calls inside EmailService — no longer holds up the apply job or the
 * self-chained next operation.
 *
 * Each task is one converted slot's point-in-time reschedule: the slot id (for package/operator
 * email context), the unchanged date, the old and new start time, and the ids of the bookings that
 * were sitting on the slot at conversion time (those are the ones that moved — bookings made at the
 * new time afterwards are deliberately not included).
 */
class SendConvertRescheduleEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Do not auto-retry: a retry would resend the emails that already went out in this batch. The
     * per-item try/catch below keeps one failure from failing the whole job, so a retry is rarely
     * needed anyway.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Source tour_slot_bulk_operations id — for traceability in logs only.
     *
     * @var int
     */
    public $operationId;

    /**
     * Acting user (the bulk operation's creator) — used as the email/history attribution, since the
     * queue worker has no Auth context.
     *
     * @var int
     */
    public $createdBy;

    /**
     * @var string
     */
    public $createdByUserType;

    /**
     * @var array<int, array{slot_id:int,date:string,old_from:string,new_from:string,booking_ids:array<int,int>}>
     */
    public $tasks;

    public function __construct(int $operationId, int $createdBy, string $createdByUserType, array $tasks)
    {
        $this->operationId      = $operationId;
        $this->createdBy        = $createdBy;
        $this->createdByUserType = $createdByUserType;
        $this->tasks            = $tasks;
    }

    public function handle(): void
    {
        foreach ($this->tasks as $task) {
            try {
                $bookingIds = array_values(array_filter(array_map('intval', $task['booking_ids'] ?? [])));
                if (empty($bookingIds)) {
                    continue;
                }

                // Package + tour-operator context for the converted slot (same for all its bookings).
                $info = DB::table('tour_slot_times AS tst')
                    ->join('tour_packages AS tp', 'tp.id', '=', 'tst.tour_package_id')
                    ->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
                    ->where('tst.id', $task['slot_id'])
                    ->first([
                        'tp.name AS package_name',
                        'tp.id AS tour_package_id',
                        'tp.things_to_bring',
                        'tp.important_notes',
                        'tp.email AS packageEmail',
                        'tp.from_name AS packageFromName',
                        'tp.from_email AS packageFromEmail',
                        'tp.api_key AS packageApiKey',
                        't_o.id AS tour_operator_id',
                        't_o.name AS tour_operator',
                        't_o.email AS tour_op_email',
                        't_o.phone_number AS tour_op_phone',
                        't_o.website AS tour_op_website',
                        't_o.logo AS tour_op_logo',
                        't_o.cancellation_policy',
                        't_o.tenant_id',
                    ]);

                if (!$info) {
                    continue; // slot/package/operator gone → nothing to build the email from
                }

                // Re-fetch contact info fresh and skip any booking cancelled/deleted since conversion.
                $bookings = DB::table('tour_bookings_master')
                    ->whereIn('id', $bookingIds)
                    ->where('status', '!=', 'Cancelled')
                    ->whereNull('deleted_at')
                    ->get(['id', 'name', 'email', 'phone_number']);

                $rescheduledDateTime = date('l, F j, Y @ g:i a', strtotime($task['date'] . ' ' . $task['new_from']));

                foreach ($bookings as $booking) {
                    if (empty($booking->email)) {
                        continue; // no address to notify
                    }
                    try {
                        EmailService::sendBookingReschuduleEmail($info, [
                            'bookingId'           => (int) $booking->id,
                            'tour_d_t'            => $task['date'] . ' ' . $task['old_from'],
                            'custName'            => $booking->name,
                            'custEmail'           => $booking->email,
                            'custPhone'           => $booking->phone_number,
                            'rescheduledDateTime' => $rescheduledDateTime,
                            'operationBy'         => $this->createdBy,
                            'operationByUserType' => $this->createdByUserType,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error($e);
                    }
                }
            } catch (\Throwable $e) {
                Log::error($e);
            }
        }
    }
}
