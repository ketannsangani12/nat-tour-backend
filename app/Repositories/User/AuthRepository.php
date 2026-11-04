<?php

namespace App\Repositories\User;

use App\Constants\Constants;
use App\Models\Staff;
use App\Repositories\Repository;
use App\Transformers\ProfileResource;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\TourOperator;
use Exception;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthRepository extends Repository
{
    /**
     * @var string
     */
    public $model = Staff::class;

    /**
     * @var string
     */
    public $tourOperator = TourOperator::class;

    /**
     * @param  array  $data
     * @return array
     *
     * @throws Exception
     */
    public function register(array $data): array
    {
        DB::beginTransaction();
        try {
            $user = $this->model::where('email', $data['email'])
                ->whereNull('email_verified_at')
                ->first();

            if (!$user) {
                $user = $this->model::create([
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'created_from' => Constants::STATUS_ONE,
                ]);
            } else {
                $user->password = Hash::make($data['password']);
                $user->save();
            }
            DB::commit();

            return [
                'user' => $user,
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  array  $data
     * @return array
     *
     * @throws Exception
     */
    public function login(array $data): array
    {
        DB::beginTransaction();
        try {
            $user = $this->model::select('id','tour_operator_id','first_name','last_name','email','password','phone_number','status','profile_photo_path as profile_photo_url')->where('email', $data['email'])->where('status', '=','Active')->first();

            if (!$user) {
                throw new Exception("User not found.");
            }

            if (!Hash::check($data['password'], $user->password)) {
                throw new Exception("Email or password not correct.");
            }

            $user->tokens()->where('created_at', '<', now()->subMinutes(config('sanctum.expiration')))->delete();
            $token = $user->createToken(config('sanctum.sanctum_token_text'))->plainTextToken;

            session([
                'guard' => 'tour_operator_staff',
                'userType' => 'Tour Operator'
            ]);

            DB::commit();

            $tourOperator = $this->tourOperator::find($user->tour_operator_id);

            $isCalendar = false;
            $isNewBooking = false;
            $isAgenda = false;
            $isReport = false;
            $isQr = false;
            $isProfile = false;

            if ($user->can('calendar')) {
                $isCalendar = true;
            }
            if ($user->can('new-booking')) {
                $isNewBooking = true;
            }
            if ($user->can('agenda')) {
                $isAgenda = true;
            }
            if ($user->can('reports')) {
                $isReport = true;
            }
            if ($user->can('qr')) {
                $isQr = true;
            }
            if ($user->can('profile')) {
                $isProfile = true;
            }
 

            $oldBookingId = oldBookingId();
			$setting = DB::table('settings')->where('key', 'BOOKING_ID_PREFIX')->first();
			$bookingIdPrefix = ($setting && $setting->value != '') ? $setting->value : '';
            $staffRole = $user->roles->first()->name ?? 'staff';
            if (count($user->roles) == 2) {
                $staffRole = "manager";
            } 

            return [
                'user_details' => new ProfileResource($user),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('sanctum.expiration'),
				'card_reader_prefix' => $tourOperator->card_reader_prefix ?? 'Other',
                'statement_descriptor' => $tourOperator->statement_descriptor ?: substr(strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $tourOperator->name)), 0, 22),
                'service_commission_percentage' => $tourOperator->service_commission_percentage ?? 0,
				'timezone' => $tourOperator->timezone,
				'isCalendar'=> $isCalendar,
				'isAgenda' => $isAgenda,
				'isNewBooking' => $isNewBooking,
				'isReport' => $isReport,
                'isQr' => $isQr,
                'isProfile' => $isProfile,
				'sections' => config('constants.payment_sections'),
				'oldBookingId'=> $oldBookingId,
                'staff_role' => $staffRole,
				'bookingIdPrefix' => $bookingIdPrefix,
				'isCartView' => cartView(),
                'stripe_connected_account_id' => $tourOperator->stripe_destination_id,
                'stripe_terminal_location_id' => $tourOperator->stripe_terminal_location_id
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  object  $request
     * @return string
     */
    public function forgot(object $request): string
    {
        DB::beginTransaction();
        try {
            $user = $this->model::where('email', $request->email)->first();

            if (!$user) {
                throw new Exception("User not found.");
            }


            Config::set('auth.defaults.passwords', 'tour_operator_staff');

            // We will send the password reset link to this user. Once we have attempted
            // to send the link, we will examine the response then see the message we
            // need to show to the user. Finally, we'll send out a proper response.
            $status = Password::sendResetLink($request->only('email'));
            DB::commit();

            return $status;
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  object  $request
     * @return mixed
     */
    public function reset(object $request)
    {
        DB::beginTransaction();
        try {
            $user = $this->model::where('email', $request->email)->first();

            if (!$user) {
                throw new Exception("User not found.");
            }

            $token = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$token) {
                throw new Exception("Password already reset.");
            }

            // Here we will attempt to reset the user's password. If it is successful we
            // will update the password on an actual user model and persist it to the
            // database. Otherwise we will parse the error and return the response.
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user) use ($request) {
                    $user->forceFill([
                        'password' => Hash::make($request->password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );
            DB::commit();

            return $status;
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
            throw new GeneralCoreException($exception);
        }
    }
}
