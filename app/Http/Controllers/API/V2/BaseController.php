<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Send a success response.
     */
    public function sendResponse(mixed $result, string $message, bool $status = true, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => $message,
            'status'  => $status,
        ], $code);
    }

    /**
     * Send an error response.
     */
    public function sendError(string $error, array $errorMessages = [], int $code = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $error,
            'data'    => $errorMessages ?: null, // Avoid adding an empty array
        ], $code);
    }
}
