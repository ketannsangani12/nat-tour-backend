<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingAffiliate implements ShouldBroadcast
{
	use Dispatchable, InteractsWithSockets; //, SerializesModels;

	public $booking;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($booking)
	{
		$this->booking = $this->enrichBookingData($booking);
	}

	/**
	 * Enrich booking data with parent-child relationship information
	 */
	private function enrichBookingData($booking)
	{
		// Add parent-child relationship flags if package relations are enabled
		if (hasSetting('PACKAGE_RELATION') && isset($booking['pkg_id'])) {
			$year = Carbon::parse($booking['date'])->format('Y');

			$relationInfo = DB::table('tour_package_families')
				->where('tour_package_id', $booking['pkg_id'])
				->where('relation_year', $year)
				->first(['is_parent', 'parent_package_id', 'group_index']);

			if ($relationInfo) {
				$booking['has_relations'] = true;
				$booking['is_parent'] = (bool) $relationInfo->is_parent;
				$booking['is_child'] = !$relationInfo->is_parent;
				$booking['parent_package_id'] = $relationInfo->parent_package_id;
				$booking['group_index'] = $relationInfo->group_index;

				// Get all related package IDs for this group
				$relatedPackages = DB::table('tour_package_families')
					->where('parent_package_id', $relationInfo->parent_package_id)
					->where('group_index', $relationInfo->group_index)
					->where('relation_year', $year)
					->pluck('tour_package_id')
					->toArray();

				$booking['related_package_ids'] = $relatedPackages;
			} else {
				$booking['has_relations'] = false;
				$booking['is_parent'] = false;
				$booking['is_child'] = false;
			}
		} else {
			$booking['has_relations'] = false;
			$booking['is_parent'] = false;
			$booking['is_child'] = false;
		}

		return $booking;
	}

	/**
	 * Get the channels the event should broadcast on.
	 *
	 * @return \Illuminate\Broadcasting\Channel|array
	 */
	public function broadcastOn()
	{
		$to = $this->booking['notif_for'];
		unset($this->booking['notif_for']);

		if ($to == 'Tour Operator') {
			$to = $this->booking['tour_operator_id'];
			unset($this->booking['tour_operator_id']);
		}

		return new Channel("bookings.$to");
	}

	/**
	 * The event's broadcast name.
	 *
	 * @return string
	 */
	public function broadcastAs()
	{
		return 'booking-affiliate';
	}
}
