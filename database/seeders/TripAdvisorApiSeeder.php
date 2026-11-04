<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TourPackage;

class TripAdvisorApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return mixed
     */
    public function run()
    {
        $update = 0;
		$tour_packages = TourPackage::where('tour_operator_id',1)->get();
		if(!empty($tour_packages)){
			foreach ($tour_packages as $key => $value) {
				$update = $value->update([
					'viator_flags' => viatorFlags()
				]);
			}
		}
		return $update;
    }
}
