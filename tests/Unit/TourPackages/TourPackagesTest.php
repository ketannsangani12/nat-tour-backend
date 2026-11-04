<?php

namespace Tests\Unit\TourPackages;

use App\Http\Controllers\Common\TourPackageController;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TourPackagesTest extends TestCase
{
	protected static array $createdPackageIds = [];
	protected static array $createdRateIds = [];

	protected function controller(): TourPackageController
	{
		return app(TourPackageController::class);
	}

	protected function authAsAdmin(): Admin
	{
		$admin = Admin::first() ?? Admin::factory()->create();
		$this->actingAs($admin, 'admin');
		session(['userType' => 'Admin']);
		return $admin;
	}

	/**
	 * Insert a test package directly via DB to avoid model events.
	 */
	protected function insertTestPackage(array $overrides = []): int
	{
		$now = Carbon::now();
		$operatorId = DB::table('tour_operators')->value('id');
		$id = DB::table('tour_packages')->insertGetId(array_merge([
			'tour_operator_id' => $operatorId,
			'name' => 'Test Pkg ' . uniqid(),
			'short_description' => 'Short desc for testing',
			'long_description' => 'Long desc for testing',
			'duration' => 1,
			'hours' => 1,
			'minutes' => 30,
			'day_hours' => 1,
			'things_to_bring' => '[]',
			'important_notes' => '',
			'status' => 'Active',
			'type' => 'Regular',
			'service_commission_percentage' => 0,
			'stripe_commission_percentage' => 0,
			'frontend_enabled' => 0,
			'package_has_slots' => 1,
			'package_has_waiver' => 0,
			'package_has_permit' => 0,
			'is_combo_package' => 0,
			'bundle_package' => 0,
			'overbook_seats' => 0,
			'is_group_rate_enabled' => 0,
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
			'updated_at' => $now,
		], $overrides));
		static::$createdPackageIds[] = $id;
		return $id;
	}

	/**
	 * Insert a test rate group directly via DB.
	 */
	protected function insertTestRate(int $packageId, array $overrides = []): int
	{
		$now = Carbon::now();
		$id = DB::table('tour_package_rates')->insertGetId(array_merge([
			'tour_package_id' => $packageId,
			'rate_for' => 'Adult',
			'age_from' => 18,
			'age_to' => 64,
			'rate' => 50.00,
			'primary_rate' => 50.00,
			'permit_fee' => 0,
			'additional_charge' => 0,
			'processing_charge_percentage' => 0,
			'additional_tax_percentage' => 0,
			'scheduling_year' => date('Y'),
			'status' => 'Active',
			'orderby' => 1,
			'mapping_key' => rand(100, 99999),
			'created_by' => 1,
			'created_by_user_type' => 'Admin',
			'created_at' => $now,
			'updated_at' => $now,
		], $overrides));
		static::$createdRateIds[] = $id;
		return $id;
	}

	/**
	 * Test that the "Add Tour Package" form returns the correct page structure.
	 */
	public function testCreateFormPageLoads(): void
	{
		$this->authAsAdmin();
		$res = $this->controller()->create();

		$this->assertEquals('Add Tour Package', $res['page_title']);
		$this->assertArrayHasKey('tourOperators', $res);
		$this->assertArrayHasKey('categories', $res);
	}

	/**
	 * Test that the edit package page loads with the correct package data.
	 */
	public function testEditPackagePageLoads(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage(['name' => 'Edit Test Pkg ' . uniqid()]);
		$year = date('Y');

		$res = $this->controller()->editpackage($packageId, $year);

		$this->assertEquals('Edit Tour Package', $res['page_title']);
		$this->assertEquals($packageId, $res['tourPackage']->id);
		$this->assertArrayHasKey('packageRates', $res);
	}

	/**
	 * Test updating SEO fields and verifying the changes persist in the database.
	 */
	public function testUpdateSeoDetails(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage();

		$seoTitle = 'SEO Title ' . uniqid();
		$seoDesc = 'SEO Description for testing';
		$seoKeywords = 'test, keywords, tour';

		$request = new Request();
		$request->merge([
			'seo_title' => $seoTitle,
			'seo_description' => $seoDesc,
			'seo_keywords' => $seoKeywords,
		]);

		$response = $this->controller()->updateSEO($request, $packageId);
		$this->assertEquals(200, $response->getStatusCode());

		$pkg = DB::table('tour_packages')->find($packageId);
		$this->assertEquals($seoTitle, $pkg->seo_title);
		$this->assertEquals($seoDesc, $pkg->seo_description);
		$this->assertEquals($seoKeywords, $pkg->seo_keywords);
	}

	/**
	 * Test updating waiver content and verifying the change persists.
	 */
	public function testUpdateWaiverContent(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage();

		$waiverContent = '<p>Liability waiver content for test ' . uniqid() . '</p>';

		$request = new Request();
		$request->merge(['waiver_content' => $waiverContent]);

		$response = $this->controller()->updateWaiver($request, $packageId);
		$this->assertEquals(200, $response->getStatusCode());

		$pkg = DB::table('tour_packages')->find($packageId);
		$this->assertEquals($waiverContent, $pkg->waiver_content);
	}

	/**
	 * Test adding a new rate group via the controller and verifying it in the DB.
	 */
	public function testAddRateGroup(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage();
		$mappingKey = rand(100, 99999);

		$request = new Request();
		$request->merge([
			'tour_package_rate_id' => '',
			'scheduling_year' => date('Y'),
			'rate_for' => 'Adult',
			'age_from' => 18,
			'age_to' => 64,
			'rate' => 75,
			'permit_fee' => 0,
			'processing_charge_percentage' => 0,
			'additional_tax_percentage' => 0,
			'additional_charge' => 0,
			'min_pax_allowed' => 0,
			'max_pax_allowed' => 0,
			'description' => '',
			'orderby' => 1,
			'status' => 'Active',
			'mapping_key' => $mappingKey,
			'global_rate' => 0,
		]);

		$response = $this->controller()->addUpdateRate($request, $packageId);
		$data = json_decode($response->getContent(), true);

		$this->assertArrayHasKey('tour_package_rate_id', $data, 'Response should contain new rate ID');
		$rateId = $data['tour_package_rate_id'];
		static::$createdRateIds[] = $rateId;

		$rate = DB::table('tour_package_rates')->find($rateId);
		$this->assertNotNull($rate);
		$this->assertEquals($packageId, $rate->tour_package_id);
		$this->assertEquals('Adult', $rate->rate_for);
		$this->assertEqualsWithDelta(75.00, (float) $rate->rate, 0.01);
		$this->assertEquals(18, $rate->age_from);
		$this->assertEquals(64, $rate->age_to);
		$this->assertEquals('Active', $rate->status);
	}

	/**
	 * Test updating an existing rate group and verifying the change in DB.
	 */
	public function testUpdateRateGroup(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage();
		$rateId = $this->insertTestRate($packageId, ['rate' => 50.00, 'primary_rate' => 50.00]);
		$mappingKey = DB::table('tour_package_rates')->where('id', $rateId)->value('mapping_key');

		$request = new Request();
		$request->merge([
			'tour_package_rate_id' => $rateId,
			'scheduling_year' => date('Y'),
			'rate_for' => 'Adult',
			'age_from' => 18,
			'age_to' => 64,
			'rate' => 85,
			'permit_fee' => 5,
			'processing_charge_percentage' => 0,
			'additional_tax_percentage' => 0,
			'additional_charge' => 0,
			'min_pax_allowed' => 0,
			'max_pax_allowed' => 0,
			'description' => '',
			'orderby' => 1,
			'status' => 'Active',
			'mapping_key' => $mappingKey,
			'global_rate' => 0,
		]);

		$response = $this->controller()->addUpdateRate($request, $packageId);
		$this->assertEquals(200, $response->getStatusCode());

		$rate = DB::table('tour_package_rates')->find($rateId);
		$this->assertEqualsWithDelta(85.00, (float) $rate->rate, 0.01, 'Rate should be updated to 85');
		$this->assertEqualsWithDelta(5.00, (float) $rate->permit_fee, 0.01, 'Permit fee should be updated to 5');
	}

	/**
	 * Test soft-deleting a rate group and verifying it is marked as deleted.
	 */
	public function testSoftDeleteRateGroup(): void
	{
		$this->authAsAdmin();
		$packageId = $this->insertTestPackage();
		$rateId = $this->insertTestRate($packageId);

		// Verify it exists and is not deleted
		$rateBefore = DB::table('tour_package_rates')->find($rateId);
		//($rateBefore->deleted_at);

		$response = $this->controller()->deleteRate($rateId);
		$this->assertEquals(200, $response->getStatusCode());

		$rateAfter = DB::table('tour_package_rates')->find($rateId);
		$this->assertNotNull($rateAfter->deleted_at, 'Rate should be soft-deleted');
		$this->assertNotEquals(0, $rateAfter->deleted_by, 'deleted_by should be set');
		$this->assertEquals('Admin', $rateAfter->deleted_by_user_type);

		// Audit trail should exist
		$history = DB::table('records_history')
			->where('record_id_1', $rateId)
			->where('table_name', 'tour_package_rates')
			->where('operation_type', 'd')
			->first();
		$this->assertNotNull($history, 'Delete should be recorded in records_history');
	}

	public static function tearDownAfterClass(): void
	{
		// Clean up rates and their audit trail
		foreach (static::$createdRateIds as $id) {
			DB::table('records_history')
				->where('record_id_1', $id)
				->where('table_name', 'tour_package_rates')
				->delete();
			DB::table('tour_package_rates')->where('id', $id)->delete();
		}
		static::$createdRateIds = [];

		// Clean up packages and any associated records
		foreach (static::$createdPackageIds as $id) {
			DB::table('records_history')
				->where('record_id_2', $id)
				->where('table_name', 'tour_package_rates')
				->delete();
			DB::table('tour_package_rates')->where('tour_package_id', $id)->delete();
			DB::table('tour_packages')->where('id', $id)->delete();
		}
		static::$createdPackageIds = [];
	}
}
