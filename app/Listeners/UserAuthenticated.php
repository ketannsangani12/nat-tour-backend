<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Authenticated;

class UserAuthenticated
{
	/**
	* Create the event listener.
	*
	* @return void
	*/
	public function __construct()
	{
		if(empty(session('userType'))) {
			if(auth('admin')->id()) {
				session([
					'guard' => 'admin',
					'userType' => 'Admin',
				]);
			}
			elseif(auth('tour_operator_staff')->id()) {
				session([
					'guard' => 'tour_operator_staff',
					'userType' => 'Tour Operator',
				]);
			}
			elseif(auth('customer')->id()) {
				session([
					'guard' => 'customer',
					'userType' => 'Customer',
				]);
			}

			// Clear calendar session values on fresh login to start with current month
			session()->forget([
				'calendar_date',
				'calendar_month',
				'calendar_year',
				'calendar_week',
				'calendar_option'
			]);
		}
	}

	/**
	* Handle the event.
	*
	* @param  Authenticated  $event
	* @return void
	*/
	public function handle(Authenticated $event)
	{
		//
	}
}
