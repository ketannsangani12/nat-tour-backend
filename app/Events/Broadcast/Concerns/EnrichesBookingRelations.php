<?php

namespace App\Events\Broadcast\Concerns;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Extracted from BookingNew / BookingCancelled / BookingAffiliate.
 * Adds parent-child package relationship fields to a booking array when
 * the PACKAGE_RELATION setting is active.
 */
trait EnrichesBookingRelations
{
    protected function enrichBookingRelations(array $booking): array
    {
        if (!hasSetting('PACKAGE_RELATION') || !isset($booking['pkg_id'])) {
            return array_merge($booking, [
                'has_relations' => false,
                'is_parent'     => false,
                'is_child'      => false,
            ]);
        }

        $year = Carbon::parse($booking['date'])->format('Y');

        $relation = DB::table('tour_package_families')
            ->where('tour_package_id', $booking['pkg_id'])
            ->where('relation_year', $year)
            ->first(['is_parent', 'parent_package_id', 'group_index']);

        if (!$relation) {
            return array_merge($booking, [
                'has_relations' => false,
                'is_parent'     => false,
                'is_child'      => false,
            ]);
        }

        $relatedPackageIds = DB::table('tour_package_families')
            ->where('parent_package_id', $relation->parent_package_id)
            ->where('group_index', $relation->group_index)
            ->where('relation_year', $year)
            ->pluck('tour_package_id')
            ->toArray();

        return array_merge($booking, [
            'has_relations'      => true,
            'is_parent'          => (bool) $relation->is_parent,
            'is_child'           => !$relation->is_parent,
            'parent_package_id'  => $relation->parent_package_id,
            'group_index'        => $relation->group_index,
            'related_package_ids' => $relatedPackageIds,
        ]);
    }
}
