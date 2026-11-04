<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Authentication\UserProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
	/**
	* The policy mappings for the application.
	*
	* @var array
	*/
	protected $policies = [
	];

	/**
	* Register any authentication / authorization services.
	*
	* @return void
	*/
	public function boot()
	{
		$this->registerPolicies();
		// Implicitly grant "super-admin" role all permission checks using can()
        Gate::before(function ($user, $ability) {
             if ($user->hasRole('super-admin')) {
                 return true;
             }
        });
	}
}
