<?php

namespace Tests\Unit\Stripe;

use App\Utils\StripeUtils;
use ReflectionMethod;
use Tests\TestCase;

/**
 * CMD: php artisan test --filter=StatementDescriptorTest
 */
class StatementDescriptorTest extends TestCase
{
    private static ReflectionMethod $buildDescriptor;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$buildDescriptor = new ReflectionMethod(StripeUtils::class, 'buildDescriptorFallback');
        self::$buildDescriptor->setAccessible(true);
    }

    private function callBuildDescriptor(string $operatorName, string $tourName = ''): string
    {
        return self::$buildDescriptor->invoke(null, $operatorName, $tourName);
    }

    public function test_basic_operator_and_tour_name(): void
    {
        $result = $this->callBuildDescriptor('Dixies', 'Canyon Tour');
        $this->assertEquals('DIXIES CANYON TOUR', $result);
    }

    public function test_strips_special_characters(): void
    {
        $result = $this->callBuildDescriptor('Dixie\'s Tours!', 'Canyon <Tour>');
        $this->assertEquals('DIXIES TOURS CANYON TO', $result);
    }

    public function test_truncates_to_22_characters(): void
    {
        $result = $this->callBuildDescriptor('Very Long Operator Name', 'Very Long Tour Package Name');
        $this->assertLessThanOrEqual(22, strlen($result));
    }

    public function test_operator_name_only(): void
    {
        $result = $this->callBuildDescriptor('Dixies', '');
        $this->assertEquals('DIXIES', $result);
    }

    public function test_empty_names_falls_back_to_tenant_id(): void
    {
        $result = $this->callBuildDescriptor('', '');
        $this->assertEquals('DIXIES', $result);
    }

    public function test_special_chars_only_falls_back_to_tenant_id(): void
    {
        $result = $this->callBuildDescriptor('!!!', '###');
        $this->assertEquals('DIXIES', $result);
    }

    public function test_preserves_dots_and_hyphens(): void
    {
        $result = $this->callBuildDescriptor('A.B.C', 'X-Y');
        $this->assertEquals('A.B.C X-Y', $result);
    }

    public function test_tour_operator_descriptor_formatting(): void
    {
        // Simulates the logic in TourOperator::saved()
        $name = 'Dixie\'s "Best" Tours*';
        $descriptor = strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $name));
        $descriptor = substr(preg_replace('/[<>\\\'"*]/', '', $descriptor), 0, 22);
        $descriptor = str_pad($descriptor, 5, '.');

        $this->assertEquals('DIXIES BEST TOURS', $descriptor);
        $this->assertGreaterThanOrEqual(5, strlen($descriptor));
        $this->assertDoesNotMatchRegularExpression('/[<>\\\'"*]/', $descriptor);
    }

    public function test_tour_operator_descriptor_short_name_padded(): void
    {
        $name = 'AB';
        $descriptor = strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $name));
        $descriptor = substr(preg_replace('/[<>\\\'"*]/', '', $descriptor), 0, 22);
        $descriptor = str_pad($descriptor, 5, '.');

        $this->assertEquals('AB...', $descriptor);
        $this->assertGreaterThanOrEqual(5, strlen($descriptor));
    }

    public function test_tour_operator_descriptor_respects_max_length(): void
    {
        $name = 'Extremely Long Tour Operator Name That Exceeds Limit';
        $descriptor = strtoupper(preg_replace('/[^A-Za-z0-9 \-.]/', '', $name));
        $descriptor = substr(preg_replace('/[<>\\\'"*]/', '', $descriptor), 0, 22);
        $descriptor = str_pad($descriptor, 5, '.');

        $this->assertLessThanOrEqual(22, strlen($descriptor));
    }
}
