<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use App\Http\Requests\Common\ResetPasswordRequest;
use Illuminate\Support\Facades\Route;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\Staff;
use App\Models\TourPackage;
use App\Models\TourPackageEmailTemplate;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use App\Utils\IpGeolocationUtils;

class AuthController extends Controller
{
	use AuthenticatesUsers;

	public function customerRegisterForm(Request $request)
	{
		Log::warning('Registration attempt blocked: registration is not allowed on this platform.', [
			'ip' => $request->ip(),
			'url' => $request->fullUrl(),
			'user_agent' => $request->userAgent(),
		]);

		abort(404);
	}

	/**
	 * Admin Login Form View
	 */
	public function adminLoginForm()
	{
		$vars = [
			'page_title' => 'Admin Log-in',
			'formSubmitRoute' => 'auth.admin.login',
			'forgotPasswordRoute' => 'auth.admin.forgot-password-form',
		];

		return view('login-2', $vars);
	}

	/**
	 * Admin Login Method
	 * 
	 * @param Request $request
	 */
	public function adminLogin(Request $request)
	{
		$prevUriSegments = $this->prev_segments(session('link_to_redirect'));

		if (!isset($prevUriSegments[0]) || (isset($prevUriSegments[0]) && $prevUriSegments[0] == 'tour-operator')) {
			session(['link_to_redirect' => null]);
		}

		return $this->commonLogin(
			$request,
			[
				'guard' => 'admin',
				'userType' => 'Admin',
				'routeUserHome' => 'admin.dashboard',
				'link_to_redirect' => session('link_to_redirect')
			]
		);
	}

	/**
	 * Tour Operator Staff Login Form View
	 */
	public function tourOpStaffLoginForm()
	{
		$vars = [
			'page_title' => 'Tour Operator Staff Log-in',
			'formSubmitRoute' => 'auth.tour-operator.login',
			'forgotPasswordRoute' => 'auth.tour-operator.forgot-password-form',
		];

		return view('tour-operator-login-form', $vars);
	}

	/**
	 * Tour Operator Login Form View
	 */
	public function tourOpStaffLogin(Request $r)
	{
		$prevUriSegments = $this->prev_segments(session('link_to_redirect'));

		if (!isset($prevUriSegments[0]) || (isset($prevUriSegments[0]) && $prevUriSegments[0] == 'admin')) {
			session(['link_to_redirect' => null]);
		}

		return $this->commonLogin(
			$r,
			[
				'guard' => 'tour_operator_staff',
				'userType' => 'Tour Operator',
				'routeUserHome' => 'tour-operator.dashboard',
				'link_to_redirect' => session('link_to_redirect')
			]
		);
	}

	/**
	 * Customer Login Form View
	 */
	public function customerLoginForm()
	{
		$vars = [
			'page_title' => 'Customer Log-in',
			'formSubmitRoute' => 'auth.customer.login',
			'forgotPasswordRoute' => 'auth.customer.forgot-password-form',
		];

		if (App::environment(['local', 'demo'])) {
			$vars['tourOperators'] = DB::table('tour_operators')
				->where('status', 'Active')
				->orderBy('name')
				->get(['id', 'name']);
		}

		return view('login', $vars);
	}

	/**
	 * Affiliate Login Form View
	 */
	public function affiliateLoginForm()
	{
		$prevUriSegments = $this->prev_segments(session('link_to_redirect'));

		if (!isset($prevUriSegments[0]) || (isset($prevUriSegments[0]) && ($prevUriSegments[0] == 'admin' || $prevUriSegments[0] == 'tour-operator'))) {
			session(['link_to_redirect' => null]);
		}

		$vars = [
			'page_title' => 'Affiliate Log-in',
			'formSubmitRoute' => 'auth.affiliate.login',
			'forgotPasswordRoute' => 'auth.customer.forgot-password-form',
		];

		if (App::environment(['local', 'demo'])) {
			$vars['tourOperators'] = DB::table('customers')
				->where('status', 'Active')
				->orderBy('name')
				->get(['id', 'name']);
		}

		return view('login-3', $vars);
	}

	/**
	 * Affiliate Login Method
	 * 
	 * @param Request $request
	 */
	public function affiliateLogin(Request $request)
	{
		return $this->commonLogin(
			$request,
			[
				'guard' => 'affiliate',
				'userType' => 'Affiliate',
				'routeUserHome' => 'affiliate.dashboard',
				'link_to_redirect' => session('link_to_redirect')
			]
		);
	}

	/**
	 * Customer Login Method
	 * 
	 * @param Request $request
	 */
	public function customerLogin(Request $request)
	{
		try {
			$customer = Customer::where('tour_operator_id', $request->tour_operator)
				->where('email', $request->email)
				->first();

			if (!$customer) {
				return back()
					->withErrors([
						'errors' => 'Incorrect email address and/or password and/or tour operator combinations.'
					])
					->withInput($request->except('password'));
			}

			$customer->makeVisible('password');

			if (!Hash::check($request->password, $customer->password)) {
				return back()
					->withErrors([
						'errors' => 'Incorrect email address and/or password and/or tour operator combinations.'
					])
					->withInput($request->except('password'));
			}

			if ($customer->status != 'Active') {
				return back()
					->withErrors([
						'errors' => "Your account's status isn't active. Can not log you in."
					])
					->withInput($request->except('password'));
			}

			Auth::guard('customer')->login($customer, $request->has('remember'));

			// Update customer's IP address and location data
			$customer = Customer::where('email', $request->email)
				->where('tour_operator_id', $request->tour_operator)
				->first();

			if ($customer) {
				$customer->update(['ip_address' => $request->ip()]);

				// Get and store location data
				IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $request->ip());
			}

			session([
				'guard' => 'customer',
				'userType' => 'Customer',
			]); // set these in Listener > UserAuthenticated as well

			return redirect()->intended(route('customer.tour-bookings'));
		} catch (Exception $exception) {
			Log::error($exception);

			return back()
				->withErrors(['errors' => 'Unable to log you in presently, please try again later.'])
				->withInput($request->except('password'));
		}
	}

	/**
	 * Common Login for all roles
	 * 
	 * @param Request $request
	 * @param array $params
	 */
	private function commonLogin(Request $request, $params)
	{
		if (Auth::guard($params['guard'])->check()) {
			Auth::guard($params['guard'])->logout();
			$request->session()->invalidate();
			$request->session()->regenerateToken();
		}

		if (Auth::guard($params['guard'])->attempt([
			'email' => $request->email,
			'password' => $request->password,
			"status" => "Active"
		], $request->has('remember'))) {
			session([
				'guard' => $params['guard'],
				'userType' => $params['userType'],
			]); // set these in Listener > UserAuthenticated as well
			$token = \App\Models\Token::create([
				'purpose' => 'Listening Broadcast',
				'user_id' => auth($params['guard'])->id(),
				'user_type' => $params['userType'],
				'csrf_token' => csrf_token(),
			]);

			if ($params['guard'] == "admin") {
				Admin::where('email', $request->email)
					->update([
						'login_status' => $_SERVER['HTTP_USER_AGENT'],
						'ip_address' => $request->ip()
					]);

				$roleNames = DB::table('customers as cs')
					->where('cs.deleted_by', '=', '0')
					->where('cs.id', '=', auth($params['guard'])->id())
					->get();
				$canPosLogin = auth($params['guard'])->user()->can('pos-login');
				session()->put('roleNames', $roleNames);
			} else if ($params['guard'] != "affiliate") {
				Staff::where('email', $request->email)
					->update([
						'ip_address' => $request->ip()
					]);

				$roleNames = DB::table('tour_operator_staff_roles as tosd')
					->select('r.id', 'r.name as name')
					->join('roles as r', 'r.id', '=', 'tosd.role_id')
					->where('tosd.deleted_by', '=', '0')
					->where('tosd.tour_operator_staff_id', '=', auth($params['guard'])->id())
					->get();


				session()->put('roleNames', $roleNames);
			} else if ($params['guard'] == "affiliate") {
				// This condition is only to disable TripAdvisor login because we use TripAdvisor as an affiliate to manage rate groups differently
				$checkCustomer = DB::table('customers')
					->where('email', $request->email)
					->where('is_affiliate', 'Yes')
					->whereIn('name', ['TripAdvisor', 'Bokun'])
					->where('bokun', 1)
					->first();

				if ($checkCustomer) {
					return redirect()->back()->withErrors([
						'errors' => 'No affiliate found!!'
					]);
				}

				$customer = Customer::where('email', $request->email)
					->where('is_affiliate', 'Yes')
					->first();

				if ($customer) {
					$customer->update(['ip_address' => $request->ip()]);
					
					// Get and store location data
					IpGeolocationUtils::updateCustomerLocationFromIp($customer->id, $request->ip());
				}
			}

			session(['broadcastListeningTokenId' => $token->id]);
			if ($params['guard'] == "tour_operator_staff") {
				$canPosLogin = auth($params['guard'])->user()->can('pos-login');
				if ($canPosLogin) {
					return redirect()
						->route(routePrefix() . '.pos');
				}
			}

			if (isset($params['link_to_redirect']) && !empty($params['link_to_redirect'])) {
				return redirect($params['link_to_redirect']);
			}

			return redirect()->intended(route($params['routeUserHome']));
		}

		return back()
			->withErrors([
				'errors' => 'Incorrect email address and/or password.'
			])
			->withInput($request->except('password'));
	}

	/**
	 * Admin Forgot Password Form View
	 */
	public function adminForgotPasswordForm()
	{
		$vars = [
			'page_title' => 'Forgot Password?',
			'formSubmitRoute' => 'auth.admin.forgot-password-send-email',
			'loginRoute' => 'auth.admin.login-form',
		];

		return view('forgot-password-2', $vars);
	}

	/**
	 * Tour Operator Forgot Password Form View
	 */
	public function tourOpStaffForgotPasswordForm()
	{
		$vars = [
			'page_title' => 'Forgot Password?',
			'formSubmitRoute' => 'auth.tour-operator.forgot-password-send-email',
			'loginRoute' => 'auth.tour-operator.login-form',
		];

		return view('forgot-password-2', $vars);
	}

	/**
	 * Customer Forgot Password Form View
	 */
	public function customerForgotPasswordForm()
	{
		$vars = [
			'page_title' => 'Forgot Password?',
			'formSubmitRoute' => 'auth.customer.forgot-password-send-email',
			'loginRoute' => 'auth.customer.login-form',
		];

		$vars['tourOperators'] = DB::table('tour_operators')
			->where('status', 'Active')
			->orderBy('name')
			->get(['id', 'name']);

		return view('forgot-password', $vars);
	}

	/**
	 * Handle an incoming password reset link request.
	 *
	 * @param \Illuminate\Http\Request $request
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function adminSendResetPasswordLink(Request $request)
	{
		$request->validate(['email' => 'required|email']);

		return $this->sendResetPasswordLink($request, 'admin');
	}

	/**
	 * Handle an incoming password reset link request.
	 *
	 * @param \Illuminate\Http\Request $request
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function tourOpStaffSendResetPasswordLink(Request $request)
	{
		$request->validate(['email' => 'required|email']);

		return $this->sendResetPasswordLink($request, 'tour_operator_staff');
	}

	/**
	 * Handle an incoming password reset link request.
	 *
	 * @param \Illuminate\Http\Request $request
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function customerSendResetPasswordLink(Request $request)
	{
		$request->validate([
			'email' => 'required|email',
			'tour_operator' => 'required',
		]);

		try {
			$customer = DB::table('customers')
				->where('email', $request->email)
				->where('tour_operator_id', $request->tour_operator)
				->first(['id', 'name', 'email', 'status']);

			if ($customer) {
				if ($customer->status != 'Active')
					return sendJsonErrMsg(["The account's password can not be reset.", 'Account is not active.']);

				$key = config('app.key');

				if (Str::startsWith($key, 'base64:'))
					$key = base64_decode(substr($key, 7));

				$token = hash_hmac('sha256', Str::random(40), $key);

				$resetPwdId = DB::table('password_resets_customers')
					->insertGetId([
						'customer_id' => $customer->id,
						'token' => $token,
						'created_at' => Carbon::now(),
					]);

				if (!$resetPwdId)
					return sendJsonErrMsg(['Unable to perform the requested operation.', 'An error occurred.']);


				$arEmailData = [
					'url' => route('auth.customer.reset-password-form', ['token' => $token, 'id' => $resetPwdId])
				];
				$package = TourPackage::first();
				if ($package) {
					$arEmailData['packageFromEmail'] = $package->from_email;
					$arEmailData['packageFromName'] = $package->from_name;
				}

				sendEmail(
					'Reset account password',
					[
						'name' => $customer->name,
						'email' => $customer->email,
					],
					'link-for-resetting-password',
					$arEmailData
				);

				return response()->json([]);
			}

			return sendJsonErrMsg('Incorrect email address and/or tour operator combination.');
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to perform the requested operation. An error occurred.');
		}
	}

	/**
	 * Send Reset Password Link
	 *
	 * @param Request $request
	 * @param string $provider
	 * @return \Illuminate\Http\JsonResponse
	 */
	private function sendResetPasswordLink(Request $request, $provider)
	{
		// Set the auth provider for password resets
		Config::set('auth.defaults.passwords', $provider);

		// Validate request
		$request->validate([
			'email' => 'required|email',
			// Include tour_operator validation for customer and affiliate providers
			// 'tour_operator' => in_array($provider, ['customer', 'affiliate']) ? 'required' : 'nullable',
		]);

		try {
			// Map provider to model and reset table
			$providerConfig = [
				'admin' => [
					'model' => Admin::class,
					'reset_table' => 'password_resets_admins',
					'route' => 'auth.admin.reset-password-form',
				],
				'tour_operator_staff' => [
					'model' => Staff::class,
					'reset_table' => 'password_resets_t_o_staff',
					'route' => 'auth.tour-operator.reset-password-form',
				],
				'affiliate' => [
					'model' => Customer::class,
					'reset_table' => 'password_resets_customers',
					'route' => 'auth.customer.reset-password-form',
				]
			];

			if (!array_key_exists($provider, $providerConfig)) {
				return sendJsonErrMsg('Invalid authentication provider.');
			}

			$model = $providerConfig[$provider]['model'];
			$resetTable = $providerConfig[$provider]['reset_table'];
			$route = $providerConfig[$provider]['route'];

			// Check if user exists
			$query = $model::where('email', $request->email);
			// if (in_array($provider, ['customer', 'affiliate'])) {
			// 	$query->where('tour_operator_id', $request->tour_operator);
			// }
			$user = $query->first(['id', 'email', 'status']);

			if (!$user) {
				return sendJsonErrMsg('Incorrect email address and/or tour operator combination.');
			}

			if ($user->status != 'Active') {
				return sendJsonErrMsg(["The account's password cannot be reset.", 'Account is not active.']);
			}

			// Generate a secure token
			$key = config('app.key');
			if (Str::startsWith($key, 'base64:')) {
				$key = base64_decode(substr($key, 7));
			}
			$token = hash_hmac('sha256', Str::random(40), $key);

			// Store the token in the appropriate password reset table
			$resetData = [
				'created_at' => Carbon::now(),
				'token' => $token,
				'email' => $user->email
			];

			DB::table($resetTable)->insertGetId($resetData);

			// Prepare email data
			$routeParams = ['token' => $token, 'email' => $user->email];
			$arEmailData = [
				'url' => route($route, $routeParams)
			];

			// Optional: Add package details if applicable
			$package = TourPackage::first();
			if ($package) {
				$arEmailData['packageFromEmail'] = $package->from_email;
				$arEmailData['packageFromName'] = $package->from_name;
			}

			// Send email using custom sendEmail function
			sendEmail(
				'Reset account password',
				[
					'name' => $user?->name ?? $user?->first_name,
					'email' => $user->email,
				],
				'link-for-resetting-password',
				$arEmailData
			);

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Unable to perform the requested operation. An error occurred.');
		}
	}

	/**
	 * Admin Reset Password Form View
	 */
	public function adminResetPasswordForm()
	{
		$vars = [
			'page_title' => 'Change / Reset Password',
			'formSubmitRoute' => 'auth.admin.reset-password',
			'loginRoute' => 'auth.admin.login-form',
		];

		return view('reset-password-2', $vars);
	}

	/**
	 * Tour Operator Reset Password Form View
	 */
	public function tourOpStaffResetPasswordForm()
	{
		$vars = [
			'page_title' => 'Reset Password',
			'formSubmitRoute' => 'auth.tour-operator.reset-password',
			'loginRoute' => 'auth.tour-operator.login-form',
		];

		return view('reset-password-2', $vars);
	}

	/**
	 * Customer Reset Password Form View
	 */
	public function customerResetPasswordForm()
	{
		$vars = [
			'page_title' => 'Reset Password',
			'formSubmitRoute' => 'auth.customer.reset-password',
			'loginRoute' => 'auth.customer.login-form',
		];

		return view('reset-password', $vars);
	}

	/**
	 * Admin Reset Password Method
	 * 
	 * @param ResetPasswordRequest $request
	 */
	public function adminResetPassword(ResetPasswordRequest $request)
	{
		Config::set("auth.defaults.passwords", "admin");

		return $this->resetPassword($request, 'admin');
	}

	/**
	 * Tour Operator Reset Password Method
	 * 
	 * @param ResetPasswordRequest $request
	 */
	public function tourOpStaffResetPassword(ResetPasswordRequest $request)
	{
		Config::set("auth.defaults.passwords", "tour_operator_staff");

		return $this->resetPassword($request, 'tour_operator_staff');
	}

	/**
	 * Customer Reset Password
	 * 
	 * @param Request $request
	 */
	public function customerResetPassword(Request $request)
	{
		$request->validate([
			'token' => 'required',
			'id' => 'required|numeric',
			'password' => 'required|confirmed|min:8',
		]);

		$pwdResetRec = DB::table('password_resets_customers AS prc')
			->join('customers AS c', 'c.id', '=', 'prc.customer_id')
			->where('prc.id', $request->id)
			->first(['prc.*']);

		$deleteBeforeDT = date('Y-m-d H:i:s', time() - 3600);

		DB::table('password_resets_customers')
			->where('created_at', '<=', $deleteBeforeDT)
			->delete();

		if (!$pwdResetRec)
			return sendJsonErrMsg(['The password reset link seems invalid or expired.', 'Please re-request password reset.']);

		if ($pwdResetRec->created_at <= $deleteBeforeDT)
			return sendJsonErrMsg(['The password reset link is expired.', 'Please re-request password reset.']);

		if (!hash_equals($pwdResetRec->token, $request->token))
			return sendJsonErrMsg(['The password reset link seems invalid or expired.', 'Please re-request password reset.']);

		DB::table('customers')
			->where('id', $pwdResetRec->customer_id)
			->update(['password' => Hash::make($request->password)]);

		DB::table('password_resets_customers')
			->where('customer_id', $pwdResetRec->customer_id)
			->delete();

		return response()->json([]);
	}

	/**
	 * Reset Password Method
	 *
	 * @param Request $request
	 * @param string $provider
	 * @return \Illuminate\Http\JsonResponse
	 */
	private function resetPassword(Request $request, $provider)
	{
		// Validate request
		$request->validate([
			'token' => 'required',
			'password' => 'required|confirmed|min:8',
			'email' => 'required|email',
		]);

		try {
			// Map provider to model, reset table, and model table
			$providerConfig = [
				'admin' => [
					'model' => Admin::class,
					'reset_table' => 'password_resets_admins',
					'model_table' => 'admins',
				],
				'tour_operator_staff' => [
					'model' => Staff::class,
					'reset_table' => 'password_resets_t_o_staff',
					'model_table' => 'tour_operator_staff',
				],
				'affiliate' => [
					'model' => Customer::class,
					'reset_table' => 'password_resets_customers',
					'model_table' => 'customers',
				]
			];

			if (!array_key_exists($provider, $providerConfig)) {
				return sendJsonErrMsg('Invalid authentication provider.');
			}

			$resetTable = $providerConfig[$provider]['reset_table'];
			$modelTable = $providerConfig[$provider]['model_table'];

			// Query reset record
			$query = DB::table("$resetTable AS pr")
				->join("$modelTable AS m", function ($join) {
					$join->on('m.email', '=', 'pr.email');
				})
				->where('pr.token', $request->token ?? 0);

			$pwdResetRec = $query->first(['pr.*', 'm.id AS user_id']);

			// Clean up expired reset records (older than 1 hour)
			$deleteBeforeDT = Carbon::now()->subHour()->toDateTimeString();
			DB::table($resetTable)
				->where('created_at', '<=', $deleteBeforeDT)
				->delete();

			if (!$pwdResetRec) {
				return sendJsonErrMsg(['The password reset link seems invalid or expired.', 'Please re-request password reset.']);
			}

			if ($pwdResetRec->created_at <= $deleteBeforeDT) {
				return sendJsonErrMsg(['The password reset link is expired.', 'Please re-request password reset.']);
			}

			if (!hash_equals($pwdResetRec->token, $request->token)) {
				return sendJsonErrMsg(['The password reset link seems invalid or expired.', 'Please re-request password reset.']);
			}

			// Update password
			$userId = $pwdResetRec->user_id;
			DB::table($modelTable)
				->where('id', $userId)
				->update(['password' => Hash::make($request->password)]);

			// Delete used reset record
			DB::table($resetTable)
				->where('email', $pwdResetRec->email)
				->delete();

			return response()->json([]);
		} catch (Exception $exception) {
			Log::error("Password reset failed for provider: $provider, email: {$request->email}, id: {$request->id}", ['exception' => $exception]);
			return sendJsonErrMsg('Unable to perform the requested operation. An error occurred.');
		}
	}

	/**
	 * Logout method
	 * 
	 * @param Request $request
	 */
	public function logout(Request $request)
	{
		$guard = session('guard');

		if ($guard == 'admin') {
			Admin::where('id', Auth::id())->update(['login_status' => Null]);
		}

		if ($guard = session('guard'))
			Auth::guard($guard)->logout();
		else
			Auth::logout();

		$request->session()->invalidate();
		$request->session()->regenerateToken();
		session()->forget('roleNames');

		$currentURL = Route::currentRouteName();
		if ($currentURL == 'affiliate.logout')
			return redirect(route('auth.affiliate.login-form'));

		if ($guard == 'admin') {
			session(['link_to_redirect' => url()->previous()]);
			return redirect(route('auth.admin.login-form'));
		}

		if ($guard == 'tour_operator_staff') {
			session(['link_to_redirect' => url()->previous()]);
			return redirect(route('auth.tour-operator.login-form'));
		}

		if ($guard == 'customer')
			return redirect(route('auth.customer.login-form'));

		return redirect('/');
	}

	/**
	 * Get all of the segments for the previous uri.
	 *
	 * @return array
	 */
	public function prev_segments($uri)
	{
		$segments = explode('/', str_replace('' . url('') . '', '', $uri));

		return array_values(array_filter($segments, function ($value) {
			return $value !== '';
		}));
	}

	/**
	 * Summary of generateLoginUrl
	 * @param mixed $userId
	 * @param mixed $guard
	 * @return string
	 */
	public function generateLoginUrl($userId, $guard)
	{
		$encryptedId = Crypt::encryptString($userId);
		if (userRole() !== 'super-admin') {
			return redirect()->back()->withErrors(['message' => 'You are not allowed to login.']);
		}

		$url = URL::temporarySignedRoute('auth.magic.login', now()->addMinutes(1), ['token' => $encryptedId, 'type' => $guard]);

		return $url;
	}

	/**
	 * Summary of magicLogin
	 * @param mixed $token
	 * @param mixed $guard
	 * @return mixed|\Illuminate\Http\RedirectResponse
	 */
	public function magicLogin($token, $guard)
	{
		try {
			$userId = Crypt::decryptString($token);

			switch ($guard) {
				case "admin":
					$user = Admin::findOrFail($userId);
					$routeName = 'admin.dashboard';
					$userType = 'Admin';
					break;
				case "tour_operator_staff":
					$user = Staff::findOrFail($userId);
					$routeName = 'tour-operator.dashboard';
					$userType = 'Tour Operator';
					break;
				case "affiliate":
					$user = Customer::findOrFail($userId);
					$routeName = 'affiliate.dashboard';
					$userType = 'Affiliate';
					break;
			}

			Auth::guard($guard)->login($user);

			session([
				'guard' => $guard,
				'userType' => $userType,
			]);

			return redirect(route($routeName))->with('success', 'You are logged in!');
		} catch (Exception $e) {
			return redirect('/login')->withErrors(['message' => 'Invalid or expired token.']);
		}
	}

	/**
	 * Modify static links in email templates.
	 * 
	 * This function retrieves all email templates from both `EmailTemplate` 
	 * and `TourPackageEmailTemplate` models. It searches for occurrences of 
	 * the old URL and replaces them with the new URL in the `body` field, 
	 * then saves the updated content back to the database.
	 * 
	 * @return string Success message after updating all templates.
	 */
	public function modifyEmailStaticLinks()
	{
		// Define the old and new URLs
		$oldUrl = "https://glorydemo.agency/testmail-images/native-tour";
		$newUrl = "https://nat-email-icons.s3.us-east-1.amazonaws.com";

		// Fetch and update all email templates
		$emailTemplates = EmailTemplate::all();
		foreach ($emailTemplates as $emailTemplate) {
			// Replace old URL with new URL in the body field
			$emailTemplate->body = str_replace($oldUrl, $newUrl, $emailTemplate->body);
			// Save the updated template
			$emailTemplate->save();
		}

		// Fetch and update all tour package email templates
		$tourPackageEmailTemplates = TourPackageEmailTemplate::all();
		foreach ($tourPackageEmailTemplates as $tourPackageEmailTemplate) {
			// Replace old URL with new URL in the body field
			$tourPackageEmailTemplate->body = str_replace($oldUrl, $newUrl, $tourPackageEmailTemplate->body);
			// Save the updated template
			$tourPackageEmailTemplate->save();
		}

		// Return success message
		return "Email templates updated successfully.";
	}
}
