<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use App\Models\BookingTransact;
use App\Models\TourBookingContact;
use Carbon\Carbon;
use Exception;

class PackageHasContactsService
{
    /**
	 * Store contacts/people in tour_booking_peoples table
	 *
	 * @param integer|string $tourBookingId
     * @param mixed $contactData
	 *
	 * @return void
	 */
    public static function storeTourBookingPeople($tourBookingId, $contactData): void
	{
        $contactDetails = json_decode($contactData, true);

		foreach ($contactDetails as $key => $contact) {
            if(count($contact['data']) > 0) {
                for ($i = 0; $i < count($contact['data']); $i ++) {
					$tourBookingTransact = BookingTransact::where('tour_booking_id', $tourBookingId)->where('tour_package_rate_id', $contact['rate'])->first();
					
					$contactInfo[] = [
						'tour_booking_id' => $tourBookingId,
						'tour_booking_transact_id' => $tourBookingTransact->id,
						'name' => $contact['data'][$i]['name'],
						'age' => $contact['data'][$i]['age'],
						'weight' => $contact['data'][$i]['weight'],
						'note' => ($contact['data'][$i]['note'] != "") ? $contact['data'][$i]['note'] : null,
						'created_at' => Carbon::now(),
						'updated_at' => Carbon::now()
					];
				}
            }
        }
		
        DB::table('tour_booking_contacts')->insert($contactInfo);
        DB::commit();
	}

	/**
	 * Execute the contactsForBooking service to get the contacts of the booking.
	 *
	 * @param integer|string $tourBookingId
	 *
	 * @return object
	 */
	public static function contactsForBooking($tourBookingId): object
	{
		$contacts = DB::table('tour_booking_contacts as tbc')
			->join('tour_bookings_transact as tbt', 'tbt.id', '=', 'tbc.tour_booking_transact_id')
			->join('tour_package_rates as tpr', 'tpr.id', '=', 'tbt.tour_package_rate_id')
			->where('tbc.tour_booking_id', $tourBookingId)
			->get([
				'tbc.id as contact_id',
				'tbc.tour_booking_transact_id',
				'tbc.name',
				'tbc.age',
				'tbc.weight',
				'tbc.note',
				'tpr.id as rate_id',
				'tpr.rate_for'
			]);

		if (!$contacts) {
			throw new Exception("Contact details not found.");
		}

		return $contacts;
	}

	/**
	 * Execute the contacts service to get the contact details of the booking.
	 *
	 * @param integer|string $tourBookingId
	 *
	 * @return array
	 */
	public static function contactList($tourBookingId): array
	{
		$contactsInfo = [];
		$contacts = static::contactsForBooking($tourBookingId);
		
		foreach ($contacts as $key => $contact) {
			if(!isset($contactsInfo[$contact->tour_booking_transact_id])) {
				$contactsInfo[$contact->tour_booking_transact_id] = array();
			}

			$contactsInfo[$contact->tour_booking_transact_id]['transact_id'] = $contact->tour_booking_transact_id;
			$contactsInfo[$contact->tour_booking_transact_id]['rate_for'] = $contact->rate_for;
			$contactsInfo[$contact->tour_booking_transact_id]['rate_id'] = $contact->rate_id;
			$contactsInfo[$contact->tour_booking_transact_id]['contact_data'][] = $contact;
		}

		return $contactsInfo;
	}

	/**
	 * Execute to get contact details for given contact ID
	 *
	 * @param integer $contactId
	 *
	 * @return object
	 */
	public static function getContact($contactId): object
	{
		$contact = DB::table('tour_booking_contacts')->where('id', $contactId)->first();

		if(!$contact) {
			throw new Exception("Contacts details not found.");
		}

		return $contact;
	}

	/**
	 * Execute to delete contacts for selected contact ids
	 *
	 * @param integer $contactIds
	 *
	 * @return void
	 */
	public static function deleteContacts($contactIds): void
	{
		$ids = json_decode($contactIds, TRUE);
								
		if(count($ids) > 0) {
			$contacts = TourBookingContact::whereIn('id', $ids);
			$haveContacts = $contacts->get();

			if($haveContacts) {
				$contacts->delete();
			}
		}

		DB::commit();
	}
}