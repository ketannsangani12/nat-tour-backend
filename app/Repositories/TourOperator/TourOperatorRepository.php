<?php

namespace App\Repositories\TourOperator;

use App\Models\TourOperator;
use App\Models\Staff;
use App\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use App\Exceptions\Core\GeneralCoreException;
use Exception;

class TourOperatorRepository extends Repository
{
    /**
     * @var string
     */
    public $tourOperator = TourOperator::class;

    public $staff = Staff::class;

    /**
     * Check tour operator is affiliate or not.
     *
     * @param int $id
     * @return object|null
     */
    public function checkIsAffiliate(int $id)
    {
        return $this->tourOperator::isAffiliate()->find($id) ?? [];
    }

    /**
     * Get the list of staff for specific tour operator
     *
     * @param object $request
     * @return mixed
     */
    public function bookedbyInfo(object $request)
    {
        try {
            $staffUsers = DB::table('tour_operator_staff')
                ->where('tour_operator_id', $request->tour_operator_id)
                ->where('status', 'Active')
                ->where('deleted_by', 0)
                ->get(['id', 'first_name', 'last_name']);
            return $staffUsers;
        } catch (Exception $exception) {
            Log::error($exception);
            throw new GeneralCoreException($exception);
        }
    }

    /**
     * Update the staff profile
     *
     * @param object $request
     * @param int $id
     * @return array
     */
    public function updateStaffProfile(object $request, int $id)
    {
        DB::beginTransaction();
        try {
            if (session('userType') == 'Admin') {
                $values = $request->except(['status', 'profile_picture']);
            } else {
                $values = $request->except('profile_picture');
            }

            if (empty($values['password'])) {
                unset($values['password']);
            }

            if ($request->hasFile('profile_picture')) {
                $tourOperator = $this->tourOperator::query()->first();
                $photo        = $request->file('profile_picture');
                $fileNmExt    = fileNameExt($photo->getClientOriginalName());
                $ext          = strtolower($fileNmExt->ext);

                if (\in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif'], true)) {
                    $cleanName   = str_replace(' ', '_', $fileNmExt->name);
                    $fileName    = "{$cleanName}_" . time() . ".{$ext}";
                    $s3Path      = "images/tour-operator/" . $tourOperator->tenant_id . "/users/" . $fileName;
                    $imageStream = Image::make($photo)->stream();
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
                    $s3 = Storage::disk('s3');
                    $s3->put($s3Path, $imageStream->__toString());
                    $values['profile_photo_path'] = $s3->url($s3Path);
                }
            }

            $this->staff::findOrFail($id)->fill($values)->saveOrFail();
            DB::commit();

            $staffData = $this->staff::findOrFail($id);

            return [
                'staffData' => $staffData
            ];
        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollback();
            throw new GeneralCoreException($exception);
        }
    }

	/**
	 *  Update build number details
	 *
	 * @param object $request

	 * @return array
	 */
	public function saveBuildNumber(object $request)
	{
		DB::beginTransaction();
		try {
			$values = $request->all();
            $id = auth()->id();
			$values = $this->staff::findOrFail($id)
				->fill($values)
				->saveOrFail();
			DB::commit();

			$staffData = $this->staff::findOrFail($id);

			return [
				'staffData' => $staffData
			];
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();
			throw new GeneralCoreException($exception);
		}
	}

	/**
	 *  Get the stripe keys for the app
	 *
	 * @param object $request

	 * @return array
	 */
	public function getStripeKeys()
	{
		DB::beginTransaction();
		try {
			return [
				'publicKey' => getStripePublicKey(),
                'secretKey' => getStripeSecretKey()
			];
		} catch (Exception $exception) {
			Log::error($exception);
			DB::rollback();
			throw new GeneralCoreException($exception);
		}
	}

    /**
     * Enable or Disable stripe invoiceing for a touroperator.
     * @param int $id tour operator id
     * @param int $state 0 or 1 representing true or false. 
     * @return array 
     */
    public function setExternalInvoiceStatus(int $id, int $state){

        $error = null;
        $sucess = true;

        DB::beginTransaction();
		try {
			$tourOperator = $this->tourOperator::findOrFail($id);
            $tourOperator->stripe_invoice_enabled = $state;
            $tourOperator->save();
			DB::commit();
		} catch (Exception $err) {
			Log::error($err);
			DB::rollback();
			$sucess = false;
            $error = $err;
		}

        return [ 'id'=>$id, 'state'=>$state, 'success'=>$sucess, 'error' => $error ];
    }
}
