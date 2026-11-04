<?php

namespace App\Services\Booking;

use App\Models\BookingCheckoutTerm;
use App\Models\TourOperator;
use Illuminate\Support\Facades\Log;

class CheckoutTermsService
{
    /**
     * Save checkout term acknowledgments for a booking.
     *
     * @param int $bookingId
     * @param array $acknowledgments Array of term_key => customer_response
     * @return void
     */
    public static function saveCheckoutTerms(int $bookingId, array $acknowledgments): void
    {
        if (empty($acknowledgments)) {
            return;
        }

        try {
            // Get the tour operator's checkout terms configuration
            $tourOperator = TourOperator::first();
            if (!$tourOperator) {
                return;
            }

            $configuredTerms = $tourOperator->custom_checkout_terms ?? [];
            if (empty($configuredTerms)) {
                return;
            }

            // Create a lookup map for configured terms
            $termsMap = [];
            foreach ($configuredTerms as $term) {
                $termsMap[$term['key']] = $term;
            }

            // Save each acknowledgment
            foreach ($acknowledgments as $termKey => $customerResponse) {
                // Only save if the term exists in configuration
                if (!isset($termsMap[$termKey])) {
                    continue;
                }

                $term = $termsMap[$termKey];

                BookingCheckoutTerm::create([
                    'tour_booking_id' => $bookingId,
                    'term_key' => $termKey,
                    'term_label' => $term['label'],
                    'is_required' => $term['required'] ?? false,
                    'customer_response' => (bool) $customerResponse,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save checkout terms: ' . $e->getMessage());
        }
    }

    /**
     * Get checkout terms for a booking.
     *
     * @param int $bookingId
     * @return array
     */
    public static function getCheckoutTerms(int $bookingId): array
    {
        return BookingCheckoutTerm::where('tour_booking_id', $bookingId)
            ->get()
            ->toArray();
    }
}
