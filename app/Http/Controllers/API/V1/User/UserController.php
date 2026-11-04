<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\API\V1\ApiController;
use App\Repositories\User\UserRepository;
use App\Transformers\ProfileResource;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends ApiController
{
    protected $userRepository;

    /**
     * @param  UserRepository  $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return $this
                ->setMessage(__('user.api_response.logout_success'))
                ->respond(null);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     *
     * @throws GeneralCoreException
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            return $this
                ->setMessage(__('user.api_response.profile_success'))
                ->respond(new ProfileResource($this->userRepository->getProfile()));
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
