<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Events\BookingNew;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BookingSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		if(rand(1, 5) == 5) {
			$customer = DB::table('customers')
				->orderBy(DB::raw('RAND()'))
				->where('status', 'Active')
				->where('deleted_by', 0)
				->first([ 'id', 'name' ]);
			
			if(!$customer)
				$customer = $this->addCustomer();
		} else
			$customer = $this->addCustomer();
		
		$tourSlotTime = DB::table('tour_slot_times AS tst')
			->join('tour_slot_dates AS tsd', 'tsd.id', '=', 'tst.tour_slot_id')
			->join('tour_packages AS tp', 'tp.id', '=', 'tsd.tour_package_id')
			->join('tour_operators AS t_o', 't_o.id', '=', 'tp.tour_operator_id')
			->join('cities as ct', 'ct.id', '=', 't_o.city_id')
			->join('states as s', 's.id', '=', 'ct.state_id')
			->leftJoin('taxes as t', 't.state_id', '=', 's.id')
			->where([
				'tst.deleted_by' => 0,
				'tsd.status' => 'Active',
				'tsd.deleted_by' => 0,
				'tp.status' => 'Active',
				'tp.deleted_by' => 0,
				't_o.status' => 'Active',
				't_o.deleted_by' => 0,
			])
			->whereRaw('tsd.date BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND DATE_ADD(NOW(), INTERVAL 1 DAY)')
			->orderBy(DB::raw('RAND()'))
			->first([
				'tst.id', 'tst.time_from', 'tst.time_to', 'tsd.date', 'tsd.tour_package_id',
				'tp.tour_operator_id', 'tp.name AS pkg_name',
				's.permit_fee', 't.value AS state_tax_value', 't.value_type AS state_tax_value_type'
			]);
		
		$tpr = DB::table('tour_package_rates')
			->where('tour_package_id', $tourSlotTime->tour_package_id)
			->where('status', 'Active')
			->where('deleted_by', 0)
			->orderBy(DB::raw('RAND()'))
			->get([ 'id', 'rate_for', 'rate', 'additional_tax_percentage', 'processing_charge', 'additional_charge','permit_fee'])
			->toArray();
		
		$seats = [];
		$touristGroups = [];
		$c1 = rand(1, 4);
		$c2 = count($tpr);
		$grandTotalAmt = 0;
		
		for($i = 0; $i < $c1 && $i < $c2; $i++) {
			$tourists = rand(1, 5);

			$recordTotalAmt = round($tpr[$i]->rate +
				$tpr[$i]->processing_charge +
				$tpr[$i]->additional_charge +
				$tpr[$i]->permit_fee, 2);
			
			$stateTax = $tourSlotTime->state_tax_value_type == 'Percent' ?
				round($recordTotalAmt * ($tourSlotTime->state_tax_value / 100), 2) :
				$tourSlotTime->state_tax_value;
			
			$recordTotalAmt += round($recordTotalAmt * ($tpr[$i]->additional_tax_percentage / 100), 2);
			$recordTotalAmt += $stateTax;
			$recordTotalAmt *= $tourists;
			$grandTotalAmt += $recordTotalAmt;
			$touristGroups[] = $tpr[$i]->rate_for[0] . ':' . $tourists;

			$seats[] = [
				'tour_package_rate_id' => $tpr[$i]->id,
				'tourists' => $tourists,
				'processing_charge' => $tpr[$i]->processing_charge,
				'additional_tax_percentage' => $tpr[$i]->additional_tax_percentage,
				'permit_fee' => $tpr[$i]->permit_fee,
				'additional_charge' => $tpr[$i]->additional_charge,
				'state_tax' => (double) $stateTax,
				'rate' => $tpr[$i]->rate,
				'total' => $recordTotalAmt,
				'created_by' => 2,
				'created_by_user_type' => 'Admin',
			];
		}

		DB::beginTransaction();

		try {
			if(rand(1, 3) == 3) { // apply promo in 1 of 3 chances
				$promo = DB::table('tour_promotions')
					->where('tour_operator_id', $tourSlotTime->tour_operator_id)
					->whereRaw('DATE(NOW()) BETWEEN start_date AND end_date')
					->where('status', 'Active')
					->where('deleted_by', 0)
					->orderBy(DB::raw('RAND()'))
					->first([ 'id', 'discount_value', 'discount_value_type' ]);

				if($promo) {
					if($promo->discount_value_type == 'Percent')
						$discountAmt = $grandTotalAmt * ($promo->discount_value / 100);
					else
						$discountAmt = $promo->discount_value;
					
					$grandTotalAmt -= $discountAmt;
					$tourPromotionId = $promo->id;
				} else
					$tourPromotionId = $discountAmt = 0;
			} else
				$tourPromotionId = $discountAmt = 0;
			
			if(rand(1, 5) == 5)
				$status = 'Cancelled';
			elseif(mysqlDT() < $tourSlotTime->date . ' ' . $tourSlotTime->time_to)
				$status = 'Completed';
			else
				$status = 'Booked';
			
			$tourBooking = \App\Models\TourBooking::create([
				'tour_package_id' => $tourSlotTime->tour_package_id,
				'tour_slot_id' => $tourSlotTime->id,
				'tour_promotion_id' => $tourPromotionId,
				'customer_id' => $customer->id,
				'comments' => '',
				'mask_required' => rand(0, 1) ? 'Yes' : 'No',
				'discount' => $discountAmt,
				'total' => $grandTotalAmt,
				'status' => $status,
				'created_by' => 2,
				'created_by_user_type' => 'Admin',
				'updated_at' => '0000-00-00 00:00:00',
			]);

			for($i = 0; $i < count($seats); $i++)
				$seats[$i]['tour_booking_id'] = $tourBooking->id;
			
			DB::table('tour_bookings_transact')
				->insert($seats);

			$paidAmt = 0;

			if($payPart = rand(0, 7)) {
				$paidAmt = $grandTotalAmt / $payPart;

				DB::table('transactions')
					->insert([
						'tour_booking_id' => $tourBooking->id,
						'amount' => $paidAmt,
						'transaction_number' => Str::random(10),
						'via' => '',
						'response_init' => '',
						'response_end' => '',
						'response_end_datetime' => '0000-00-00 00:00:00',
						'status' => 'Completed',
						'created_by' => 2,
						'created_by_user_type' => 'Admin',
					]);
			}

			DB::commit();

			echo "{$tourSlotTime->date} {$tourSlotTime->time_to} #{$tourBooking->id} | {$tourSlotTime->pkg_name} #Op: {$tourSlotTime->tour_operator_id}\n";

			$booking = [
				'id' => $tourBooking->id,
				'cust_name' => $customer->name,
				'pkg_name' => $tourSlotTime->pkg_name,
				'paid' => $paidAmt,
				'total' => $grandTotalAmt,
				'tour_d_t' => $tourSlotTime->date . ' ' . $tourSlotTime->time_from,
				'booking_d_t' => $tourBooking->created_at,
				't_groups' => implode(' | ', $touristGroups),
				'tour_operator_id' => $tourSlotTime->tour_operator_id,
				'notif_for' => 'admin',
			];

			BookingNew::dispatch($booking);
			$booking['notif_for'] = 'Tour Operator';
			BookingNew::dispatch($booking);
		} catch(Exception $exception) {
			Log::error($exception);
			echo 'Error occurred (' . $exception->getLine() . '): '. $exception->getMessage();
		}
	}

	private function addCustomer() {
		$emailClients = [ 'gmail', 'yahoo', 'hotmail', 'outlook' ];
		$name = Str::random(10);

		$customer = \App\Models\Customer::create([
			'name' => $name,
			'email' => $name . '@' . $emailClients[rand(0, 3)] . '.com',
			'password' => Hash::make(123456),
			'phone_number' => rand(1000000000, 9999999999),
			'is_subscribed' => rand(0, 1) ? 'Yes' : 'No',
			'status' => 'Active',
			'updated_at' => '0000-00-00 00:00:00',
		]);

		return $customer;
	}
}
