<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Customer\CustomerRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLiabilityWaiverTest extends TestCase
{
	/**
	 * Regression test for CustomerRepository::updateLiabilityWaiver(). It used to call
	 * DB::beginTransaction() before validating the request, with validate() sitting
	 * outside the try/catch. A validation failure then threw past the catch block
	 * entirely, leaving the transaction open for the rest of the request -- any later
	 * query on the same connection (e.g. the check-in calls that follow signWaiver in
	 * TourCheckInController::signWaiver) would run nested inside it via a SAVEPOINT,
	 * which is what let a downstream deadlock's rollback fail with
	 * "SAVEPOINT transN does not exist" instead of surfacing the real deadlock error
	 * (see TourBookingRepository::updateCheckInStatus for that half of the fix).
	 */
	public function testValidationFailureDoesNotLeaveTransactionOpen(): void
	{
		$levelBefore = DB::transactionLevel();

		$request = new Request;
		$request->merge([
			// 'name' and 'age' omitted -> fails the 'required|array' rules
			'agree' => 'on',
		]);

		try {
			app(CustomerRepository::class)->updateLiabilityWaiver($request, 1);
			$this->fail('Expected a validation exception to be thrown.');
		} catch (ValidationException $exception) {
			// expected
		}

		$this->assertEquals(
			$levelBefore,
			DB::transactionLevel(),
			'updateLiabilityWaiver() left a transaction open after a validation failure.'
		);
	}
}
