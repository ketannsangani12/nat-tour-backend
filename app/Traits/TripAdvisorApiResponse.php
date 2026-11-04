<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait TripAdvisorApiResponse
{
    public $key, $value;

    public static function response($key = null,$data): JsonResponse
    {
        if($key){
            return response()
                ->json(
                    [
                        $key => $data,
                    ]
                );
        }else{
           return response()
            ->json($data);
        }
    }
}
