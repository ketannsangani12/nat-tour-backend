<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
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
		$guard = session('guard');

		if(auth($guard)->user()->status != 'Active') {
			auth($guard)->logout();
			$r->session()->invalidate();
			$r->session()->regenerateToken();
				
			if($guard == 'admin' || $guard == 'customer')
				$routePrefix = $guard;
			else
				$routePrefix = 'tour-operator';
			
			if($r->expectsJson())
				return sendJsonErrMsg('We can not let you log-in. Your account status is Inactive.', 401);
			else {
				return redirect(route("auth.{$routePrefix}.login-form"))
					->withErrors([ 'errors' => 'We can not let you log-in. Your account status is Inactive.' ]);
			}
		}

		return $next($r);
	}
}
