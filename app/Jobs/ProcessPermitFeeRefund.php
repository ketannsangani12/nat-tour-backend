<?php

namespace App\Jobs;

use App\Models\PermitFeeRefundQueue;
use App\Repositories\TourBooking\V3\TourBookingRepository;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPermitFeeRefund implements ShouldQueue
{
    use Queueable;

    protected $queueItem;
    protected $user;
    protected $updatedPermitFee = 15;

    public function __construct(PermitFeeRefundQueue $queueItem, $user = null)
    {
        $this->queueItem = $queueItem;
        $this->user = $user;
    }

    public function handle(TourBookingRepository $tourBookingRepository)
    {
        try {
            // Mark as processing
            $this->queueItem->update(['status' => 'processing']);

            // Set user context if provided
            if ($this->user) {
                Auth::login($this->user);

                // Set session data that the application expects
                session([
                    'userType' => 'Admin',
                    'userId' => $this->user->id,
                    'userName' => $this->user->name ?? 'System Admin'
                ]);

                // Set request context for IP and user agent
                request()->server->set('REMOTE_ADDR', '127.0.0.1');
                request()->server->set('HTTP_USER_AGENT', 'Queue Job');
            } else {
                // Fallback: create a system user context
                session([
                    'userType' => 'Admin',
                    'userId' => 1,
                    'userName' => 'System Admin'
                ]);
            }

            // Get commission data
            $commissions = DB::table('tour_packages')
                ->where('id', $this->queueItem->package_id)
                ->select(
                    'next_year_service_commission_percentage',
                    'next_year_stripe_commission_percentage'
                )
                ->first();

            if (!$commissions) {
                throw new Exception('Commission data not found for package');
            }

            // Build permitF and check arrays from stored rate groups
            $permitF = [];
            $check = [];

            foreach ($this->queueItem->rate_groups as $rateGroup) {
                $count = (int) $rateGroup['pax_count'];
                
                for ($i = 0; $i < $count; $i++) {
                    $key = "{$rateGroup['rate_for']}.{$i}";
                    $permitF[$key] = (string) $this->updatedPermitFee;
                    $check[$key] = (string) $rateGroup['tour_package_rate_id'];
                }
            }

            // Build payload with user context
            $payload = [
                "refund_Type"          => "full_refund",
                "is_cash"              => "",
                "partial_Refund_value" => "",
                "action_type"          => "update",
                "tourits_numbers"      => "",
                "contacts"             => "",
                "pax_addons_ids"       => "",
                "bookingID"            => (string)$this->queueItem->booking_id,
                "parent_url"           => url("/admin/tour-operator/booking/{$this->queueItem->booking_id}"),
                "tour_operator_id"     => (string)$this->queueItem->rate_groups[0]['tour_operator_id'] ?? '',
                "payment_type"         => "Card",
                "total_tourist"        => (string)$this->queueItem->tourists,
                "checked_transactions" => "",
                "splitPendingAmount"   => "0",
                "is_calculate_amount"  => "0",
                "permitF"              => $permitF,
                "check"                => $check,
                // Add user context to request
                "created_by"           => $this->user ? $this->user->id : 1,
                "created_by_user_type" => "Admin",
                "processed_by_queue"   => true
            ];

            DB::transaction(function () use ($payload, $commissions, $tourBookingRepository) {
                $request = new Request($payload);
                
                // Set additional request attributes for user context
                if ($this->user) {
                    $request->merge([
                        'user_id' => $this->user->id,
                        'user_type' => 'Admin'
                    ]);
                }

                // Run core business logic
                $tourBookingRepository->editBooking($request);

                // Update fee percentages
                DB::table('tour_bookings_transact')
                    ->where('tour_booking_id', $this->queueItem->booking_id)
                    ->where('permit_fee', 20)
                    ->update([
                        'nat_fees_percentage'    => $commissions->next_year_service_commission_percentage,
                        'stripe_fees_percentage' => $commissions->next_year_stripe_commission_percentage,
                    ]);
            });

            // Mark as completed
            $this->queueItem->update([
                'status' => 'completed',
                'processed_at' => now(),
                'processed_by' => $this->user ? $this->user->id : null,
                'error_message' => null
            ]);

            Log::info("Permit fee refund completed for booking {$this->queueItem->booking_id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->queueItem->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            Log::error("Permit fee refund failed for booking {$this->queueItem->booking_id}: {$e->getMessage()}");
            
            throw $e; // Re-throw to trigger job failure handling
        } finally {
            // Clear authentication context
            if (Auth::check()) {
                Auth::logout();
            }
            session()->flush();
        }
    }

    public function failed(Exception $exception)
    {
        $this->queueItem->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}
