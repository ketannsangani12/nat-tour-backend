<?php

namespace App\Repositories\Agenda;

use App\Exceptions\Core\GeneralCoreException;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\TourOperator;
use App\Models\TourPackage;
use App\Models\TourPackageRate;
use Exception;
use App\Repositories\Repository;
use App\Services\Agenda\AgendaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AgendaRepository extends Repository
{
    /**
     * @var string
     */
    public $customer = Customer::class;

    public $tourPackage = TourPackage::class;

    public $tourPackageRate = TourPackageRate::class;

    public $tourOperator = TourOperator::class;

    public $hotel = Hotel::class;

    /**
     * @param  object  $request
     * @param  string $from
     * @param  string $to
     * @param  int $packageId
     * @param  int $customerId
     * 
     * @return array
     *
     * @throws Exception
     */
    public function initAgenda(object $request, string $from, string $to, int $packageId, int $customerId): array
    {
        try {
            $response['page_title'] = 'Agenda';

            $departmentPackage = departmentPackage();

            $response['packages'] = DB::table('tour_packages AS tp')
                ->where('tp.status', 'Active')
                ->when(userType() == 'Affiliate', function ($query) {
                    return $query->join('package_affiliate_assignments AS paa', 'paa.package_id', '=', 'tp.id')
                        ->where('paa.affiliate_id', Auth::id());
                })
                ->when($departmentPackage, function ($q) use ($departmentPackage) {
                    return $q->whereIn('tp.id', $departmentPackage);
                })
                ->get();

            $response['customers'] = DB::table('customers AS c')
                ->where('c.is_affiliate', 'Yes')
                ->when(userType() == 'Affiliate', function ($query) {
                    return $query->where('c.id', Auth::id());
                })
                ->when($departmentPackage, function ($q) {
                    return $q->where('c.department_id', Auth::user()->department_id);
                })
                ->get();

            $request->per_page ??= 50;
            $request->sort_field ??= 'tbm.id';
            $request->sort_order ??= 'asc';

            // Clone the original query to use it for counting and summing
            $baseQuery = DB::table('tour_bookings_master AS tbm')
                ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
                ->join('customers as c', 'c.id', '=', 'tbm.customer_id')
                ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
                ->leftJoin(DB::raw('
                    (SELECT booking_id, 
                            MAX(id) as latest_dispute_id,
                            COUNT(*) as dispute_count
                     FROM disputes 
                     WHERE status IN ("warning_needs_response", "under_review")
                     GROUP BY booking_id) as active_disputes
                '), 'tbm.id', '=', 'active_disputes.booking_id')
                ->leftJoin('disputes AS d', 'd.id', '=', 'active_disputes.latest_dispute_id')
                ->where('c.is_affiliate', 'Yes')
                ->whereBetween('tst.date', [$from, $to])
                ->when($packageId != 0, function ($query) use ($packageId) {
                    return $query->where('tbm.tour_package_id', '=', $packageId);
                })
                ->when($customerId != 0, function ($query) use ($customerId) {
                    return $query->where('c.id', '=', $customerId);
                })
                ->when(userType() == 'Affiliate', function ($query) {
                    return $query->where('c.id', Auth::id());
                })
                ->when($departmentPackage, function ($q) {
                    return $q->where('c.department_id', Auth::user()->department_id);
                });

            // Total number of bookings
            $response['total_bookings'] = $baseQuery->count();

            // Sum of tourists
            $response['total_tickets'] = $baseQuery->sum('tbm.tourists');

            // Sum of tourists
            $response['total_orders'] = $baseQuery->distinct()->count('tbm.group_id');

            // Query for paginated booking data 
            $data = $baseQuery->select(
                'tbm.id as bookingid',
                'tbm.status',
                'tbm.total',
                'tbm.booking_total',
                'tbm.tourists',
                'tbm.is_group_booking',
                DB::raw('DATE_FORMAT(tbm.created_at, "%Y-%m-%d") as created_at'),
                DB::raw('DATE_FORMAT(tst.date, "%Y-%m-%d") as bookingdate'),
                'tbm.name as name',
                'tp.id as packageid',
                'tp.name as package',
                'tp.checkin',
                'tp.checkin_type',
                'tp.bundle_package',
                'tp.package_has_waiver',
                // Dispute information
                'd.id AS dispute_id',
                'd.stripe_dispute_id',
                'd.status AS dispute_status',
                'd.reason AS dispute_reason',
                'd.amount AS dispute_amount',
                'd.currency AS dispute_currency',
                'd.evidence_due_by AS dispute_evidence_due_by',
                'active_disputes.dispute_count',
                DB::raw(
                    "IFNULL((SELECT GROUP_CONCAT(
                            CONCAT(tpbr.tour_package_id, ':', tpbr.package_name)
                            ORDER BY tp.name ASC
                            SEPARATOR '|'
                        )
                        FROM tour_package_bundle_relations AS tpbr
                        WHERE tbm.tour_package_id = tpbr.parent_id
                        AND tpbr.deleted_by = 0), CONCAT(tp.id, ':', tp.name)) as packages"
                ),
            )
                ->orderBy($request->sort_field, $request->sort_order)
                ->paginate($request->per_page);

            // Extract booking IDs
            $bookingIds = $data->pluck('bookingid');

            // Fetch transactions and map them by booking ID
            $transactions = DB::table('transactions as tra')
                ->whereIn('tra.tour_booking_id', $bookingIds)
                ->where('tra.status', '!=', 'Failed')
                ->select(
                    'tra.amount',
                    'tra.fee_amount',
                    'tra.via',
                    'tra.status',
                    'tra.tour_booking_id as bookingid'
                )
                ->get()
                ->groupBy('bookingid');

            // Fetch tour booking transact and map them by booking ID
            $tourBookingTransact = DB::table('tour_bookings_transact as tbt')
                ->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
                ->whereIn('tbt.tour_booking_id', $bookingIds)
                ->where('tbt.deleted_by', 0)
                ->select(
                    'tpr.rate_for',
                    'tbt.tourists',
                    'tbt.check_in_status',
                    'tbt.redeem_ticket',
                    'tbt.tour_booking_id as bookingid'
                )
                ->get()
                ->groupBy('bookingid');

            // Update booking data with transaction details
            $data->transform(function ($item) use ($transactions, $tourBookingTransact) {
                $item->paid_amount = 0;
                $item->refund_amount = 0;

                if (isset($transactions[$item->bookingid])) {
                    foreach ($transactions[$item->bookingid] as $transaction) {
                        if ($transaction->status == 'Completed') {
                            $item->paid_amount += (float) $transaction->amount;
                        }

                        if ($transaction->status == 'Refund') {
                            $item->refund_amount += (float) $transaction->amount;
                            $item->paid_amount -= (float) $transaction->amount;
                        }
                    }
                }

                // Add dispute information
                $item->has_active_dispute = !is_null($item->dispute_id);
                $item->dispute_info = null;
                
                if ($item->has_active_dispute) {
                    $item->dispute_info = [
                        'id' => $item->stripe_dispute_id,
                        'status' => $item->dispute_status,
                        'reason' => $item->dispute_reason,
                        'amount' => $item->dispute_amount,
                        'currency' => $item->dispute_currency,
                        'evidence_due_by' => $item->dispute_evidence_due_by,
                        'dispute_count' => $item->dispute_count ?? 1,
                        'status_label' => $this->getDisputeStatusLabel($item->dispute_status),
                        'reason_label' => $this->getDisputeReasonLabel($item->dispute_reason),
                        'css_class' => $this->getDisputeStatusCssClass($item->dispute_status),
                        'requires_attention' => $this->disputeRequiresAttention($item->dispute_status, $item->dispute_evidence_due_by),
                        'days_until_deadline' => $this->getDaysUntilDeadline($item->dispute_evidence_due_by)
                    ];
                }

                $item->class = 'btn btn-outline-warning btn-sm';
                $item->text = 'Not Applicable';

                if (isset($item->packages)) {
                    $explodePackage = explode('|', $item->packages);
                    if (count($explodePackage) > 0) {
                        foreach ($explodePackage as $key => $package) {
                            $exPackage = explode(':', $package);
                            $item->package_list[$key] = (object) [
                                'id' => $exPackage[0],
                                'name' => $exPackage[1],
                                'redeemed' => 0,
                                'not_redeemed' => 0,
                                'total' => 0,
                                'class' => '',
                                'af_class' => ''
                            ];
                        }
                    }
                }

                AgendaService::getCheckinsStatus($item, $tourBookingTransact);

                return $item;
            });

            $response['data'] = $data;

            return $response;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  object  $request
     * @param  string $from
     * @param  string $to
     * @param  int $packageId
     * @param  int $customerId
     * 
     * @return mixed
     *
     * @throws Exception
     */
    public function downloadAffiliateAgenda(object $request, string $from, string $to, int $packageId, int $customerId): mixed
    {
        try {
            $sortedDataArray = $this->initAgenda($request, $from, $to, $packageId, $customerId);

            $columns = ['Booking #', 'Package', 'Name', 'Guest(s)', 'Booking Date', 'Created Date', 'Booking Amount', 'Paid Amount', 'Status', 'Redeem'];
            $columnsHeader = ['From Date', 'To Date', 'Number of Orders', 'Number of Bookings', 'Number of Tickets'];

            $name = "agenda_report_{$from}_$to.csv";

            $callback = function () use ($sortedDataArray, $columns, $columnsHeader, $from, $to) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columnsHeader);

                fputcsv($file, [formatDate($from), formatDate($to), $sortedDataArray['total_orders'], $sortedDataArray['total_bookings'], $sortedDataArray['total_tickets']]);

                $BlankLine = '';
                fputcsv($file, [$BlankLine]);

                fputcsv($file, $columns);
                foreach ($sortedDataArray['data'] as $key => $value) {
                    $row = [
                        $value->bookingid,
                        $value->package,
                        $value->name,
                        $value->tourists,
                        formatDate($value->bookingdate),
                        formatDate($value->created_at),
                        number_format($value->booking_total, 2),
                        number_format($value->paid_amount, 2),
                        $value->status,
                        $value->text
                    ];

                    fputcsv($file, $row);
                }

                fputcsv($file, array_merge(array_fill(0, 12, $BlankLine)));
                fclose($file);
            };

            return response()->stream($callback, 200, csvHeaders($name));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  object $bookings
     * 
     * @return object
     *
     */
    public function getCheckInsAndTickets(object $bookings, $tenant = null): object
    {
        $bookingIds = $bookings->pluck('booking_id');

        // Choose DB connection based on tenant
        $db = $tenant ? DB::connection('master') : DB::connection();

        // Fetch tour booking transactions
        $tourBookingTransact = $db->table('tour_bookings_transact AS tbt')
            ->join('tour_package_rates AS tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
            ->whereIn('tbt.tour_booking_id', $bookingIds)
            ->where('tbt.deleted_by', 0)
            ->get([
                'tbt.tourists',
                'tbt.infants',
                'tbt.check_in_status',
                'tpr.rate_for',
                'tbt.redeem_ticket',
                'tbt.tour_booking_id as bookingid'
            ])
            ->groupBy('bookingid');

        // Transform bookings
        $bookings->transform(function ($item) use ($tourBookingTransact , $tenant) {
            AgendaService::getCheckinsStatus($item, $tourBookingTransact, $tenant);
            return $item;
        });
        return $bookings;
    }


    /**
     * @param  object $bookings
     * 
     * @return object
     *
     */
    public function bookingContacts(object $bookings, $tenant = null): object
    {
        $bookingIds = $bookings->pluck('booking_id');

        // Use master connection if tenant is provided
        $db = $tenant ? DB::connection('master') : DB::connection();

        // Fetch contacts for booking IDs
        $contacts = $db->table('tour_booking_contacts AS tbc')
            ->join('tour_bookings_transact AS tbt', 'tbt.id', '=', 'tbc.tour_booking_transact_id')
            ->whereIn('tbc.tour_booking_id', $bookingIds)
            ->where('tbt.deleted_by', 0)
            ->get([
                'tbc.id as contact_id',
                'tbc.tour_booking_id as bookingid',
                'tbc.name',
                'tbc.age',
                'tbc.weight',
                'tbc.note',
                'tbt.id as transact_id',
            ])
            ->groupBy('bookingid');

        // Attach contacts to bookings
        $bookings->transform(function ($item) use ($contacts) {
            $item->contacts = [];

            $key = $item->booking_id ?? $item->bookingid ?? null;

            if ($key && isset($contacts[$key])) {
                foreach ($contacts[$key] as $index => $contact) {
                    $item->contacts[$index] = $contact;
                }
            }

            return $item;
        });

        return $bookings;
    }

    /**
     * @param  array $agendaData
     * 
     * @return object
     *
     */
    public function rosterReportCSV($agendaData)
    {
        $tourPackage = $this->tourPackage::findOrFail($agendaData['tourPackagesId']);
        $convertedDate = date("m-d-Y", strtotime($agendaData['date']));
        $packageName = strtolower(str_replace(' ', '_', $tourPackage->name));
        $fileName = $packageName . '_report_' . $convertedDate . '.csv';
        $headers = csvHeaders($fileName);
        $columnsHeader = csvColumns('wwr_roster_report');

        $callback = function () use ($agendaData, $columnsHeader, $convertedDate) {
            $file = fopen('php://output', 'w');  // Create a file
            $blankLine = '';

            $tourPackageId = 0;
            $slotId = 0;
            $agendaView = agendaView();
            $dayBookings = $agendaData['dayBookings'];

            // Slot wise booking list
            if (!empty($dayBookings)) {
                for ($i = 0, $ic = count($dayBookings); $i < $ic; $i++) {
                    $agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';

                    if ($dayBookings[$i]->tour_package_id != $tourPackageId || $dayBookings[$i]->$agendaViewId != $slotId) {
                        $tourPackageId = $dayBookings[$i]->tour_package_id;
                        $slotId = $dayBookings[$i]->$agendaViewId;

                        fputcsv($file, $columnsHeader);

                        $serialCount = 1;
                    }

                    if (count($dayBookings[$i]->contacts) > 0) {
                        for ($y = 0; $y < count($dayBookings[$i]->contacts); $y++) {
                            $contact = $dayBookings[$i]->contacts[$y];

                            $serialNo = $serialCount;
                            $packageName = isset($dayBookings[$i]->tour_package_name) ? $dayBookings[$i]->tour_package_name : '';
                            $date = isset($dayBookings[$i]->date) ? date("l, F d, Y", strtotime($dayBookings[$i]->date)) : '';
                            $time = (isset($dayBookings[$i]->time_from) && isset($dayBookings[$i]->time_to)) ? date("g:i A", strtotime($dayBookings[$i]->time_from)) . ' - ' . date("g:i A", strtotime($dayBookings[$i]->time_to)) : '';
                            $duration = isset($dayBookings[$i]->hours) ? $dayBookings[$i]->hours . ' Hours' : '';
                            $bookingId = isset($dayBookings[$i]->booking_id) ? $dayBookings[$i]->booking_id : '';
                            $customer = isset($dayBookings[$i]->customer_name) ? $dayBookings[$i]->customer_name : '';
                            $contactName = isset($contact->name) ? $contact->name : '';
                            $contactAge = isset($contact->age) ? $contact->age : '';
                            $contactWeight = isset($contact->weight) ? $contact->weight : '';
                            $contactNote = isset($contact->note) ? $contact->note : '';

                            fputcsv($file, array($serialNo, $packageName, $date, $time, $duration, $bookingId, $customer, $contactName, $contactAge, $contactWeight, $contactNote));

                            $serialCount++;
                        }
                    }

                    if ($i == $ic - 1 || $dayBookings[$i + 1]->tour_package_id != $tourPackageId || $dayBookings[$i + 1]->$agendaViewId != $slotId) {
                        fputcsv($file, array($blankLine));
                        fputcsv($file, array($blankLine));
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @param array $agendaData
     * 
     * @return mixed
     *
     */
    public function rosterReportPDF($agendaData)
    {
        $tourPackage = $this->tourPackage::select('name')->findOrFail($agendaData['tourPackagesId']);
        $convertedDate = date("m-d-Y", strtotime($agendaData['date']));
        $packageName = strtolower(str_replace(' ', '_', $tourPackage->name));
        $fileName = $packageName . '_report_' . $convertedDate . '.pdf';

        $tourPackageId = 0;
        $slotId = 0;
        $agendaView = agendaView();
        $dayBookings = $agendaData['dayBookings'];
        $contacts['package_name'] = $tourPackage->name;
        $counter = 0;

        if (!empty($dayBookings)) {
            for ($i = 0, $ic = count($dayBookings); $i < $ic; $i++) {
                $agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';

                if ($dayBookings[$i]->tour_package_id != $tourPackageId || $dayBookings[$i]->$agendaViewId != $slotId) {
                    $tourPackageId = $dayBookings[$i]->tour_package_id;
                    $slotId = $dayBookings[$i]->$agendaViewId;
                    $contactInfo = [];

                    $contacts['contacts'][$counter] = [
                        'date' => isset($dayBookings[$i]->date) ? date("m/d/Y", strtotime($dayBookings[$i]->date)) : '',
                        'time' => isset($dayBookings[$i]->time_from) ? date("g:i A", strtotime($dayBookings[$i]->time_from)) : ''
                    ];
                }

                if (count($dayBookings[$i]->contacts) > 0) {
                    for ($y = 0; $y < count($dayBookings[$i]->contacts); $y++) {
                        $contact = $dayBookings[$i]->contacts[$y];

                        $contactInfo[] = [
                            'booking_id' => $dayBookings[$i]->booking_id,
                            'name' => ucwords($contact->name),
                            'age' => $contact->age,
                            'weight' => $contact->weight,
                            'note' => $contact->note
                        ];

                        $contacts['contacts'][$counter]['data'] = $contactInfo;
                    }
                }

                if ($i == $ic - 1 || $dayBookings[$i + 1]->tour_package_id != $tourPackageId || $dayBookings[$i + 1]->$agendaViewId != $slotId) {
                    $counter++;
                }
            }
        }

        $contacts['datetime'] = date("m/d/Y g:i A", strtotime(mysqlDT()));
        $contacts['user_name'] = Auth::user()->name;
        $contacts['user_id'] = Auth::user()->id;
        $contacts['user_type'] = Auth::user()->roles->first()->display_name;

        $dompdf = PDF::loadView('users.common.agenda.roster-report-pdf', $contacts);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4');

        // Render the HTML as PDF
        $dompdf->render();

        $font = $dompdf->getFontMetrics()->getFont("helvetica", "bold");

        $dompdf->getCanvas()->page_text(508, 785, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 10, array(0, 0, 0));

        return $dompdf->download($fileName);
    }

    /**
     * Get human-readable dispute status label.
     */
    private function getDisputeStatusLabel(?string $status): string
    {
        if (!$status) return '';
        
        return match($status) {
            'warning_needs_response' => 'Needs Response',
            'under_review' => 'Under Review',
            'charge_refunded' => 'Charge Refunded',
            'lost' => 'Lost',
            'won' => 'Won',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    /**
     * Get human-readable dispute reason label.
     */
    private function getDisputeReasonLabel(?string $reason): string
    {
        if (!$reason) return '';
        
        return match($reason) {
            'fraudulent' => 'Fraudulent',
            'subscription_canceled' => 'Subscription Canceled',
            'product_unacceptable' => 'Product Unacceptable',
            'credit_not_processed' => 'Credit Not Processed',
            'duplicate' => 'Duplicate',
            'product_not_received' => 'Product Not Received',
            'unrecognized' => 'Unrecognized',
            'general' => 'General',
            default => ucfirst(str_replace('_', ' ', $reason))
        };
    }

    /**
     * Get CSS class for dispute status styling.
     */
    private function getDisputeStatusCssClass(?string $status): string
    {
        if (!$status) return '';
        
        return match($status) {
            'warning_needs_response' => 'bg-red-100 text-red-800 border-red-200',
            'under_review' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'charge_refunded' => 'bg-blue-100 text-blue-800 border-blue-200',
            'lost' => 'bg-red-100 text-red-800 border-red-200',
            'won' => 'bg-green-100 text-green-800 border-green-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200'
        };
    }

    /**
     * Check if dispute requires immediate attention.
     */
    private function disputeRequiresAttention(?string $status, ?string $evidenceDueBy): bool
    {
        if ($status !== 'warning_needs_response' || !$evidenceDueBy) {
            return false;
        }

        $daysUntilDeadline = $this->getDaysUntilDeadline($evidenceDueBy);
        return $daysUntilDeadline !== null && $daysUntilDeadline <= 3;
    }

    /**
     * Get days until evidence deadline.
     */
    private function getDaysUntilDeadline(?string $evidenceDueBy): ?int
    {
        if (!$evidenceDueBy) {
            return null;
        }
        
        try {
            $deadline = Carbon::parse($evidenceDueBy);
            return now()->diffInDays($deadline, false);
        } catch (Exception $e) {
            return null;
        }
    }
}
