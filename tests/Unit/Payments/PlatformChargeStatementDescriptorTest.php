<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PlatformChargeStatementDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test (no database, no Stripe): which PaymentIntent shapes get the operator's name moved
 * from statement_descriptor_suffix into a full statement_descriptor, and which are left exactly as
 * they were. The real Stripe behaviour behind this is covered by
 * PlatformHeldStatementDescriptorStripeTest.
 */
class PlatformChargeStatementDescriptorTest extends TestCase
{
    private function platformHeldParams(array $extra = []): array
    {
        return array_merge([
            'amount' => 10000,
            'currency' => 'cad',
            'payment_method_types' => ['card'],
            'statement_descriptor_suffix' => 'ASWTA TOURS',
            'metadata' => ['tenant_id' => 'aswta'],
        ], $extra);
    }

    public function test_platform_held_charge_gets_a_full_descriptor_and_loses_the_suffix(): void
    {
        $result = PlatformChargeStatementDescriptor::normalize($this->platformHeldParams());

        $this->assertSame('ASWTA TOURS', $result['statement_descriptor']);
        $this->assertArrayNotHasKey('statement_descriptor_suffix', $result);
    }

    public function test_every_other_param_is_preserved(): void
    {
        $params = $this->platformHeldParams(['customer' => 'cus_1', 'description' => 'Booking 7']);

        $result = PlatformChargeStatementDescriptor::normalize($params);

        unset($params['statement_descriptor_suffix']);
        $params['statement_descriptor'] = 'ASWTA TOURS';
        $this->assertEquals($params, $result);
    }

    public function test_charge_with_on_behalf_of_is_untouched(): void
    {
        $params = $this->platformHeldParams(['on_behalf_of' => 'acct_123']);

        $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params));
    }

    public function test_destination_charge_is_untouched(): void
    {
        $params = $this->platformHeldParams([
            'transfer_data' => ['destination' => 'acct_123'],
            'application_fee_amount' => 320,
        ]);

        $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params));
    }

    public function test_direct_charge_on_a_connected_account_is_untouched(): void
    {
        $params = $this->platformHeldParams();

        $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params, ['stripe_account' => 'acct_123']));
    }

    public function test_an_explicit_full_descriptor_is_left_alone(): void
    {
        $params = $this->platformHeldParams(['statement_descriptor' => 'ALREADY SET']);

        $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params));
    }

    public function test_no_suffix_means_nothing_to_do(): void
    {
        $params = $this->platformHeldParams();
        unset($params['statement_descriptor_suffix']);

        $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params));
    }

    public function test_blank_or_null_suffix_is_untouched(): void
    {
        foreach (['', '   ', null] as $suffix) {
            $params = $this->platformHeldParams(['statement_descriptor_suffix' => $suffix]);
            $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params));
        }
    }

    public function test_characters_stripe_forbids_are_removed_and_spaces_collapsed(): void
    {
        $result = PlatformChargeStatementDescriptor::normalize(
            $this->platformHeldParams(['statement_descriptor_suffix' => "  Kens  <Tours> \"Co\" *Inc\\ "])
        );

        $this->assertSame('Kens Tours Co Inc', $result['statement_descriptor']);
    }

    public function test_long_names_are_cut_to_stripes_22_character_limit(): void
    {
        $result = PlatformChargeStatementDescriptor::normalize(
            $this->platformHeldParams(['statement_descriptor_suffix' => 'GRAND CANYON WEST ADVENTURE TOURS LTD'])
        );

        $this->assertSame(22, mb_strlen($result['statement_descriptor']));
        $this->assertSame('GRAND CANYON WEST ADVE', $result['statement_descriptor']);
    }

    public function test_a_cut_never_leaves_a_trailing_space(): void
    {
        $result = PlatformChargeStatementDescriptor::normalize(
            $this->platformHeldParams(['statement_descriptor_suffix' => 'ABCDEFGHIJKLMNOPQRSTU VWXYZ'])
        );

        $this->assertSame('ABCDEFGHIJKLMNOPQRSTU', $result['statement_descriptor']);
    }

    public function test_a_value_that_cannot_be_a_valid_descriptor_keeps_the_suffix(): void
    {
        foreach (['ABCD', '12345', '*<>*'] as $suffix) {
            $params = $this->platformHeldParams(['statement_descriptor_suffix' => $suffix]);
            $this->assertSame($params, PlatformChargeStatementDescriptor::normalize($params), "suffix '{$suffix}'");
        }
    }
}
