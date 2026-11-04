<?php

namespace App\Repositories\TourBooking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\Repository;
use App\Models\TourBookingForms;
use Exception;

class TourBookingFormsRepository extends Repository
{
    /**
     * Get custom booking option form by id or booking id
     * @param string|int|null $id the primary key of the row
     * @param string|int|null $bookingId get the form data by booking id
     */
    public function getBookingForm($id = null, $bookingId = null)
    {
        $where = ($id) ? "id" : "tour_booking_id";
        $rowId = ($id) ? $id : $bookingId;
        return TourBookingForms::where($where, $rowId)->first();
    }

    /**
     * capture custom input form the user
     * Note this model is designed to extract
     * the form fields update them and then save then again
     * insert / update custom fields values
     * @param array|null $item
     * @param int|string $bookingId
     * @param int|null $updateId if this is an existing from update the form
     * @return array<string|array>
     */
    public function upsertBookingForm($item, $bookingId, $updateId = null): array
    {
        // return meta data insert or updated was preformed
        $action = null;
        $form = null;
        $error = null;
        $addons = null;

        // if no item we can not process a form
        if (isset($item["custom_fields"]) && count($item["custom_fields"])) {
            try {
                $form = $item["custom_fields"];
                // if we have fields to save
                $resp = $this->saveFormData($form, $form[0]["form_id"], $bookingId, $updateId);
                $action = $resp["action"];
                $addons = $resp["addons"];
                // if form fields
            } catch (Exception $e) {
                DB::rollBack();
                Log::error($e);
                $error = $e->getMessage();
            } //try catch

        } //if form set

        return ["booking_id" => $bookingId,  "action" => $action, "form" => $form, "addons" => $addons, "error" => $error];
    }

    public function saveFormData($form, $formId, $bookingId, $updateId = null)
    {
        $action = "none";
        $addons = null;

        DB::beginTransaction();
        // if id is set update the form data
        if ($updateId) {
            $action = "update";
            $data = ['tour_package_form_id' => intval($formId), 'tour_booking_id' => intval($bookingId), 'form_submission' => json_encode($form)];
            TourBookingForms::where('tour_booking_id', $updateId)->update($data);
        } else {
            $action = "insert";
            $tourBookingForm = new TourBookingForms;
            $tourBookingForm->tour_package_form_id = intval($formId);
            $tourBookingForm->tour_booking_id = intval($bookingId);
            $tourBookingForm->form_submission = json_encode($form);
            $tourBookingForm->save();
        }
        DB::commit();

        return [ "action"=>$action, "addons" => $addons];
    }

    public function insertBookingFields($item, $bookingId): array
    {
        // return meta data insert or updated was preformed
        $action = "insert";
        $form = null;
        $error = null;
        $addons = null;

        // if no item we can not process a form
        if (isset($item) && $item["fields"]) {
            try {
                $formId = $item["form_id"];
                $form = $item["fields"];
                // if we have fields to save
                if (count($form) >  0) {
                   $resp = $this->saveFormData($form, $formId, $bookingId);
                   $addons = $resp["addons"];
                } // if form fields
            } catch (Exception $e) {
                DB::rollBack();
                Log::error($e);
                $error = $e->getMessage();
            } //try catch
        } //if form set

        return ["booking_id" => $bookingId,  "action" => $action, "form" => $form, "addons" => $addons, "error" => $error];
    }

    /**
     * provide a method to the save setter of upsert booking form
     */
    public function saveBookingForm($fields, $bookingId, $updateId= null)
    {
        return $this->upsertBookingForm($fields, $bookingId, $updateId);
    }

    public function insertBookingForm($form, $bookingId)
    {
        return $this->insertBookingFields($form, $bookingId);
    }
}
