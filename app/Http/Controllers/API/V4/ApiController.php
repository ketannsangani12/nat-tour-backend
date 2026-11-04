<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function sendSuccess($data, $message = 'Success', $code = Response::HTTP_OK)
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ], $code);
    }

    public function sendError($message = 'Error', $code = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $code);
    }
}