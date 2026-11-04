<?php

namespace App\Repositories\Dispute;

use App\Models\Dispute;
use App\Models\DisputeTimeline;
use App\Models\TourBooking;
use App\Models\Transaction;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class DisputeRepository extends Repository
{
    public $model = Dispute::class;

    public function findAll(array $filters = [], array $with = []): Collection
    {
        $query = Dispute::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $query = $this->applyFilters($query, $filters);
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 25, array $with = []): LengthAwarePaginator
    {
        $query = Dispute::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $query = $this->applyFilters($query, $filters);

        $sortBy        = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $allowedSortFields = ['created_at', 'evidence_due_by', 'amount', 'status', 'reason'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function findById(int $disputeId, array $with = []): ?Dispute
    {
        $query = Dispute::where('id', $disputeId);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    public function findByStripeId(string $stripeDisputeId, array $with = []): ?Dispute
    {
        $query = Dispute::where('stripe_dispute_id', $stripeDisputeId);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    public function findByBookingId(int $bookingId, array $with = []): Collection
    {
        if (!TourBooking::find($bookingId)) {
            return new Collection();
        }

        $query = Dispute::where('booking_id', $bookingId);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    public function create(array $disputeData): Dispute
    {
        $this->validateDisputeData($disputeData);

        if (isset($disputeData['booking_id']) && !TourBooking::find($disputeData['booking_id'])) {
            throw new Exception("Booking ID {$disputeData['booking_id']} not found");
        }

        if (isset($disputeData['transaction_id']) && !Transaction::find($disputeData['transaction_id'])) {
            throw new Exception("Transaction ID {$disputeData['transaction_id']} not found");
        }

        $dispute = Dispute::create($disputeData);

        DisputeTimeline::logDisputeCreated($dispute, ['created_via' => 'repository']);

        return $dispute;
    }

    public function update(int $disputeId, array $updateData): Dispute
    {
        $dispute = $this->findById($disputeId);
        if (!$dispute) {
            throw new Exception("Dispute ID {$disputeId} not found");
        }

        $oldStatus = $dispute->status;

        $dispute->update($updateData);
        $dispute->refresh();

        if (isset($updateData['status']) && $updateData['status'] !== $oldStatus) {
            DisputeTimeline::logStatusChange($dispute, $oldStatus, $updateData['status']);
        }

        return $dispute;
    }

    public function getActiveDisputesRequiringAttention(int $daysUntilDeadline = 3): Collection
    {
        return Dispute::active()
            ->where(function ($query) use ($daysUntilDeadline) {
                $query->where('status', 'warning_needs_response')
                      ->where('evidence_due_by', '<=', now()->addDays($daysUntilDeadline));
            })
            ->with(['booking', 'evidence'])
            ->orderBy('evidence_due_by', 'asc')
            ->get();
    }

    public function getStatistics(array $dateRange = []): array
    {
        $query = Dispute::query();

        if (!empty($dateRange)) {
            if (isset($dateRange['start'])) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $query->where('created_at', '<=', $dateRange['end']);
            }
        }

        return [
            'total_disputes'      => $query->count(),
            'active_disputes'     => $query->clone()->active()->count(),
            'overdue_disputes'    => $query->clone()->overdue()->count(),
            'requiring_attention' => $query->clone()->requiringAttention()->count(),
            'by_status'           => $query->clone()->groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status')->toArray(),
            'by_reason'           => $query->clone()->groupBy('reason')->selectRaw('reason, count(*) as count')->pluck('count', 'reason')->toArray(),
            'total_amount'        => $query->clone()->sum('amount'),
            'average_amount'      => $query->clone()->avg('amount'),
        ];
    }

    public function getDisputeVolumeTrends(array $dateRange = [], string $groupBy = 'day'): array
    {
        $query = Dispute::query();

        if (!empty($dateRange)) {
            if (isset($dateRange['start'])) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $query->where('created_at', '<=', $dateRange['end']);
            }
        }

        $dateFormat = match($groupBy) {
            'hour'  => '%Y-%m-%d %H:00:00',
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'month' => '%Y-%m',
            'year'  => '%Y',
            default => '%Y-%m-%d',
        };

        return $query->selectRaw("
            DATE_FORMAT(created_at, '{$dateFormat}') as period,
            COUNT(*) as dispute_count,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount,
            COUNT(CASE WHEN status = 'warning_needs_response' THEN 1 END) as needs_response,
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review,
            COUNT(CASE WHEN status = 'won' THEN 1 END) as won,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost
        ")
        ->groupBy('period')
        ->orderBy('period')
        ->get()
        ->map(fn ($item) => [
            'period'         => $item->period,
            'dispute_count'  => (int)   $item->dispute_count,
            'total_amount'   => (float) $item->total_amount,
            'average_amount' => (float) $item->average_amount,
            'needs_response' => (int)   $item->needs_response,
            'under_review'   => (int)   $item->under_review,
            'won'            => (int)   $item->won,
            'lost'           => (int)   $item->lost,
        ])
        ->toArray();
    }

    public function getDisputeReasonBreakdown(array $dateRange = []): array
    {
        $query = Dispute::query();

        if (!empty($dateRange)) {
            if (isset($dateRange['start'])) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $query->where('created_at', '<=', $dateRange['end']);
            }
        }

        $reasonBreakdown = $query->selectRaw("
            reason,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount,
            COUNT(CASE WHEN status = 'won' THEN 1 END) as won_count,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_count,
            COUNT(CASE WHEN status IN ('warning_needs_response', 'under_review') THEN 1 END) as pending_count,
            ROUND(COUNT(CASE WHEN status = 'won' THEN 1 END) * 100.0 / COUNT(*), 2) as win_rate
        ")
        ->groupBy('reason')
        ->orderBy('count', 'desc')
        ->get()
        ->map(fn ($item) => [
            'reason'         => $item->reason,
            'reason_label'   => $this->getReasonLabel($item->reason),
            'category'       => $this->getReasonCategory($item->reason),
            'count'          => (int)   $item->count,
            'total_amount'   => (float) $item->total_amount,
            'average_amount' => (float) $item->average_amount,
            'won_count'      => (int)   $item->won_count,
            'lost_count'     => (int)   $item->lost_count,
            'pending_count'  => (int)   $item->pending_count,
            'win_rate'       => (float) $item->win_rate,
        ])
        ->toArray();

        $categoryBreakdown = collect($reasonBreakdown)
            ->groupBy('category')
            ->map(fn ($reasons, $category) => [
                'category'       => $category,
                'count'          => $reasons->sum('count'),
                'total_amount'   => $reasons->sum('total_amount'),
                'average_amount' => $reasons->avg('average_amount'),
                'win_rate'       => $reasons->avg('win_rate'),
                'reasons'        => $reasons->values()->toArray(),
            ])
            ->values()
            ->toArray();

        return [
            'by_reason'   => $reasonBreakdown,
            'by_category' => $categoryBreakdown,
        ];
    }

    public function getFinancialImpactAnalysis(array $dateRange = []): array
    {
        $query = Dispute::query();

        if (!empty($dateRange)) {
            if (isset($dateRange['start'])) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end'])) {
                $query->where('created_at', '<=', $dateRange['end']);
            }
        }

        $basicMetrics = $query->selectRaw("
            COUNT(*) as total_disputes,
            SUM(amount) as total_disputed_amount,
            AVG(amount) as average_dispute_amount,
            SUM(CASE WHEN status = 'lost' THEN amount ELSE 0 END) as lost_revenue,
            SUM(CASE WHEN status = 'won' THEN amount ELSE 0 END) as retained_revenue,
            SUM(CASE WHEN status = 'charge_refunded' THEN amount ELSE 0 END) as refunded_amount,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_disputes,
            COUNT(CASE WHEN status = 'won' THEN 1 END) as won_disputes,
            SUM(COALESCE(stripe_dispute_fee, 1500)) / 100 as total_fees_amount
        ")->first();

        $totalFees = (float) $basicMetrics->total_fees_amount;
        $netImpact = $basicMetrics->lost_revenue + $basicMetrics->refunded_amount + $totalFees;
        $recoveryRate   = $basicMetrics->total_disputes > 0
            ? ($basicMetrics->won_disputes / $basicMetrics->total_disputes) * 100
            : 0;

        $monthlyImpact = $query->selectRaw("
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as dispute_count,
            SUM(amount) as disputed_amount,
            SUM(CASE WHEN status = 'lost' THEN amount ELSE 0 END) as lost_amount,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_count,
            SUM(COALESCE(stripe_dispute_fee, 1500)) / 100 as total_fees_amount
        ")
        ->groupBy('month')
        ->orderBy('month')
        ->get()
        ->map(function ($item) {
            $monthlyTotalFees = (float) $item->total_fees_amount;

            return [
                'month'           => $item->month,
                'dispute_count'   => (int)   $item->dispute_count,
                'disputed_amount' => (float) $item->disputed_amount,
                'lost_amount'     => (float) $item->lost_amount,
                'total_fees'      => $monthlyTotalFees,
                'net_impact'      => (float) $item->lost_amount + $monthlyTotalFees,
            ];
        })
        ->toArray();

        return [
            'summary' => [
                'total_disputes'         => (int)   $basicMetrics->total_disputes,
                'total_disputed_amount'  => (float) $basicMetrics->total_disputed_amount,
                'average_dispute_amount' => (float) $basicMetrics->average_dispute_amount,
                'lost_revenue'           => (float) $basicMetrics->lost_revenue,
                'retained_revenue'       => (float) $basicMetrics->retained_revenue,
                'refunded_amount'        => (float) $basicMetrics->refunded_amount,
                'total_fees'             => $totalFees,
                'net_financial_impact'   => $netImpact,
                'recovery_rate'          => round($recoveryRate, 2),
            ],
            'monthly_breakdown' => $monthlyImpact,
            'fee_structure' => [
                'dispute_fee_per_case' => 15.00,
                'currency'             => 'USD',
            ],
        ];
    }

    public function getDisputeListForAnalytics(array $dateRange = [], ?string $status = null): array
    {
        $query = Dispute::query()
            ->leftJoin('tour_bookings_master as tbm', 'tbm.id', '=', 'disputes.booking_id')
            ->select([
                'disputes.id',
                'disputes.stripe_dispute_id',
                'disputes.booking_id',
                \Illuminate\Support\Facades\DB::raw("COALESCE(tbm.name, '') as customer_name"),
                'disputes.amount',
                'disputes.reversed_amount',
                'disputes.status',
                'disputes.reason',
                'disputes.created_at',
                'disputes.lost_at',
                'disputes.evidence_due_by',
            ])
            ->orderBy('disputes.created_at', 'desc');

        if (!empty($dateRange['start'])) {
            $query->where('disputes.created_at', '>=', $dateRange['start']);
        }
        if (!empty($dateRange['end'])) {
            $query->where('disputes.created_at', '<=', $dateRange['end']);
        }
        if ($status) {
            $query->where('disputes.status', $status);
        }

        return $query->get()->map(function ($d) {
            return [
                'id'                => $d->id,
                'stripe_dispute_id' => $d->stripe_dispute_id,
                'booking_id'        => $d->booking_id,
                'customer_name'     => trim($d->customer_name) ?: 'N/A',
                'amount'            => (float) $d->amount,
                'reversed_amount'   => $d->reversed_amount ? round($d->reversed_amount / 100, 2) : null,
                'status'            => $d->status,
                'reason'            => $d->reason,
                'created_at'        => $d->created_at ? \Carbon\Carbon::parse($d->created_at)->format('m/d/Y') : null,
                'lost_at'           => $d->lost_at ? \Carbon\Carbon::parse($d->lost_at)->format('m/d/Y') : null,
                'report_period'     => $d->lost_at ? \Carbon\Carbon::parse($d->lost_at)->format('M Y') : null,
                'evidence_due_by'   => $d->evidence_due_by ? \Carbon\Carbon::parse($d->evidence_due_by)->format('m/d/Y') : null,
            ];
        })->toArray();
    }

    protected function applyFilters($query, array $filters)
    {
        if (!empty($filters['status'])) {
            is_array($filters['status'])
                ? $query->whereIn('status', $filters['status'])
                : $query->where('status', $filters['status']);
        }

        if (!empty($filters['reason'])) {
            is_array($filters['reason'])
                ? $query->whereIn('reason', $filters['reason'])
                : $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (!empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        if (!empty($filters['booking_id'])) {
            $query->where('booking_id', $filters['booking_id']);
        }

        if (!empty($filters['stripe_dispute_id'])) {
            $query->where('stripe_dispute_id', 'like', '%' . $filters['stripe_dispute_id'] . '%');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('stripe_dispute_id', 'like', '%' . $search . '%')
                  ->orWhere('reason', 'like', '%' . $search . '%')
                  ->orWhere('booking_id', is_numeric($search) ? (int) $search : null)
                  ->orWhereHas('booking', function ($bookingQuery) use ($search) {
                      $bookingQuery->where('name', 'like', '%' . $search . '%')
                                   ->orWhere('email', 'like', '%' . $search . '%')
                                   ->orWhere('id', is_numeric($search) ? (int) $search : null)
                                   ->orWhere('reference_booking', 'like', '%' . $search . '%');
                  });
            });
        }

        if (!empty($filters['requires_attention'])) {
            $query->requiringAttention();
        }

        if (!empty($filters['overdue'])) {
            $query->overdue();
        }

        return $query;
    }

    protected function validateDisputeData(array $disputeData): void
    {
        $requiredFields = ['stripe_dispute_id', 'amount', 'currency', 'reason', 'status'];

        foreach ($requiredFields as $field) {
            if (empty($disputeData[$field])) {
                throw new Exception("Required field '{$field}' is missing or empty");
            }
        }

        if ($disputeData['amount'] <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
    }

    private function getReasonLabel(string $reason): string
    {
        return match($reason) {
            'fraudulent'            => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable'  => 'Product Unacceptable',
            'credit_not_processed'  => 'Credit Not Processed',
            'duplicate'             => 'Duplicate',
            'product_not_received'  => 'Product Not Received',
            'unrecognized'          => 'Unrecognized',
            'general'               => 'General',
            default                 => ucfirst(str_replace('_', ' ', $reason)),
        };
    }

    private function getReasonCategory(string $reason): string
    {
        return match($reason) {
            'fraudulent', 'unrecognized'                                              => 'Fraud',
            'subscription_canceled', 'product_unacceptable', 'product_not_received'  => 'Service Issues',
            'credit_not_processed', 'duplicate'                                       => 'Processing Issues',
            default                                                                   => 'Other',
        };
    }
}
