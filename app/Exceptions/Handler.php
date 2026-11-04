<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
	/**
	 * A list of the exception types that are not reported.
	 *
	 * @var array
	 */
	protected $dontReport = [
		\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException::class,
	];

	/**
	 * A list of the inputs that are never flashed for validation exceptions.
	 *
	 * @var array
	 */
	protected $dontFlash = [
		'password',
		'password_confirmation',
	];

	private $errMsg = 'Session has expired. Please re-login.';

	/**
	 * Register the exception handling callbacks for the application.
	 *
	 * @return void
	 */
	public function register()
	{
		$t = $this;

		$this->reportable(function (Throwable $e) use ($t) {
			if ($e instanceof \Illuminate\Session\TokenMismatchException)
				return $t->buildUnauthenticatedResponse();

			if ($e instanceof \Carbon\Exceptions\InvalidFormatException) {
				Log::error('Carbon parse issue: ' . $e->getMessage(), [
					'trace' => $e->getTraceAsString(),
				]);
			}
		});
	}

	protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $ex)
	{
		return $this->buildUnauthenticatedResponse();
	}

	private function buildUnauthenticatedResponse()
	{
		if (request()->expectsJson()) {
			if (request()->is('api/*')) {
				return response()->json([
					'code' => 401,
					'message' => __('general.unauthorized'),
				], 401);
			} else {
				return sendJsonErrMsg($this->errMsg, 401);
			}
		} else {
			if (substr(Route::currentRouteName(), 0, 6) == 'admin.') {
				$user = 'admin';
			} elseif (substr(Route::currentRouteName(), 0, 14) == 'tour-operator.') {
				$user = 'tour-operator';
			} elseif (substr(Route::currentRouteName(), 0, 10) == 'affiliate.') {
				$user = 'affiliate';
			} else {
				$user = 'customer';
			}

			return redirect()
				->route("auth.{$user}.login-form")
				->withErrors(['errors' => $this->errMsg]);
		}
	}

	public function render($request, Throwable $ex)
	{
		// Handle tenancy identification errors gracefully
		if ($ex instanceof \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException) {
			return $request->expectsJson()
				? response()->json(['error' => 'Invalid domain'], 404)
				: response()->view('errors.404', ['error' => 'Domain not found']);
		}

		if ($ex instanceof MethodNotAllowedHttpException) {
			return $request->expectsJson()
				? sendJsonErrMsg($ex->getMessage(), 401)
				: back()
				->withErrors(['errors' => $ex->getMessage()])
				->withInput();
		}

		if ($ex instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
			return request()->expectsJson()
				? sendJsonErrMsg('Record not found.', 404)
				: response()->view('errors.404', ['error' => 'Record not found.']);
		}

		if ($ex instanceof \Illuminate\Session\TokenMismatchException)
			return request()->expectsJson()
				? sendJsonErrMsg(['The page seems expired.', 'Please refresh the page, and relogin if required.'], 419)
				: abort(419);

		return parent::render($request, $ex);
	}
}
