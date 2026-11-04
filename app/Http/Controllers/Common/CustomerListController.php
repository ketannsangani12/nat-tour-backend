<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use App\Jobs\NewsLetterEmails;
use App\Models\Customer;
use Carbon\Carbon;
use Exception;

class CustomerListController extends Controller
{
    /**
     * Display customer list page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('users.common.customer-list-page', [
            'page_title' => 'Customer List'
        ]);
    }

    /**
     * Return DataTable response for today's customers.
     *
     * @param \Yajra\DataTables\DataTables $dt
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexAHR(DataTables $dt)
    {
        try {
            $fromDate = Carbon::today()->toDateString();
            $query = $this->getCustomerQuery($fromDate);

            return $dt->of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($data) {
                    return '<a data-id="' . $data->id . '" class="btn btn-success btn-sm me-3 send-email">Send Email</a>';
                })
                ->rawColumns(['action'])
                ->make(true);

        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }

    /**
     * Return DataTable response for customers based on date.
     *
     * @param \Yajra\DataTables\DataTables $dt
     * @param string $fromDate
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchdate(DataTables $dt, $fromDate)
    {
        try {
            $query = $this->getCustomerQuery($fromDate);

            return $dt->of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($data) {
                    return '<a data-id="' . $data->id . '" class="btn btn-success btn-sm me-3 send-email">Send Email</a>';
                })
                ->rawColumns(['action'])
                ->make(true);

        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }

    /**
     * Send newsletter email to selected customer(s).
     *
     * @param \Illuminate\Http\Request $request
     * @return string|\Illuminate\Http\JsonResponse
     */
    public function sendEmailToCustomer(Request $request)
    {
        try {
            $customerIds = is_array($request->id) ? $request->id : [$request->id];
            $customers = Customer::whereIn('id', $customerIds)->get();

            $userId = Auth::id();
            $userType = userType();

            dispatch(new NewsLetterEmails($customers, $userId, $userType));

            return "Email sent";

        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg($exception->getMessage());
        }
    }

    /**
     * Build base customer query for a given date.
     *
     * @param string $fromDate
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getCustomerQuery($fromDate)
    {
        return DB::table('tour_slot_times AS tst')
            ->join('tour_bookings_master AS tbm', 'tbm.tour_slot_id', '=', 'tst.id')
            ->join('customers AS c', 'c.id', '=', 'tbm.customer_id')
            ->whereDate('tst.date', $fromDate)
            ->where('c.is_affiliate', 'No')
            ->where('tbm.status', '!=', 'Cancelled')
            ->groupBy('c.email')
            ->select([
                'c.id',
                'c.name',
                'c.email',
                'c.phone_number'
            ]);
    }
}
