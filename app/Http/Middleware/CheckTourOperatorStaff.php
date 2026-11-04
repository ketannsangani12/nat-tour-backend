<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckTourOperatorStaff
{
	/**
	* Handle an incoming request.
	*
	* @param \Illuminate\Http\Request $r
	* @param \Closure $next
	* @return mixed
	*/
	public function handle(Request $r, Closure $next)
	{
		if(!auth('tour_operator_staff')->check() || session('userType') != 'Tour Operator') {
			if($r->expectsJson()){
				return sendJsonErrMsg('Session has expired. Please re-login.', 401);
			}
			else {
				return redirect(route('auth.tour-operator.login-form'))
				->withErrors([ 'errors' => 'Session has expired. Please re-login.' ]);
			}
		}

		$roleNames = "";
		if(session('userType') == 'Tour Operator') {
			$roleNames = DB::table('tour_operator_staff_roles as tosd')
			->select('r.id')
			->join('roles as r', 'r.id' ,'=', 'tosd.role_id')
			->where('tosd.deleted_by', '=', '0')
			->where('tosd.tour_operator_staff_id', '=', auth()->user()->id)
			->get()->pluck('id');
		}
		$routeName = $r->route()->getName();

		if (!auth('tour_operator_staff')->check()) {
			if ($r->expectsJson() && $roleNames->contains("4") && $roleNames->contains("2")) {
				if($routeName == "tour-operator.dashboard" || $routeName == "tour-operator.booking.calendar" || $routeName == "tour-operator.booking.create") {
					return $next($r);
				} else {
					return redirect(route('auth.tour-operator.login-form'))
						->withErrors([ 'errors' => 'You are not allowed to access this page.' ]);
				}
			} else {
				return redirect(route('auth.tour-operator.login-form'))
					->withErrors([ 'errors' => 'Session has expired. Please re-login.' ]);
			}
		}

		return $next($r);
	}
}
