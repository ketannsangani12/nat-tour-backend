<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Repositories\Addon\AddonFormRepository;
use App\Http\Requests\Common\UpsertAddonFormRequest;
use App\Http\Requests\Common\StoreAddonCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AddonFormController extends Controller
{
    protected $addonFormRepository;

    /**
	 * @param  AddonFormRepository $addonFormRepository
	 */
	public function __construct(AddonFormRepository $addonFormRepository) {
		parent::__construct();

		$this->addonFormRepository = $addonFormRepository;
	}

    /**
	 * Get the package addon form from the db by tour package id
	 * @method get
	 * @param $tourPackageId
	 * @param $builder
	 */
	public function getPackageAddonForm($tourPackageId, $builder = 0)
	{
		try {
			return $this->addonFormRepository->getPackageAddonForm($tourPackageId, $builder);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

    /**
	 * Save (create/update) entire addon form with N-level fields.
	 *
	 * @param \App\Http\Requests\Common\UpsertAddonFormRequest $r
	 * @return mixed
	 */
	public function saveAddonForm(UpsertAddonFormRequest $request)
	{
		try {
			return $this->addonFormRepository->saveAddonForm($request);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

    /**
	 * Soft Delete The Addon Form
	 * Has no cascade effect
	 * @param $formId primary row id
	 * @param $tourPackageId package id
	 */
	public function deleteAddonForm($formId)
	{
		try {
			return $this->addonFormRepository->deleteAddonForm($formId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * List active addon categories, for the builder's Category dropdown.
	 */
	public function getAddonCategories()
	{
		try {
			return $this->addonFormRepository->getAddonCategories();
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * Find-or-create an addon category by name.
	 */
	public function saveAddonCategory(StoreAddonCategoryRequest $request)
	{
		try {
			return $this->addonFormRepository->saveAddonCategory($request->input('name'));
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}

	/**
	 * List packages eligible to be linked as an add-on, for the builder's
	 * Package dropdown (excludes the package currently being edited).
	 * @param $tourPackageId
	 */
	public function getAddonPackages($tourPackageId = null)
	{
		try {
			return $this->addonFormRepository->getAddonPackages($tourPackageId);
		} catch (Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg($exception->getMessage());
		}
	}
}