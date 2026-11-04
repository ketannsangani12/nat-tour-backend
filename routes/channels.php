<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Crypt;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/


Broadcast::channel('bookings.admin', function() {
	return true;
}, [ 'middleware' => [ 'validate-broadcast-listening-requests' ] ]);

Broadcast::channel('bookings.{tourOperatorId}', function($tourOperatorId) {
	try {
		$tourOpId = Crypt::decrypt($tourOperatorId);
		$tourOpStaff = \App\Models\Staff::where('tour_operator_id', $tourOpId)->first();

		if($tourOpStaff)
			return true;

		return false;
	}
	catch (Exception $ex) {
		return false;
	}
}, [ 'middleware' => [ 'validate-broadcast-listening-requests' ] ]);

/*
|--------------------------------------------------------------------------
| Tenant real-time channel (new socket architecture)
|--------------------------------------------------------------------------
| Single authenticated channel per tenant.
| Frontend SocketManager subscribes once and routes by the 'feature' field
| embedded in every event payload.
|
| tenant.admin              — admin users
| tenant.{tourOperatorId}   — encrypted tour operator ID (same value as bookings.*)
*/
Broadcast::channel('tenant.admin', function () {
	return true;
}, ['middleware' => ['validate-broadcast-listening-requests']]);

Broadcast::channel('tenant.{tourOperatorId}', function ($tourOperatorId) {
	try {
		$tourOpId    = Crypt::decrypt($tourOperatorId);
		$tourOpStaff = \App\Models\Staff::where('tour_operator_id', $tourOpId)->first();

		return (bool) $tourOpStaff;
	} catch (Exception) {
		return false;
	}
}, ['middleware' => ['validate-broadcast-listening-requests']]);
