<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Log;
use App\Models\Admin;
use App\Repositories\Configuration\ConfigurationRepository;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
	protected $configurationRepository;

	/**
	 * Initialize the configuration repository
	 * 
	 * @param ConfigurationRepository $configurationRepository
	 */
	public function __construct(ConfigurationRepository $configurationRepository)
	{
		parent::__construct();
		$this->configurationRepository = $configurationRepository;
	}

	/**
	 * Configuration view for next year data migration
	 * 
	 * @return RedirectResponse|View
	 */
	public function configuration(): RedirectResponse|View
	{
		try {
			$affiliates = Customer::isAffiliate()->get();
			$packages = TourPackage::active()->get();
			$admins = Admin::all();

			$vars = [
				'page_title' => 'Configuration View',
				'initializeConfigurationRoute' => route(name: 'admin.initialize-configuration'),
				'affiliates' => $affiliates,
				'packages' => $packages,
				'admins' => $admins
			];

			return view(view: 'configuration.index', data: $vars);
		} catch (Exception $exception) {
			Log::error(message: $exception);
			return back()
				->withErrors(provider: [
					'errors' => $exception->getMessage()
				]);
		}
	}

	/**
	 * Initialize Configuration for upcoming year for all tenants
	 * 
	 * @param Request $request
	 * @return RedirectResponse
	 */
	public function initializeConfiguration(Request $request): RedirectResponse
	{
		try {
			$data = $request->all();

			// Execute if migrate data for packages
			if ($request->package_id) {
				$this->configurationRepository->migratePackages(data: $data);
			}

			// Execute if migrate data for affiliates
			if ($request->affiliate_id) {
				$this->configurationRepository->migrateAffiliates(data: $data);
			}

			return back()
				->with(key: 'message', value: 'Data migration for upcoming year will be formed in the background shortly; please check back in 5 minutes.');
		} catch (Exception $exception) {
			Log::error(message: $exception);
			return back()
				->withErrors(provider: [
					'errors' => $exception->getMessage()
				]);
		}
	}
}
