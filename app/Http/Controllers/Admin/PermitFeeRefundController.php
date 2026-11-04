<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPermitFeeRefund;
use App\Models\PermitFeeRefundQueue;
use App\Models\TourPackage;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermitFeeRefundController extends Controller
{
    public function index(Request $request)
    {
        $packages = TourPackage::select('id', 'name')->get();
        
        $query = PermitFeeRefundQueue::with(['package', 'processedBy'])
            ->orderBy('created_at', 'desc');
        
        // Server-side search by booking_id
        if ($request->has('search') && $request->search) {
            $query->where('booking_id', $request->search);
        }
        
        $queueItems = $query->paginate(50)->withQueryString();

        $vars = [
            'page_title' => 'Permit Fee Refund',
            'packages' => $packages,
            'queueItems' => $queueItems,
            'search' => $request->search
        ];

        return view('admin.permit-fee-refund.index', $vars);
    }

    public function loadBookings(Request $request)
    {
        $request->validate([
            'package_id' => 'required|integer',
            'year' => 'required|integer',
            'tour_date' => 'nullable|date',
            'tour_date_from' => 'nullable|date',
            'tour_date_to' => 'nullable|date|after_or_equal:tour_date_from',
        ]);

        // Validate date parameters
        $tourDate = $request->get('tour_date');
        $tourDateFrom = $request->get('tour_date_from');
        $tourDateTo = $request->get('tour_date_to');

        if (($tourDateFrom && !$tourDateTo) || (!$tourDateFrom && $tourDateTo)) {
            return response()->json([
                'error' => 'Both tour_date_from and tour_date_to are required when using date range filtering.'
            ], 400);
        }

        // Clear existing pending records for this package/year
        PermitFeeRefundQueue::where('package_id', $request->package_id)
            ->where('year', $request->year)
            ->where('status', 'pending')
            ->delete();

        // Get bookings using the same logic as the original script
        $bookings = DB::table('tour_bookings_master as tbm')
            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->join('tour_booking_history as tbh', 'tbh.tour_booking_id', '=', 'tbm.id')
            ->join('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
            ->where('tp.id', $request->package_id)
            ->where('tbm.status', 'Booked')
            ->where('tbh.payment_type', 'Card')
            ->where('tbh.operation', 'Payment')
            ->whereNotIn('tbh.operation', ['Cancelled', 'Refund', 'Switch Tour Package'])
            ->where('t.via', 'Card')
            ->where('t.status', 'Completed')
            ->when($tourDate, function ($q) use ($tourDate) {
                $q->whereDate('tst.date', $tourDate);
            })
            ->when($tourDateFrom && $tourDateTo, function ($q) use ($tourDateFrom, $tourDateTo) {
                $q->whereBetween('tst.date', [$tourDateFrom, $tourDateTo]);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tour_booking_history as tbhh')
                    ->whereColumn('tbhh.tour_booking_id','tbm.id')
                    ->where('tbhh.operation','Payment')
                    ->groupBy('tbhh.tour_booking_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))
                    ->from('tour_bookings_transact as tbt')
                    ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                    ->whereColumn('tbt.tour_booking_id', 'tbm.id')
                    ->where('tbt.permit_fee', 20)
                    ->where('tpr.scheduling_year', $request->year)
                    ->where('tbt.deleted_by', 0)
                    ->where('tbt.tourists', '!=', 0);
            })
            ->whereNotExists(function ($q) {
                // Skip bookings that already have permit fee refund
                $q->select(DB::raw(1))
                    ->from('tour_booking_history as tbh_refund')
                    ->whereColumn('tbh_refund.tour_booking_id', 'tbm.id')
                    ->where('tbh_refund.payment_method', 'Permitfees Refund');
            })
            ->orderBy('tbm.id')
            ->select(
                'tbm.id as booking_id',
                'tbm.tourists',
                'tp.tour_operator_id',
                'tp.name as package_name',
                'tst.date as tour_date',
                'tbm.booking_total as total_amount'
            )
            ->get();

        $loadedCount = 0;

        foreach ($bookings as $booking) {
            // Get all transact records for this booking with permit_fee = 20
            $transactRecords = DB::table('tour_bookings_transact as tbt')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->where('tbt.tour_booking_id', $booking->booking_id)
                ->where('tbt.permit_fee', 20)
                ->where('tpr.scheduling_year', $request->year)
                ->where('tbt.deleted_by', 0)
                ->where('tbt.tourists', '!=', 0)
                ->select(
                    'tbt.tour_package_rate_id',
                    'tbt.tourists as pax_count',
                    'tpr.rate_for'
                )
                ->get();

            // Process ALL pax with permit_fee=20 (no check-in filter)
            if ($transactRecords->isNotEmpty()) {
                $rateGroups = [];
                $totalPax = 0;

                foreach ($transactRecords as $record) {
                    $rateGroups[] = [
                        'tour_package_rate_id' => $record->tour_package_rate_id,
                        'pax_count' => $record->pax_count,
                        'rate_for' => $record->rate_for,
                        'tour_operator_id' => $booking->tour_operator_id
                    ];
                    $totalPax += $record->pax_count;
                }

                // Store in queue table
                PermitFeeRefundQueue::create([
                    'booking_id' => $booking->booking_id,
                    'package_id' => $request->package_id,
                    'year' => $request->year,
                    'tour_date' => $booking->tour_date,
                    'tourists' => $totalPax,
                    'total_amount' => $booking->total_amount,
                    'rate_groups' => $rateGroups,
                    'status' => 'pending'
                ]);

                $loadedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Loaded {$loadedCount} bookings for review",
            'count' => $loadedCount
        ]);
    }

    public function processSingle($id)
    {
        $queueItem = PermitFeeRefundQueue::findOrFail($id);
        
        if ($queueItem->status !== 'pending') {
            return response()->json([
                'error' => 'Booking is not in pending status'
            ], 400);
        }

        // Get current authenticated user
        $currentUser = Auth::user();
        
        // Dispatch job with user context
        ProcessPermitFeeRefund::dispatch($queueItem, $currentUser);

        return response()->json([
            'success' => true,
            'message' => 'Booking queued for processing'
        ]);
    }

    public function processBulk(Request $request)
    {
        $request->validate([
            'package_id' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $queueItems = PermitFeeRefundQueue::where('package_id', $request->package_id)
            ->where('year', $request->year)
            ->where('status', 'pending')
            ->get();

        $processedCount = 0;
        $currentUser = Auth::user();

        foreach ($queueItems as $queueItem) {
            ProcessPermitFeeRefund::dispatch($queueItem, $currentUser);
            $processedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$processedCount} bookings for processing"
        ]);
    }

    public function clearPending(Request $request)
    {
        $request->validate([
            'package_id' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $deletedCount = PermitFeeRefundQueue::where('package_id', $request->package_id)
            ->where('year', $request->year)
            ->where('status', 'pending')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleared {$deletedCount} pending bookings"
        ]);
    }

    public function getStatus()
    {
        $stats = [
            'pending' => PermitFeeRefundQueue::where('status', 'pending')->count(),
            'processing' => PermitFeeRefundQueue::where('status', 'processing')->count(),
            'completed' => PermitFeeRefundQueue::where('status', 'completed')->count(),
            'failed' => PermitFeeRefundQueue::where('status', 'failed')->count(),
        ];

        return response()->json($stats);
    }

    public function checkBooking(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $bookingId = $request->booking_id;
        $year = $request->year;
        $reasons = [];
        $info = [];

        // Check if already in queue
        $inQueue = PermitFeeRefundQueue::where('booking_id', $bookingId)->first();
        if ($inQueue) {
            $info[] = "📋 Already in queue with status: {$inQueue->status}";
        }

        // Check if booking exists
        $booking = DB::table('tour_bookings_master as tbm')
            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
            ->where('tbm.id', $bookingId)
            ->select('tbm.*', 'tp.name as package_name', 'tp.id as package_id')
            ->first();

        if (!$booking) {
            return response()->json(['error' => 'Booking not found', 'reasons' => ['Booking ID does not exist']]);
        }

        // Show package info
        $info[] = "📦 Package: {$booking->package_name} (ID: {$booking->package_id})";

        // Check tour date
        $tourSlot = DB::table('tour_slot_times')
            ->where('id', $booking->tour_slot_id)
            ->first();
        
        if ($tourSlot) {
            $info[] = "📅 Tour date: {$tourSlot->date}";
        }

        // Check booking status
        if ($booking->status !== 'Booked') {
            $reasons[] = "❌ Booking status is '{$booking->status}' (must be 'Booked')";
        }

        // Check payment history - must have Card payment with operation = Payment
        $paymentHistory = DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->where('operation', 'Payment')
            ->get();

        if ($paymentHistory->isEmpty()) {
            $reasons[] = "❌ No Payment operation found in booking history";
        } else {
            $cardPayment = $paymentHistory->where('payment_type', 'Card')->first();
            if (!$cardPayment) {
                $reasons[] = "❌ No Card payment found (payment types: " . $paymentHistory->pluck('payment_type')->unique()->implode(', ') . ")";
            }
            if ($paymentHistory->count() > 1) {
                $reasons[] = "❌ Multiple Payment operations found ({$paymentHistory->count()}) - only single payment bookings are eligible";
            }
        }

        // Check transactions
        $transaction = DB::table('transactions')
            ->where('tour_booking_id', $bookingId)
            ->where('via', 'Card')
            ->where('status', 'Completed')
            ->first();

        if (!$transaction) {
            $allTransactions = DB::table('transactions')
                ->where('tour_booking_id', $bookingId)
                ->select('via', 'status')
                ->get();
            $txInfo = $allTransactions->map(fn($t) => "{$t->via}/{$t->status}")->implode(', ');
            $reasons[] = "❌ No completed Card transaction found (found: {$txInfo})";
        }

        // Check permit fee refund already exists
        $permitRefund = DB::table('tour_booking_history')
            ->where('tour_booking_id', $bookingId)
            ->where('payment_method', 'Permitfees Refund')
            ->first();

        if ($permitRefund) {
            $reasons[] = "❌ Permit fee refund already processed (found 'Permitfees Refund' in history)";
        }

        // Check transact records with permit_fee = 20
        $transactRecords = DB::table('tour_bookings_transact as tbt')
            ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->where('tbt.tour_booking_id', $bookingId)
            ->where('tbt.permit_fee', 20)
            ->where('tpr.scheduling_year', $year)
            ->where('tbt.deleted_by', 0)
            ->where('tbt.tourists', '!=', 0)
            ->select('tbt.*', 'tpr.rate_for', 'tpr.scheduling_year')
            ->get();

        if ($transactRecords->isEmpty()) {
            // Check what permit fees exist
            $allTransact = DB::table('tour_bookings_transact as tbt')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->where('tbt.tour_booking_id', $bookingId)
                ->where('tbt.deleted_by', 0)
                ->where('tbt.tourists', '!=', 0)
                ->select('tbt.permit_fee', 'tpr.scheduling_year')
                ->get();
            
            $permitFees = $allTransact->pluck('permit_fee')->unique()->implode(', ');
            $years = $allTransact->pluck('scheduling_year')->unique()->implode(', ');
            $reasons[] = "❌ No transact records with permit_fee=20 and year={$year} (found permit_fees: {$permitFees}, years: {$years})";
        } else {
            // Count total pax eligible for refund
            $totalPax = $transactRecords->sum('tourists');
            $info[] = "✅ Found {$totalPax} pax eligible for \$5 refund";
        }

        $hasErrors = !empty(array_filter($reasons, fn($r) => strpos($r, '❌') === 0));

        if (!$hasErrors) {
            return response()->json([
                'success' => true,
                'message' => "Booking is eligible for permit fee refund",
                'booking_id' => $bookingId,
                'info' => $info,
                'note' => "Make sure you selected Package ID {$booking->package_id} and the tour date {$tourSlot->date} is within your date filter range when loading bookings."
            ]);
        }

        return response()->json([
            'error' => 'Booking not eligible',
            'booking_id' => $bookingId,
            'info' => $info,
            'reasons' => $reasons
        ]);
    }
}
