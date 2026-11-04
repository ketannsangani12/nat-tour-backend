<?php

namespace App\Repositories\TourPackage;

use App\Models\TourPackage;
use App\Exceptions\Core\GeneralCoreException;
use Exception;
use App\Repositories\Repository;
use Illuminate\Support\Facades\Log;
use App\Models\TourPackageForm;
use App\Models\TourBookingForms;
use App\Repositories\TourBooking\TourBookingAddonRepository;

class TourPackageRepository extends Repository
{
    /**
     * @var string
     */
    public $tourPackage = TourPackage::class;

    /**
     * @var TourBookingAddonRepository
     */
    protected $tourBookingAddonRepository;

    public function __construct(TourBookingAddonRepository $tourBookingAddonRepository)
    {
        $this->tourBookingAddonRepository = $tourBookingAddonRepository;
    }

    /**
     * Get the tour operator packages.
     *
     * @param int $id
     * @return mixed
     */
    public function getPackages(int $id)
    {
        try {
            return $this->tourPackage::active()
                ->departmentPackage()
                ->where('tour_operator_id', $id)
                ->orderBy('name')
                ->get(['id', 'name']);
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
    /**
     * Get custom form for tour package checkout options
     * @param int|string $id
     * @return mixed
     */
    public function getCustomForm($id)
    {
        try {
            $fields = ["id", "form_name", "description", "status", "form_package_id", "form_fields"];
            return TourPackageForm::where('form_package_id', intval($id))
                ->where("status", "=", "active")
                ->select($fields)
                ->limit(1)
                ->get()
                ->first();
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Get submitted custom form
     * @param int|string $id
     * @return mixed
     */
    public function getSubmittedCustomForm($bookingId)
    {
        // Old fields (form_id/booking_id/fields/description) preserve their exact
        // existing shape and null-semantics for backward compatibility with current
        // API consumers. has_old_addons/has_new_addons/new_addons are additive —
        // callers ignoring them see identical behavior to before this change.
        $response = [
            'form_id' => null,
            'booking_id' => null,
            'fields' => null,
            'description' => null,
            'has_old_addons' => false,
            'has_new_addons' => false,
            'new_addons' => null,
        ];
        try {
            $form = TourBookingForms::where('tour_booking_id', intval($bookingId))
                ->limit(1)
                ->select(["tour_package_form_id", "tour_booking_id", "form_submission"])
                ->get()
                ->first();

            if ($form) {

                $formData = TourPackageForm::where('id', $form->tour_package_form_id)
                    ->where("status", "=", "active")
                    ->select('description')
                    ->limit(1)
                    ->get()
                    ->first();

                $response['form_id'] =  $form->tour_package_form_id;
                $response['booking_id'] = $form->tour_booking_id;
                $response['description'] = $formData['description'] ?? "";

                $fields = json_decode($form->form_submission);
                foreach ($fields as $field) {
                    $field->booking_addon_id = 0;
                    $field->priceInfo->price = (string)$field->priceInfo->price;
                    $field->priceInfo->fee = isset($field->priceInfo->fee) ? (float)$field->priceInfo->fee : 0.00;
                }
                $response['fields'] = $fields;
                $response['has_old_addons'] = true;
            }

            $newAddons = $this->tourBookingAddonRepository->buildAddonsSubmission(intval($bookingId));
            if ($newAddons !== null) {
                $response['has_new_addons'] = true;
                $response['new_addons'] = $newAddons;
            }

            return $response;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }


    /**
     * Update submitted custom form
     * @param int|string $id
     * @return mixed
     */
    public function updateSubmittedCustomForm($request)
    {
        $response =  ['update' => null, 'form_id' => null, 'booking_id' => null, 'fields' => null];
        try {
            $bookingFormAddons = TourBookingForms::firstOrCreate(['tour_booking_id' => $request->tour_booking_id]);
            $bookingFormAddons->tour_package_form_id = intval($request->tour_package_form_id);
            $bookingFormAddons->tour_booking_id = intval($request->tour_booking_id);
            $bookingFormAddons->form_submission = $request->fields;

            $response["update"] = $bookingFormAddons->save();
            $response['form_id'] =  $request->tour_package_form_id;
            $response['booking_id'] = $request->tour_booking_id;
            $response['fields'] = $request->fields;

            return $response;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }
}
