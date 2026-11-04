<?php

namespace App\Http\Controllers\API\V3;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use App\Traits\TripAdvisorApiResponse;

class ApiController extends Controller
{
    use ApiResponses, TripAdvisorApiResponse;
}
