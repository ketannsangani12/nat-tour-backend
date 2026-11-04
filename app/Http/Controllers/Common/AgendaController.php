<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Repositories\Agenda\AgendaRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Summary of AgendaController
 */
class AgendaController extends Controller
{
	protected $agendaRepository;

	/**
	 * @param  AgendaRepository  $agendaRepository
	 */
	public function __construct(
		AgendaRepository $agendaRepository
	) {
		parent::__construct();
		$this->agendaRepository = $agendaRepository;
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function agenda(Request $request, $from, $to, $packageId = 0, $customerId = 0)
	{
		try {
			$vars = $this->agendaRepository->initAgenda($request, $from, $to, $packageId, $customerId);

			$vars['request'] = $request;
			$vars['from'] = $from;
			$vars['to'] = $to;
			$vars['packageId'] = $packageId;
			$vars['customerId'] = $customerId;

			return view('users.common.agenda.index', $vars);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return mixed
	 */
	public function downloadAffiliateAgenda(Request $request, $from, $to, $packageId = 0, $customerId = 0)
	{
		try {
			return $this->agendaRepository->downloadAffiliateAgenda($request, $from, $to, $packageId, $customerId);
		} catch (Exception $exception) {
			Log::error($exception);
			return back()
				->withErrors(['errors' => $exception->getMessage()]);
		}
	}
}
