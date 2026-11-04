<?php

namespace App\Services\Booking\RemainingPayment\Handlers;

use App\Models\TourBookingAddonField;
use App\Models\TourBookingPaymentLinkItem;
use App\Repositories\TourBooking\TourBookingAddonRepository;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Log;

class AddonItemHandler extends BaseItemHandler
{
    public static function isProcessable(TourBookingPaymentLinkItem $item): bool
    {
        return true;
    }

    public static function extractFees(TourBookingPaymentLinkItem $item): float
    {
        $details = is_array($item->details)
            ? $item->details
            : (json_decode($item->details ?? '[]', true) ?? []);

        return (float) ($details['platform_fees'] ?? 0);
    }

    public static function finalize(
        TourBookingPaymentLinkItem $item,
        int|string $bookingId,
        object $paymentIntent,
        string $paymentMethod = 'Card',
        ?int $transactionId = null
    ): void {
        $details = is_array($item->details)
            ? $item->details
            : (json_decode($item->details ?? '[]', true) ?? []);

        if (empty($details['financial'])) {
            return;
        }

        $financial                      = $details['financial'];
        $financial['action']            = 'charge';
        $financial['payment_method']    = $paymentMethod;
        $financial['payment_intent_id'] = $paymentIntent->id ?? null;

        $fieldToDb       = $details['field_to_db'] ?? [];
        $storedBookingId = (int) ($details['booking_id'] ?? $bookingId);
        $formId          = $details['form_id'] ?? null;

        // Fallback: if fieldToDb is missing entries for newly-added charge items,
        // look them up from the DB. If not found, create the row from stored diff data.
        // Handles both top-level addon edits (added/updated) and bulk pax addon flow (pax).
        foreach ($financial['to_charge'] ?? [] as $chargeItem) {
            $fid      = $chargeItem['field_id'] ?? null;
            $paxIndex = $chargeItem['pax_index'] ?? null;

            // Build the lookup key: composite for pax items, plain for non-pax.
            $lookupKey = $paxIndex !== null ? ($fid . '_' . $paxIndex) : $fid;

            if (!$fid || ($chargeItem['old_db_id'] ?? null) || isset($fieldToDb[$lookupKey])) {
                continue;
            }

            // Resolve the pax_id for pax-structured items.
            $resolvedPaxId = null;
            if ($paxIndex !== null) {
                foreach ($details['pax'] ?? [] as $paxGroup) {
                    if (($paxGroup['pax_index'] ?? null) == $paxIndex) {
                        $resolvedPaxId = $paxGroup['pax_id'] ?? null;
                        break;
                    }
                }
            }

            // Try DB lookup first.
            $found = TourBookingAddonField::where('tour_booking_id', $storedBookingId)
                ->where('field_id', $fid)
                ->when($resolvedPaxId !== null,
                    fn ($q) => $q->where('pax_id', $resolvedPaxId),
                    fn ($q) => $q->whereNull('pax_id')
                )
                ->when($formId, fn ($q) => $q->where('form_id', $formId))
                ->orderByDesc('id')
                ->value('id');

            if ($found) {
                $fieldToDb[$lookupKey] = $found;
            } else {
                // Row not in DB — try to create it from stored diff data.
                // Search top-level added/updated first, then pax-structured groups.
                $addedItem = collect($details['added'] ?? [])->first(
                    fn($a) => (int) ($a['field_id'] ?? 0) === (int) $fid
                );

                if (!$addedItem) {
                    $addedItem = collect($details['updated'] ?? [])->first(
                        fn($a) => (int) ($a['field_id'] ?? 0) === (int) $fid
                    );
                }

                // Search pax groups when to_charge item carries a pax_index.
                if (!$addedItem && $paxIndex !== null) {
                    foreach ($details['pax'] ?? [] as $paxGroup) {
                        if (($paxGroup['pax_index'] ?? null) != $paxIndex) {
                            continue;
                        }
                        $addedItem = collect(array_merge($paxGroup['added'] ?? [], $paxGroup['updated'] ?? []))
                            ->first(fn($a) => (int) ($a['field_id'] ?? 0) === (int) $fid);
                        break;
                    }
                }

                if ($addedItem) {
                    $booking = BookingService::booking($storedBookingId);
                    $created = TourBookingAddonField::firstOrCreate(
                        [
                            'tour_booking_id' => $storedBookingId,
                            'form_id'         => $formId ?: ($addedItem['form_id'] ?? 0),
                            'field_id'        => $fid,
                            'pax_id'          => $resolvedPaxId,
                        ],
                        [
                            'option_id'              => $addedItem['option_id'] ?? null,
                            'option_label'           => $addedItem['option_label'] ?? null,
                            'value'                  => $addedItem['value'] ?? null,
                            'unit_type'              => $addedItem['unit_type'] ?? null,
                            'unit'                   => $addedItem['unit'] ?? 0,
                            'rule_id'                => $addedItem['rule_id'] ?? null,
                            'applied_rule_snapshot'  => $addedItem['applied_rule_snapshot'] ?? null,
                            'operation_at'           => now(),
                            'operation_by'           => $booking->created_by,
                            'operation_by_user_type' => $booking->created_by_user_type,
                        ]
                    );
                    $fieldToDb[$lookupKey] = $created->id;
                }
            }
        }

        $repo = app(TourBookingAddonRepository::class);
        try {
            $repo->processAddonPaymentFromLink($storedBookingId, $financial, $fieldToDb);
        } catch (\Throwable $e) {
            Log::error('AddonItemHandler: processAddonPaymentFromLink failed', [
                'booking_id'  => $storedBookingId,
                'pi_id'       => $financial['payment_intent_id'] ?? null,
                'field_to_db' => $fieldToDb,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public static function getLabel(): string
    {
        return 'Add-on';
    }
}
