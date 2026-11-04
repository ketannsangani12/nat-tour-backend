<?php

namespace App\Http\Controllers\API\V1\User;

use App\Constants\Constants;
use App\Http\Controllers\API\V1\ApiController;
use App\Http\Requests\API\User\RegisterRequest;
use App\Http\Requests\API\User\AuthRequest;
use App\Http\Requests\API\User\ForgotPasswordRequest;
use App\Http\Requests\API\User\ResetPasswordRequest;
use App\Repositories\User\AuthRepository;
use App\Exceptions\Core\GeneralCoreException;
use App\Models\Tenant;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use App\Utils\AuthUtils;
use App\Models\TourOperator;
class AuthController extends ApiController
{
    protected $authRepository;

    /**
     * @param  AuthRepository  $authRepository
     */
    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    /**
     * @param  RegisterRequest  $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('auth.api_response.register_success'))
                ->respond($this->authRepository->register($request->validated()));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  AuthRequest  $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function login(AuthRequest $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('auth.api_response.login_success'))
                ->respond($this->authRepository->login($request->validated()));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  ForgotPasswordRequest  $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authRepository->forgot($request);

            if ($status == Password::RESET_LINK_SENT) {
                return $this
                    ->setMessage(__('auth.api_response.password_reset_link_sent'))
                    ->respond(null);
            }

            return $this
                ->setCode(Constants::HTTP_FORBIDDEN)
                ->setMessage(__('auth.api_response.max_password_reset_attempts'))
                ->respond(null);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  ResetPasswordRequest  $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authRepository->reset($request);

            if ($status == Password::PASSWORD_RESET) {
                return $this
                    ->setMessage(__('auth.api_response.password_reset_success'))
                    ->respond(null);
            }

            return $this
                ->setCode(Constants::HTTP_FORBIDDEN)
                ->setMessage(__('auth.api_response.invalid_password_reset_token'))
                ->respond(null);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Check if tenant is exist or not.
     * 
     * @param string $tenantId
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function checkTenantExist($tenantId, $tenantInfo = "N")
    {
        try {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $response = ['id' => $tenant->id, 'name' => $tenant->name];
                if ($tenantInfo != "N") {
                    $response['info'] = AuthUtils::tenantPublicInfo($tenant);
                }
                return $this
                    ->setMessage(__('Workspace found.'))
                    ->respond($response);
            } else {
                throw new Exception("No workspace found.");
            }
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
