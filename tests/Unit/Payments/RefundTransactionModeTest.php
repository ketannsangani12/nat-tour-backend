<?php

namespace Tests\Unit\Payments;

use App\Services\RefundBooking\RefundTransactionUpdateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Fixture\CreateABooking;
use Tests\TestCase;

/**
 * POS refunds are saved in the mode of the payment they refund. Against the real dixies tenant DB (no
 * rollback; the fixture's cleanup() deletes every transactions row of its booking).
 */
class RefundTransactionModeTest extends TestCase
{
	/** @var CreateABooking[] */
	private array $bookings = [];

	protected function tearDown(): void
	{
		try {
			foreach ($this->bookings as $booking) {
				$booking->cleanup();
			}
		} finally {
			parent::tearDown();
		}
	}

	private function booking(string $mode = 'platform_held', string $amount = '100.00', ?string $fee = '8.00'): array
	{
		$booking = (new CreateABooking())
			->withTourists(1)
			->withRate((float) $amount)
			->withPaymentMethod('Card')
			->withDate(Carbon::now()->subDays(3)->toDateString())
			->create();
		$this->bookings[] = $booking;

		$pi = 'pi_test_fix_' . uniqid();
		DB::table('transactions')->where('id', $booking->transactionId)->update([
			'amount' => $amount, 'fee_amount' => $fee, 'payment_mode' => $mode, 'transaction_number' => $pi,
			'request_from' => 'BACKEND', 'via' => 'Card',
		]);

		return [$booking, $pi];
	}

	public function test_pos_refund_is_saved_in_the_mode_of_the_payment_it_refunds(): void
	{
		[$platformHeld, $platformPi] = $this->booking('platform_held');
		[$destination, $destinationPi] = $this->booking('destination');
		$request = Request::create('/admin/refund', 'POST');
		$original = DB::table('transactions')->find($platformHeld->transactionId);

		$refund = RefundTransactionUpdateService::run($platformHeld->bookingId, 30.00, $platformPi, 0, null, 'Card', $original, $request);
		$this->assertSame('platform_held', DB::table('transactions')->where('id', $refund->id)->value('payment_mode'));

		$refund = RefundTransactionUpdateService::run($destination->bookingId, 30.00, $destinationPi, 0, null, 'Card', $original, $request);
		$this->assertSame('destination', DB::table('transactions')->where('id', $refund->id)->value('payment_mode'));

		// Cash / invoice / wire refunds carry no Stripe payment: unchanged default.
		$refund = RefundTransactionUpdateService::run($platformHeld->bookingId, 10.00, '', 0, null, 'Cash', $original, $request);
		$this->assertSame('destination', DB::table('transactions')->where('id', $refund->id)->value('payment_mode'));
	}
}
