<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use App\Traits\ActionButtons;

class Controller extends BaseController
{
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ActionButtons;

	public function __construct()
	{
		if (session('userType') == 'Admin' || session('userType') == 'Tour Operator') {
			DB::table('tokens')
				->where('id', session('broadcastListeningTokenId'))
				->update(['updated_at' => mysqlDT()]);
		}
	}
}
