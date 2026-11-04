<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		// if ($this->app->environment('local')) {
		// 	$this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
		// 	$this->app->register(TelescopeServiceProvider::class);
		// }

		$this->app->bind(PaymentGatewayInterface::class, StripePaymentGateway::class);
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		Schema::defaultStringLength(191);
		if (app()->environment('development') || app()->environment('staging') || app()->environment('production')) {
			URL::forceScheme('https');
		}

		$registrar = new \App\ExtendedRoutes($this->app->make('router'));

		$this->app->bind('Illuminate\Routing\ResourceRegistrar', function () use ($registrar) {
			return $registrar;
		});

		// add .vue file @include-able in .blade.php files
		// without executing server-side using 2nd argument: 'file'


	}
}
