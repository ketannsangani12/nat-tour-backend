<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;

class RecordsHistoryService
{
    /**
     * Retrieves the records history for a given tour operator and request.
     *
     * @param int $tourOperatorId The ID of the tour operator.
     * @param \Illuminate\Http\Request $request The request containing filters like date and tourPackageId.
     * @return \Illuminate\Support\Collection The formatted records history.
     */
    public static function getRecordsHistory($tourOperatorId, $request)
    {
        $fields = [
            'rh.operation_type',
            'rh.table_name',
            'rh.entity_info',
            'rh.new_values',
            'rh.operation_at',
            'rh.operation_by',
            'rh.operation_by_user_type',
            'ad.name as admin_name',
            DB::raw('CONCAT(tos.first_name, \' \', tos.last_name) as staff_name'),
        ];

        // Get queries for different table groups
        // $qry1 = self::getHistoryQuery($tourOperatorId, ['tour_package_rates'], $request->date, $fields);
        $qry2 = self::getHistoryQuery($tourOperatorId, ['tour_slot_dates', 'tour_slot_times'], $request->date, $fields, true);

        // Main query including unions
        $recordsHistory = DB::table('records_history as rh')
            ->leftJoin('admins AS ad', function ($join) {
                $join->on('ad.id', '=', 'rh.operation_by')
                    ->where('rh.operation_by_user_type', '=', 'Admin');
            })
            ->leftJoin('tour_operator_staff AS tos', function ($join) {
                $join->on('tos.id', '=', 'rh.operation_by')
                    ->where('rh.operation_by_user_type', '=', 'Tour Operator');
            })
            ->where('rh.record_id_1', $request->tourPackageId)
            // ->where('rh.table_name', 'tour_packages')
            // ->whereBetween('rh.operation_at', [$request->date . '000000', $request->date . '235959'])
            // ->union($qry1)
            ->whereIn('rh.table_name', ['tour_slot_dates', 'tour_slot_times'])
            ->whereBetween('rh.operation_at', ["{$request->date}000000", "{$request->date}235959"])
            ->union($qry2)
            ->orderByDesc('operation_at')
            ->take(30)
            ->get($fields);

        // Format response for API calls
        return request()->is('api/*') ? self::formatRecordsHistory($recordsHistory) : $recordsHistory;
    }

    /**
     * Builds the query for fetching records history based on table names and filters.
     *
     * @param int $tourOperatorId The ID of the tour operator.
     * @param array $tables The list of table names to filter.
     * @param string $date The selected date for filtering.
     * @param array $fields The fields to be selected.
     * @param bool $useDateFilter Whether to use a date filter on `entity_info`.
     * @return \Illuminate\Database\Query\Builder The query builder instance.
     */
    private static function getHistoryQuery($tourOperatorId, array $tables, $date, $fields, $useDateFilter = false)
    {
        $query = DB::table('records_history as rh')
            ->leftJoin('admins AS ad', function ($join) {
                $join->on('ad.id', '=', 'rh.operation_by')
                    ->where('rh.operation_by_user_type', '=', 'Admin');
            })
            ->leftJoin('tour_operator_staff AS tos', function ($join) {
                $join->on('tos.id', '=', 'rh.operation_by')
                    ->where('rh.operation_by_user_type', '=', 'Tour Operator');
            })
            ->where('rh.record_id_2', $tourOperatorId)
            ->whereIn('rh.table_name', $tables)
            ->select($fields);

        return $useDateFilter
            ? $query->whereDate('rh.entity_info', $date)
            : $query->whereBetween('rh.operation_at', [$date . '000000', $date . '235959']);
    }

    /**
     * Formats the records history for API responses.
     *
     * @param \Illuminate\Support\Collection $recordsHistory The records history collection.
     * @return \Illuminate\Support\Collection The formatted records history.
     */
    private static function formatRecordsHistory($recordsHistory)
    {
        foreach ($recordsHistory as $key => $record) {
            $recordsHistory[$key]->history_api_data = self::generateOperationDescription($record);

            // Remove unnecessary fields from the API response
            unset($record->operation_type, $record->table_name, $record->entity_info, $record->new_values);
        }

        return $recordsHistory;
    }

    /**
     * Generates a human-readable operation description.
     *
     * @param object $record The individual record history entry.
     * @return string The formatted operation description.
     */
    private static function generateOperationDescription($record)
    {
        // Map table names to user-friendly entity names
        $entity = match ($record->table_name) {
            'tour_packages' => 'tour package',
            'tour_package_rates' => 'age/rate group',
            'tour_slot_dates' => 'tour date',
            'tour_slot_times' => 'tour time slot',
            default => '',
        };

        // Format entity information based on table type
        $entityInfo = $record->table_name === 'tour_slot_dates' || $record->table_name === 'tour_slot_times'
            ? date("m/d/Y", strtotime($record->entity_info))
            : '';

        if ($record->table_name === 'tour_slot_times') {
            $entityInfo .= " @ " . date("g:i A", strtotime($record->entity_info));
        }

        // Determine who performed the operation
        $operationByUser = $record->staff_name
            ? $record->staff_name . ' (Tour Operator)'
            : $record->admin_name . ' (Admin)';

        // Generate a description for the new values
        $newValuesDesc = self::getNewValuesDescription($record->new_values, $record->operation_type);

        // Generate the final operation description
        return match ($record->operation_type) {
            'i' => "{$operationByUser} added {$entity} <b>{$entityInfo}</b>, {$newValuesDesc}",
            'u' => "{$operationByUser} updated {$entity} <b>{$entityInfo}</b>: {$newValuesDesc}",
            'd' => "{$operationByUser} deleted {$entity} <b>{$entityInfo}</b>",
            'e' => "{$operationByUser} sent closure email for {$entity} <b>{$entityInfo}</b>",
            default => '',
        };
    }

    /**
     * Converts JSON new values into a readable format.
     *
     * @param string $newValues The JSON-encoded new values.
     * @param string $operationType The operation type (i = insert, u = update).
     * @return string A formatted string describing the new values.
     */
    private static function getNewValuesDescription($newValues, $operationType)
    {
        if (empty($newValues)) {
            return '';
        }

        $decodedValues = json_decode($newValues, true);
        $joiner = $operationType === 'i' ? ': ' : ' to';
        $descriptions = [];

        foreach ($decodedValues as $key => $value) {
            // Format values based on key type
            $formattedValue = match ($key) {
                'date' => date("m/d/Y", strtotime($value)),
                'from_time', 'to_time' => date("g:i A", strtotime($value)),
                default => $value,
            };

            $descriptions[] = '<b>' . str_replace('_', ' ', $key) . '</b>' . $joiner . ' <b>' . $formattedValue . '</b>';
        }

        return implode(', ', $descriptions);
    }
}
