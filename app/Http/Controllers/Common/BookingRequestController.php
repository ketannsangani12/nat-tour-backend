<?php

namespace App\Http\Controllers\Common;

use App\Events\BookingRequestNotification;
use App\Exceptions\Core\GeneralCoreException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BookingRequest;
use App\Models\Staff;
use App\Models\Customer;
use App\Services\Booking\BookingService;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\ManagerRequestService;

class BookingRequestController extends Controller
{
	/**
	 * The manager Request and post request table function
	 *
	 */
	public function index(ManagerRequestService $managerRequestService)
	{
	    try {
	        $userType = userType();
	        $vars = ['page_title' => 'Manager Request'];

	        // Fetch manager requests
	        $vars['managerRequests'] = $managerRequestService->getManagerRequests('Pending', $userType);
	        $vars['pastManagerRequests'] = $managerRequestService->getManagerRequests('!Pending', $userType);
	        
	        return view('users.common.booking-request', $vars);
	    } catch (Exception $exception) {
	        Log::error($exception);
	        throw new GeneralCoreException($exception);
	    }
	}


	/**
	 * The Booking deny function
	 *
	 * @param Request $request
	 */
	public function bookingDeny(Request $request)
	{
		try {
			$bookingRequest = BookingRequest::find($request->id);
			$bookingRequest->status = "Deny";
			$bookingRequest->save();

			$tenant = tourOperator();
			broadcast(new BookingRequestNotification($bookingRequest, $tenant));

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 * The Booking approve function
	 *
	 * @param Request $request
	 */
	public function bookingApprove(Request $request)
	{
		try {
			$bookingRequest = BookingRequest::find($request->id);
			$bookingRequest->status = "Approved";
			$bookingRequest->save();

			$tenant = tourOperator();
			broadcast(new BookingRequestNotification($bookingRequest, $tenant));

			return true;
		} catch (Exception $exception) {
			Log::error($exception);
			throw new GeneralCoreException($exception);
		}
	}
	/**
	 * The Booking Request Info function
	 *
	 * @param int $id
	 */
	public function bookingRequestInfo($id)
	{
	    try {
	        $userType = userType();
	        $managerRequestQuery = DB::table('booking_requests as br')
	            ->join('tour_operator_staff as tos', 'tos.id', '=', 'br.manager_id')
	            ->where('br.id', $id)
	            ->select(
	                'br.bookings',
	                'br.order_id',
	                'br.created_at',
	                'br.request_type',
	                'br.id',
	                'br.details',
	                'br.staff_type',
	                'br.staff_id',
	                DB::raw("CONCAT(tos.first_name, ' ', tos.last_name) as managerName")
	            );

	        // Filter for Tour Operator type
	        if ($userType == "Tour Operator") {
	            $managerRequestQuery->where('br.manager_id', auth()->user()->id);
	        }

	        // Fetch manager request info
	        $managerRequestInfo = $managerRequestQuery->first();

	        if (!$managerRequestInfo) {
	            throw new Exception('Unable to find Manager Request Info.');
	        }

	        // Fetch details
	        $details = ManagerRequestService::fetchDetailsOfRequestFields(
	            $managerRequestInfo->request_type, 
	            $managerRequestInfo->details, 
	            $managerRequestInfo->bookings
	        );
	        $managerRequestInfo->details = $details;

	        // Extract booking IDs
	        $bookingIds = explode(',', $managerRequestInfo->bookings);

	        // Get payment transaction and tour bookings in a single query
	        $result = DB::table('tour_bookings_master as tbm')
	            ->join('tour_slot_times as tst', 'tst.id', '=', 'tbm.tour_slot_id')
	            ->join('tour_packages as tp', 'tp.id', '=', 'tbm.tour_package_id')
	            ->leftJoin('transactions as t', 't.tour_booking_id', '=', 'tbm.id')
	            ->whereIn('tbm.id', $bookingIds)
	            ->where('t.status', 'Completed')
	            ->select(
	                't.via as paymentMethod',
	                'tst.date',
	                'tst.time_from',
	                'tbm.id as tourBookingId',
	                'tbm.name as customerName',
	                'tp.name as tourPackageName'
	            )
	            ->distinct()
	            ->get();

	        // Get payment method (via) from first transaction if available
	        $paymentMethod = $result->first()?->paymentMethod ?? '';

	        return response()->json([
	            'success' => true,
	            'managerRequestInfo' => $managerRequestInfo,
	            'tourBookings' => $result,
	            'paymentMethod' => $paymentMethod
	        ]);
	    } catch (Exception $exception) {
	        Log::error($exception);
	        throw new GeneralCoreException($exception);
	    }
	}

}
