<?php

namespace Tests\Unit\Packages;

use Tests\TestCase;
use App\Models\TourPackageForm;
use App\Repositories\TourPackage\TourPackageRepository;
use Illuminate\Support\Facades\DB;

class TourPackageFormTest extends TestCase
{
	protected TourPackageRepository $repo;
	protected static array $formIds = [];
	protected static ?int $packageId = null;

	public function setUp(): void
	{
		parent::setUp();
		$this->repo = app(TourPackageRepository::class);

		if (!static::$packageId) {
			static::$packageId = DB::table('tour_packages')
				->where('status', 'Active')
				->value('id');
		}
	}

	/**
	 * Create a form record and track it for cleanup.
	 */
	protected function createForm(string $name, array $fields, string $status = 'active'): TourPackageForm
	{
		$form = new TourPackageForm();
		$form->form_name = $name;
		$form->form_package_id = static::$packageId;
		$form->status = $status;
		$form->form_fields = json_encode($fields);
		$form->save();
		static::$formIds[] = $form->id;
		return $form;
	}

	/**
	 * Test that a custom form can be created and its JSON fields
	 * survive a round-trip to the database.
	 */
	public function testFormFieldsRoundTripAsJson(): void
	{
		$this->assertNotNull(static::$packageId, 'At least one active tour package must exist');

		$fields = [
			['name' => 'Full Name', 'type' => 'text', 'required' => 'true',
				'priceInfo' => ['enabled' => 'false', 'price' => '0']],
			['name' => 'Age', 'type' => 'number', 'required' => 'false',
				'priceInfo' => ['enabled' => 'false', 'price' => '0']],
		];

		$form = $this->createForm('Test Form', $fields);

		// Re-fetch from DB to ensure round-trip
		$saved = TourPackageForm::find($form->id);
		$this->assertNotNull($saved);
		$this->assertEquals('Test Form', $saved->form_name);
		$this->assertEquals(static::$packageId, $saved->form_package_id);

		$decoded = json_decode($saved->form_fields, true);
		$this->assertCount(2, $decoded);
		$this->assertEquals('Full Name', $decoded[0]['name']);
		$this->assertEquals('text', $decoded[0]['type']);
		$this->assertEquals('Age', $decoded[1]['name']);
		$this->assertEquals('number', $decoded[1]['type']);
	}

	/**
	 * Test that getCustomForm returns an active form for the package
	 * and includes the expected fields.
	 */
	public function testGetCustomFormReturnsByPackageId(): void
	{
		$this->assertNotNull(static::$packageId);

		$fields = [
			['name' => 'Shuttle', 'type' => 'checkbox', 'required' => 'false',
				'priceInfo' => ['enabled' => 'true', 'price' => '30.00']],
		];

		$this->createForm('Repo Lookup Form', $fields);

		$result = $this->repo->getCustomForm(static::$packageId);

		$this->assertNotNull($result, 'getCustomForm should return a form for the package');
		$this->assertNotEmpty($result->form_name);
		$this->assertNotEmpty($result->form_fields);

		$decoded = json_decode($result->form_fields, true);
		$this->assertIsArray($decoded);
		$this->assertNotEmpty($decoded);
		$this->assertArrayHasKey('name', $decoded[0]);
	}

	/**
	 * Test that getCustomForm does not return inactive forms.
	 */
	public function testGetCustomFormExcludesInactiveForms(): void
	{
		$this->assertNotNull(static::$packageId);

		// Use a unique package ID so no other active form interferes
		$uniquePackageId = DB::table('tour_packages')->max('id') + 9000;

		$form = new TourPackageForm();
		$form->form_name = 'Inactive Form';
		$form->form_package_id = $uniquePackageId;
		$form->status = 'inactive';
		$form->form_fields = json_encode([['name' => 'Hidden', 'type' => 'text']]);
		$form->save();
		static::$formIds[] = $form->id;

		$result = $this->repo->getCustomForm($uniquePackageId);
		$this->assertNull($result, 'getCustomForm should not return inactive forms');
	}

	/**
	 * Test that form fields with pricing info are stored correctly.
	 */
	public function testFormFieldsPreservePricingInfo(): void
	{
		$this->assertNotNull(static::$packageId);

		$fields = [
			[
				'name' => 'Baby Backpack',
				'type' => 'checkbox',
				'required' => 'false',
				'priceInfo' => [
					'type' => 'setprice',
					'unit' => 'USD',
					'price' => '12.00',
					'enabled' => 'true',
				],
			],
			[
				'name' => 'Airport Pickup',
				'type' => 'checkbox',
				'required' => 'false',
				'priceInfo' => [
					'type' => 'setprice',
					'unit' => 'USD',
					'price' => '50.90',
					'enabled' => 'true',
				],
			],
		];

		$form = $this->createForm('Priced Addons Form', $fields);

		$saved = TourPackageForm::find($form->id);
		$decoded = json_decode($saved->form_fields, true);

		$this->assertCount(2, $decoded);
		$this->assertEquals('12.00', $decoded[0]['priceInfo']['price']);
		$this->assertEquals('true', $decoded[0]['priceInfo']['enabled']);
		$this->assertEquals('50.90', $decoded[1]['priceInfo']['price']);

		// Verify the total price of priced add-ons can be computed
		$totalAddonPrice = 0;
		foreach ($decoded as $field) {
			if ($field['priceInfo']['enabled'] === 'true') {
				$totalAddonPrice += (float) $field['priceInfo']['price'];
			}
		}
		$this->assertEquals(62.90, $totalAddonPrice, 'Sum of addon prices should be $12.00 + $50.90 = $62.90');
	}

	public static function tearDownAfterClass(): void
	{
		foreach (static::$formIds as $id) {
			DB::table('tour_package_form')->where('id', $id)->delete();
		}
		static::$formIds = [];
		static::$packageId = null;
	}
}
