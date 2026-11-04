<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlotsSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		$tourPackage = DB::table('tour_packages')
			->where('status', 'Active')
			->where('deleted_by', 0)
			->orderBy(DB::raw('RAND()'))
			->first([ 'id', 'name', 'duration', 'duration_unit', 'tour_operator_id' ]);
		
		if(!$tourPackage)
			exit('No tour package found.');
		
		$daySeconds = 60 * 60 * 24 * (rand(0, 1) ? -1 : 1);
		$seconds = strtotime(date('Y-m-d 00:00:00'));
		
		while(1) {
			$date = date('Y-m-d', $seconds);

			$dSlot = DB::table('tour_slot_dates')
				->where('date', $date)
				->where('status', 'Active')
				->where('deleted_by', 0)
				->where('tour_package_id', $tourPackage->id)
				->first([ 'id' ]);

			if($dSlot) {
				$seconds += $daySeconds; // increment/decrement by a day
				continue;
			}

			DB::beginTransaction();
			$slotId = DB::table('tour_slot_dates')
				->insertGetId([
					'tour_package_id' => $tourPackage->id,
					'date' => $date,
					'seats' => rand(10, 50),
					'created_by' => 2,
					'created_by_user_type' => 'Admin',
				]);

			$seconds += rand(3, 60 * 60 * 8);
			$tourPackage->duration = trimDeci(ceil($tourPackage->duration));
			echo "{$tourPackage->duration} {$tourPackage->duration_unit} | $date | Tour Op. #: {$tourPackage->tour_operator_id} | Pkg: {$tourPackage->name}\n";

			for($i = 0, $dateTo = $date; $i < 9 && $dateTo == $date; $i++) {
				$timeTo = strtotime("+{$tourPackage->duration} {$tourPackage->duration_unit}", $seconds);
				$dateTo = date('Y-m-d', $timeTo);
				
				DB::table('tour_slot_times')
					->insert([
						'tour_slot_id' => $slotId,
						'time_from' => date('H:i:00', $seconds),
						'time_to' => $dateTo == $date ? date('H:i:00', $timeTo) : null,
						'created_by' => 2,
						'created_by_user_type' => 'Admin',
						//'updated_at' => '0000-00-00 00:00:00',
					]);
				
				$seconds = $timeTo;
			}

			DB::commit();

			break;
		};
	}
}
