<?php

namespace App\Services\Frontend;

use App\Models\TourBooking;
use App\Models\TourBookingAddonField;
use App\Models\TourBookingAddonTransaction;
use App\Models\Transaction;
use App\Services\Booking\HistoryService;
use App\Services\Booking\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;

class TourBookingAddonService
{
    /**
     * Save add-on fields and their priced transactions from form payload
     *
     * @param array $formPayload The structured array of addon fields and choices.
     * @param int $bookingId The ID of the TourBooking record.
     * @param array $rateToTransactMap Map of tour_package_rate_id => tour_bookings_transact.id
     * @return array
     * @throws Exception
     */
    public static function saveFormAddons(array $formPayload, int $bookingId, array $rateToTransactMap = []): array
    {
        $booking = TourBooking::findOrFail($bookingId);

        $paymentContext = [
            'payment_method'      => 'card',
            'transaction_id'      => null,
            'application_fee_id'  => null,
            'status'              => 'open',
        ];

        $fieldRecords  = [];  // Stores records for tour_booking_addon_fields before insert
        $pricedRecords = [];  // Stores records for tour_booking_addon_transactions

        // Map original array index of a child record to the array index of its parent record.
        // Format: [child_array_index => parent_array_index]
        $parentIndexMap = [];

        DB::beginTransaction();
        try {
            // Track which top-level fields have already been processed (by field_id + form_id)
            $processedFieldKeys = [];

            foreach ($formPayload as $field) {
                $key = ($field['form_id'] ?? 0) . '-' . $field['field_id'];
                if (isset($processedFieldKeys[$key])) {
                    // Skip fields that were processed recursively as children of an earlier field
                    continue;
                }

                self::processField(
                    field: $field,
                    booking: $booking,
                    bookingId: $bookingId,
                    fieldRecords: $fieldRecords,
                    pricedRecords: $pricedRecords,
                    paymentContext: $paymentContext,
                    parentId: null, // Top-level fields have no parent index initially
                    processedKeys: $processedFieldKeys,
                    parentIndexMap: $parentIndexMap,
                    rateToTransactMap: $rateToTransactMap
                );
            }

            // --- Database Insertion ---

            $fieldIdMap = []; // [array_index => real_db_id]

            // 1. Insert all field records and capture their real IDs
            foreach ($fieldRecords as $index => $record) {
                // Link child records to their real parent IDs using the map
                if (isset($parentIndexMap[$index])) {
                    $parentIndex = $parentIndexMap[$index];
                    // The parent_id is the real DB ID of the parent field, captured during insertion.
                    $record['parent_id'] = $fieldIdMap[$parentIndex] ?? null;
                }

                $id = TourBookingAddonField::insertGetId($record);
                $fieldIdMap[$index] = $id;
            }

            // 2. Link priced records to real field IDs and insert
            foreach ($pricedRecords as &$priced) {
                $priced['tour_booking_addon_id'] = $fieldIdMap[$priced['field_record_index']] ?? null;
                unset($priced['field_record_index']); // Remove temporary index
            }
            unset($priced);

            if (!empty($pricedRecords)) {
                TourBookingAddonTransaction::insert($pricedRecords);
            }

            DB::commit();

            return [
                'success'       => true,
                'fields_saved'  => count($fieldRecords),
                'priced_saved'  => count($pricedRecords),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('TourBookingAddonService@saveFormAddons failed', [
                'booking_id' => $bookingId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Recursive processing of a single field (and its children)
     *
     * @param array $field The current field array being processed.
     * @param object $booking The TourBooking model instance.
     * @param int $bookingId The booking ID.
     * @param array $fieldRecords Reference to the array storing field records.
     * @param array $pricedRecords Reference to the array storing transaction records.
     * @param array $paymentContext Payment details context.
     * @param int|null $parentId The array index of the parent record in $fieldRecords (if applicable).
     * @param array $processedKeys Reference to processed field keys.
     * @param array $parentIndexMap Reference to the map storing child_index => parent_index.
     * @param array|null $paxData Optional array containing pax details (pax_id, pax_index, person_ordinal) to inherit.
     * @param string|null $parentUnitType Optional parent's unit_type to inherit for child fields.
     * @param array $rateToTransactMap Map of tour_package_rate_id => tour_bookings_transact.id
     * @param int|null $parentCategoryId Optional parent's category_id to inherit for child fields.
     * @param string|null $parentCategoryName Optional parent's category name to inherit for child fields.
     * @param int|null $parentPackageId Optional parent's linked package_id to inherit for child fields.
     * @param string|null $parentPackageName Optional parent's linked package name to inherit for child fields.
     * @return void
     */
    private static function processField(
        array   $field,
        object  $booking,
        int     $bookingId,
        array   &$fieldRecords,
        array   &$pricedRecords,
        array   $paymentContext,
        ?int    $parentId = null,
        array   &$processedKeys = [],
        array   &$parentIndexMap = [],
        ?array  $paxData = null,
        ?string $parentUnitType = null,
        array   $rateToTransactMap = [],
        ?int    $parentCategoryId = null,
        ?string $parentCategoryName = null,
        ?int    $parentPackageId = null,
        ?string $parentPackageName = null
    ): void {
        $formId = $field['form_id'] ?? 0;
        $fieldId = $field['field_id'];
        $key = "{$formId}-{$fieldId}";

        $isTopLevel = ($parentId === null);

        if ($isTopLevel && isset($processedKeys[$key])) {
            return;
        }

        $isPriceEnabled = !empty($field['priceInfo']['enabled']) && $field['priceInfo']['enabled'] !== false;

        // Determine unit_type: if this is a child field without its own unit_type, inherit from parent
        $unitType = $field['field_unit_type'] ?? null;
        if ($unitType === null && $parentId !== null && $parentUnitType !== null) {
            // Child field without unit_type inherits from parent
            $unitType = $parentUnitType;
        } elseif ($unitType === null) {
            // Default fallback
            $unitType = 'one_time';
        }

        // Determine category: field's own value, else inherit from parent, else null (no default)
        $categoryId = $field['category_id'] ?? $parentCategoryId;
        $categoryName = $field['category_name'] ?? $parentCategoryName;

        // Determine linked package: field's own value, else inherit from parent, else null
        $packageId = $field['package_id'] ?? $parentPackageId;
        $packageName = $field['package_name'] ?? $parentPackageName;

        // Base record structure for tour_booking_addon_fields
        // Convert pax_id from tour_package_rate_id to tour_bookings_transact.id
        $paxId = $paxData['pax_id'] ?? null;
        if ($paxId !== null && isset($rateToTransactMap[$paxId])) {
            $paxId = $rateToTransactMap[$paxId];
        }

        $baseRecord = [
            'tour_booking_id'        => $bookingId,
            'form_id'                => $formId,
            'field_id'               => $fieldId,
            'field_label'            => $field['field_label'],
            'option_id'              => $field['option_id'] ?? null,
            'option_label'           => $field['option_label'] ?? null,
            'rule_id'                => $field['rule_id'] ?? null,
            'parent_id'              => null,
            'value'                  => null,
            'pax_id'                 => $paxId,
            'pax_index'              => $paxData['pax_index'] ?? null,
            'person_ordinal'         => $paxData['person_ordinal'] ?? null,
            'unit_type'              => $unitType,
            'unit'                   => $field['priceInfo']['quantity'] ?? 1,
            'category_id'            => $categoryId,
            'category_name'          => $categoryName,
            'package_id'             => $packageId,
            'package_name'           => $packageName,
            'applied_rule_snapshot'  => null,
            'operation_at'           => now(),
            'operation_by'           => $booking->created_by ?? Auth::id() ?? 0,
            'operation_by_user_type' => $booking->created_by_user_type ?? self::getUserType(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ];

        $personData = $field['personData'] ?? [];

        // === REPEATED FIELD (has personData) ===
        if (is_array($personData) && count($personData) > 0) {
            if ($isTopLevel) {
                $processedKeys[$key] = true;
            }

            foreach ($personData as $person) {
                if (empty($person)) continue;

                // Extract PAX data for this specific repeated instance
                $currentPaxData = [
                    'pax_id'         => $person['pax_id'] ?? null,
                    'pax_index'      => $person['pax_index'] ?? null,
                    'person_ordinal' => $person['person_ordinal'] ?? null,
                ];

                $record = $baseRecord;
                // Overwrite base record's pax data with the current person's data
                // Convert pax_id from tour_package_rate_id to tour_bookings_transact.id
                $personPaxId = $currentPaxData['pax_id'];
                if ($personPaxId !== null && isset($rateToTransactMap[$personPaxId])) {
                    $personPaxId = $rateToTransactMap[$personPaxId];
                }
                $record['pax_id']                   = $personPaxId;
                $record['pax_index']                = $currentPaxData['pax_index'];
                $record['person_ordinal']           = $currentPaxData['person_ordinal'];

                $record['value']                    = $person['value'] ?? '';
                $record['option_id']                = $person['option_id'] ?? $record['option_id'];
                $record['option_label']             = $person['option_label'] ?? $record['option_label'];
                $record['applied_rule_snapshot']    = $person['applied_rule_snapshot'] ? json_encode($person['applied_rule_snapshot']) : null;

                if ($parentId !== null) {
                    $parentIndexMap[count($fieldRecords)] = $parentId;
                }

                $currentIndex = count($fieldRecords);
                $fieldRecords[] = $record;

                // Process children attached to THIS specific $person entry.
                if (!empty($person['children'])) {
                    foreach ($person['children'] as $childField) {

                        // Pass the current parent's PAX data and unit_type to the recursive call
                        self::processField(
                            field: $childField,
                            booking: $booking,
                            bookingId: $bookingId,
                            fieldRecords: $fieldRecords,
                            pricedRecords: $pricedRecords,
                            paymentContext: $paymentContext,
                            parentId: $currentIndex,
                            processedKeys: $processedKeys,
                            parentIndexMap: $parentIndexMap,
                            paxData: $currentPaxData,
                            parentUnitType: $unitType,
                            rateToTransactMap: $rateToTransactMap,
                            parentCategoryId: $categoryId,
                            parentCategoryName: $categoryName,
                            parentPackageId: $packageId,
                            parentPackageName: $packageName
                        );
                    }
                }

                if ($isPriceEnabled) {
                    self::createPricedRecord($currentIndex, $field, $person, $booking, $paymentContext, $pricedRecords);
                }
            }

            return;
        }

        // === NON-REPEATED FIELD (or a child field being processed) ===
        if ($isTopLevel) {
            $processedKeys[$key] = true;
        }

        $baseRecord['value'] = self::resolveValue($field);

        // Use the baseRecord, which now potentially contains inherited paxData
        // (This handles the child record creation with correct pax info)

        if ($parentId !== null) {
            $parentIndexMap[count($fieldRecords)] = $parentId;
        }

        $currentIndex = count($fieldRecords);
        $fieldRecords[] = $baseRecord;

        $currentParentIdIndex = $currentIndex;

        if ($isPriceEnabled) {
            self::createPricedRecord($currentIndex, $field, null, $booking, $paymentContext, $pricedRecords);
        }

        // Process children attached to this non-repeated field
        if (!empty($field['children'])) {
            foreach ($field['children'] as $childField) {
                // When processing children of a non-repeated field, pass parent's unit_type
                self::processField(
                    field: $childField,
                    booking: $booking,
                    bookingId: $bookingId,
                    fieldRecords: $fieldRecords,
                    pricedRecords: $pricedRecords,
                    paymentContext: $paymentContext,
                    parentId: $currentParentIdIndex,
                    processedKeys: $processedKeys,
                    parentIndexMap: $parentIndexMap,
                    paxData: null,
                    parentUnitType: $unitType,
                    rateToTransactMap: $rateToTransactMap,
                    parentCategoryId: $categoryId,
                    parentCategoryName: $categoryName,
                    parentPackageId: $packageId,
                    parentPackageName: $packageName
                );
            }
        }
    }

    /**
     * Create a priced record entry
     *
     * The frontend submits single-unit price/fee (rounded per unit). For
     * price_per_unit fields, this scales them by the submitted quantity here
     * so stored totals match the convention used by admin-created bookings
     * (see TourBookingAddonRepository::chargeAddonFields()).
     *
     * @param int $fieldRecordIndex
     * @param array $field
     * @param array|null $person
     * @param object $booking
     * @param array $paymentContext
     * @param array $pricedRecords
     * @return void
     */
    private static function createPricedRecord(
        int     $fieldRecordIndex,
        array   $field,
        ?array  $person,
        object  $booking,
        array   $paymentContext,
        array   &$pricedRecords
    ): void {
        $unitType = $field['field_unit_type'] ?? null;
        $quantity = $unitType === 'price_per_unit'
            ? max(1, (int) ($field['priceInfo']['quantity'] ?? 1))
            : 1;

        $unitFieldPrice  = (float) ($person['field_price']  ?? $field['field_price']  ?? 0.00);
        $unitFieldFee    = (float) ($person['field_fee']    ?? $field['field_fee']    ?? 0.00);
        $unitOptionPrice = (float) ($person['option_price'] ?? $field['option_price'] ?? 0.00);
        $unitOptionFee   = (float) ($person['option_fee']   ?? $field['option_fee']   ?? 0.00);

        $pricedRecords[] = [
            'field_record_index'     => $fieldRecordIndex,
            'tour_booking_id'        => $booking->id,
            'tour_booking_addon_id'  => null,
            'payment_method'         => $paymentContext['payment_method'],
            'status'                 => $paymentContext['status'],
            'operation'              => 'payment',
            'transaction_id'         => $paymentContext['transaction_id'],
            'application_fee_id'     => $paymentContext['application_fee_id'],
            'field_additional_fee'   => $person['field_additional_fee'] ?? $field['field_additional_fee'] ?? 0,
            'option_additional_fee'  => $person['option_additional_fee'] ?? $field['option_additional_fee'] ?? 0,
            'field_price'            => round($unitFieldPrice * $quantity, 2),
            'field_fee'              => round($unitFieldFee * $quantity, 2),
            'option_price'           => round($unitOptionPrice * $quantity, 2),
            'option_fee'             => round($unitOptionFee * $quantity, 2),
            'notes'                  => null,
            'operation_at'           => now(),
            'operation_by'           => $booking->created_by ?? Auth::id() ?? 0,
            'operation_by_user_type' => $booking->created_by_user_type ?? self::getUserType(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ];
    }

    /**
     * Resolve field value based on its type
     * @param array $field
     * @return string|null
     */
    private static function resolveValue(array $field): ?string
    {
        if ($field['type'] === 'checkbox') {
            return $field['value'] === true || $field['value'] == 1 ? '1' : '0';
        }
        return is_array($field['value']) ? json_encode($field['value']) : ($field['value'] ?? null);
    }

    /**
     * Determine user type for operation_by_user_type field
     */
    private static function getUserType(): string
    {
        if (Auth::guard('admin')->check()) return 'Admin';
        if (Auth::guard('tour_operator_staff')->check()) return 'Tour Operator';
        if (Auth::guard('affiliate')->check()) return 'Affiliate';
        return 'Customer';
    }

    /**
     * Called after successful payment to mark all 'open' add-ons as paid
     * @param int $bookingId
     * @param mixed $paymentIntent
     * @return void
     */
    public static function markAddonsAsPaid(int $bookingId, $paymentIntent, string $paymentMethod = 'card'): void
    {
        $booking = TourBooking::findOrFail($bookingId);
        $transaction = Transaction::where('tour_booking_id', $bookingId)->latest()->first();

        if (!$transaction) {
            return; // No main transaction? Nothing to do
        }

        $addonTransactions = TourBookingAddonTransaction::whereHas('addonField', function ($q) use ($bookingId) {
            $q->where('tour_booking_id', $bookingId);
        })
            ->where('status', 'open')
            ->with('addonField')
            ->get();

        if ($addonTransactions->isEmpty()) {
            return;
        }

        $method = config('constants.payment_methods.' . $paymentMethod, strtolower($paymentMethod));
        $status = 'paid';
        // Transaction-table operation: must be 'charge' to match the convention every
        // admin charge/refund query filters on (see TourBookingAddonRepository::chargeAddonFields()
        // and computeNetAddonTransactions()/getBookingPaxAddons()) — NOT the history log label below.
        $transactionOperation = 'charge';
        $historyOperation = 'added';
        $history = [];
        $totalPrice = 0;
        $totalFee = 0;

        foreach ($addonTransactions as $addonTransaction) {
            $field = $addonTransaction->addonField;
            $quantity = (int) ($field->unit ?? 1);

            // field_price/field_fee are already quantity-scaled totals (see createPricedRecord())
            $priceValue = $addonTransaction->field_price + $addonTransaction->option_price;
            $feeValue   = $addonTransaction->field_fee + $addonTransaction->option_fee;

            $totalPrice += $priceValue;
            $totalFee += $feeValue;

            // Update transaction record
            $addonTransaction->update([
                'transaction_id'        => $transaction->transaction_number,
                'application_fee_id'    => $transaction->application_fee_id,
                'payment_method'        => $method,
                'status'                => $status,
                'operation'             => $transactionOperation,
                'operation_at'          => now(),
            ]);

            $history[] = [
                'name'           => $field->field_label . ($field->option_label ? " ({$field->option_label})" : ''),
                'price'          => $priceValue,
                'fee'            => $feeValue,
                'quantity'       => $quantity,
                'payment_method' => $method,
                'status'         => $status
            ];
        }

        // Store in main transaction & history
        // Reuse the main tour-side transaction's own persisted payment_mode — it's the SAME
        // real charge this addon amount is being split off from, already correctly recorded by
        // whichever flow created it.
        $transId = TransactionService::storeAddonsPaymentInTransaction(
            $booking,
            $method,
            $totalPrice,
            $totalFee,
            $paymentIntent,
            mode: $transaction->payment_mode
        );

        HistoryService::storeAddonsPaymentInHistory(
            $booking,
            $history,
            $historyOperation,
            $paymentIntent,
            $transId,
            $status,
            $totalPrice,
            $totalFee
        );
    }
}
