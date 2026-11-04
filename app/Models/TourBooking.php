<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class TourBooking extends Model implements Auditable
{
	use SoftDeletes, HasFactory, Notifiable, AuditTrait;

	protected $table = 'tour_bookings_master';

	protected $fillable = [
		'viator_bookingRef',
		'get_your_guide_reference_booking',
		'ota_reference_booking',
		'bokun_reference_code',
		'reseller_id',
		'tour_package_id',
		'tour_slot_id',
		'tour_promotion_id',
		'customer_id',
		'hotel_id',
		'cart_id',
		'group_id',
		'name',
		'email',
		'phone_number',
		'tourists',
		'infants',
		'is_reserved_for_affiliate',
		'comments',
		'note',
		'booking_source',
		'note_datetime',
		'reference_booking',
		'mask_required',
		'discount2_value',
		'discount2_percentage',
		'discount_promo_code',
		'service_commission',
		'total',
		'stripe_paymentintent_id',
		'external_booking_id',
		'liability_waiver_signing',
		'status',
		'is_group_booking',
		'is_partner_booking',
		'visitor',
		'request_from',
		'expire_at',
		'created_by',
		'created_by_user_type',
		'updated_by',
		'updated_by_user_type',
		'deleted_by',
		'deleted_by_user_type',
		'booking_total'
	];

	protected function serializeDate(\DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}

	/**
	 * Get the tour package data using relationship.
	 *
	 * @return BelongsTo
	 */
	public function tourPackage()
	{
		return $this->belongsTo(TourPackage::class, 'tour_package_id', 'id');
	}

	/**
	 * Get the tour slot time data using relationship.
	 *
	 * @return BelongsTo
	 */
	public function tourSlotTime(): BelongsTo
	{
		return $this->belongsTo(TourSlotTime::class, 'tour_slot_id', 'id');
	}

	/**
	 * Get the customer data using relationship.
	 *
	 * @return BelongsTo
	 */
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class, 'customer_id', 'id');
	}

	/**
	 * Get contacts data using relationship.
	 *
	 * @return HasMany
	 */
	public function contacts(): HasMany 
	{
		return $this->hasMany(TourBookingContact::class, 'tour_booking_id', 'id');
	}

	/**
	 * Get the transactions data using relationship.
	 *
	 * @return HasMany
	 */
	public function transactions(): HasMany
	{
		return $this->hasMany(Transaction::class, 'tour_booking_id', 'id');
	}

	/**
	 * Get the checkout terms data using relationship.
	 *
	 * @return HasMany
	 */
	public function checkoutTerms(): HasMany
	{
		return $this->hasMany(BookingCheckoutTerm::class, 'tour_booking_id', 'id');
	}

	public function payoutAdjustments(): HasMany
	{
		return $this->hasMany(PayoutAdjustment::class, 'booking_id', 'id');
	}

	/**
	 * Get the disputes associated with this booking.
	 *
	 * @return HasMany
	 */
	public function disputes(): HasMany
	{
		return $this->hasMany(Dispute::class, 'booking_id', 'id');
	}

	/**
	 * Check if this booking has any active disputes.
	 */
	public function hasActiveDispute(): bool
	{
		return $this->disputes()
			->whereIn('status', ['warning_needs_response', 'under_review'])
			->exists();
	}

	/**
	 * Get the current active dispute for this booking.
	 */
	public function getActiveDispute(): ?Dispute
	{
		return $this->disputes()
			->whereIn('status', ['warning_needs_response', 'under_review'])
			->first();
	}

	/**
	 * Get the dispute status for this booking.
	 */
	public function getDisputeStatusAttribute(): ?string
	{
		$activeDispute = $this->getActiveDispute();
		return $activeDispute?->status;
	}

	/**
	 * Check if this booking is disputed.
	 */
	public function isDisputed(): bool
	{
		return $this->disputes()->exists();
	}

	/**
	 * Get the dispute status CSS class for styling.
	 */
	public function getDisputeStatusCssClass(): string
	{
		$activeDispute = $this->getActiveDispute();
		
		if (!$activeDispute) {
			return '';
		}

		return match($activeDispute->status) {
			'warning_needs_response' => 'bg-red-100 text-red-800 border-red-200',
			'under_review' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
			'charge_refunded' => 'bg-blue-100 text-blue-800 border-blue-200',
			'lost' => 'bg-red-100 text-red-800 border-red-200',
			'won' => 'bg-green-100 text-green-800 border-green-200',
			default => 'bg-gray-100 text-gray-800 border-gray-200'
		};
	}

	/**
	 * Get dispute summary for tooltips and quick display.
	 */
	public function getDisputeSummary(): ?array
	{
		$activeDispute = $this->getActiveDispute();
		
		if (!$activeDispute) {
			return null;
		}

		return [
			'id' => $activeDispute->stripe_dispute_id,
			'amount' => $activeDispute->amount,
			'currency' => $activeDispute->currency,
			'reason' => $activeDispute->reason_label,
			'status' => $activeDispute->status_label,
			'deadline' => $activeDispute->evidence_due_by?->format('M j, Y'),
			'days_until_deadline' => $activeDispute->getDaysUntilDeadline(),
			'requires_attention' => $activeDispute->requiresAttention()
		];
	}

	/**
	 * Check if this booking requires dispute attention.
	 */
	public function requiresDisputeAttention(): bool
	{
		$activeDispute = $this->getActiveDispute();
		return $activeDispute?->requiresAttention() ?? false;
	}

	/**
	 * Get the invoice_bookings links associated with this booking.
	 *
	 * @return HasMany
	 */
	public function invoiceBookings(): HasMany
	{
		return $this->hasMany(InvoiceBooking::class, 'tour_booking_id', 'id');
	}

	/**
	 * Check if this booking has ever been linked to an invoice.
	 */
	public function isInvoiced(): bool
	{
		return $this->invoiceBookings()->exists();
	}

	public $import = [

		'fields' => [
			// 'tour_package_id' 	=> ['displayName' => 'tour_package_id',],
			// 'tour_promotion_id' => ['displayName' => 'tour_promotion_id',],
			'rate' 		=> ['displayName' => 'Customers',],
			'tourists' 	=> ['displayName' => 'Pax',],
			'check_in' 	=> ['displayName' => 'Check-in',],
			'comments' 	=> ['displayName' => 'Comments',],
			'waivers' 	=> ['displayName' => 'Waivers',],
			'due' 		=> ['displayName' => 'Due',],
			'note' 		=> ['displayName' => 'note',],
		],

	];
}
