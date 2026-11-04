<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCustomer
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
		if(!auth('customer')->check() || session('userType') != 'Customer') {
			if($r->expectsJson())
				return sendJsonErrMsg('Session has expired. Please re-login.', 401);
			else {
				return redirect(route('auth.customer.login-form'))
					->withErrors([ 'errors' => 'Session has expired. Please re-login.' ]);
			}
		}

		return $next($r);
	}
}
