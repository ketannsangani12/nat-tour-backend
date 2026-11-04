<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticated
{
	/**
	* Handle an incoming request.
	*
	* @param \Illuminate\Http\Request $request
	* @param \Closure $next
	* @param string|null ...$guards
	* @return mixed
	*/
	public function handle(Request $request, Closure $next, ...$guards)
	{
		if(
			Auth::guard('admin')->check() &&
			session('userType') == 'Admin' &&
			substr(Route::currentRouteName(), 0, 11) == 'auth.admin.'
		) {
			return redirect()->route('admin.dashboard');
		}

		if(
			Auth::guard('tour_operator_staff')->check() &&
			session('userType') == 'Tour Operator' &&
			substr(Route::currentRouteName(), 0, 19) == 'auth.tour-operator.'
		) {
			return redirect()->route('tour-operator.dashboard');
		}

		if(
			Auth::guard('customer')->check() &&
			session('userType') == 'Customer' &&
			substr(Route::currentRouteName(), 0, 14) == 'auth.customer.'
		) {
			return redirect()->route('customer.tour-bookings');
		}

		return $next($request);
	}
}
