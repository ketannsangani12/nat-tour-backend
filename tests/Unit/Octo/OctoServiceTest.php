<?php

namespace Tests\Unit\Octo;

use App\Services\Octo\OctoService;
use Tests\TestCase;

class OctoServiceTest extends TestCase
{
    protected $tenancy = false;

    // ── formatPhoneE164 ───────────────────────────────────────────────────────

    public function test_format_phone_e164_combines_code_and_digits(): void
    {
        $result = OctoService::formatPhoneE164('480-555-1234', '1');
        $this->assertSame('+14805551234', $result);
    }

    public function test_format_phone_e164_strips_non_numeric_from_both(): void
    {
        $result = OctoService::formatPhoneE164('(800) 123-4567', '+1');
        $this->assertSame('+18001234567', $result);
    }

    public function test_format_phone_e164_returns_null_when_phone_empty(): void
    {
        $this->assertNull(OctoService::formatPhoneE164('', '1'));
    }

    public function test_format_phone_e164_returns_null_when_code_empty(): void
    {
        $this->assertNull(OctoService::formatPhoneE164('4805551234', ''));
    }

    public function test_format_phone_e164_returns_null_when_both_null(): void
    {
        $this->assertNull(OctoService::formatPhoneE164(null, null));
    }

    // ── mapUnitType ───────────────────────────────────────────────────────────

    /** @dataProvider unitTypeMappings */
    public function test_map_unit_type(string $input, string $expected): void
    {
        $this->assertSame($expected, OctoService::mapUnitType($input));
    }

    public static function unitTypeMappings(): array
    {
        return [
            ['Adult',           'ADULT'],
            ['adult passenger', 'ADULT'],
            ['Child (3-12)',    'CHILD'],
            ['Senior Citizen',  'SENIOR'],
            ['Infant',          'INFANT'],
            ['Youth',           'YOUTH'],
            ['Family Pack',     'FAMILY'],
            ['Student',         'STUDENT'],
            ['Military',        'MILITARY'],
            ['Group Rate',      'OTHER'],
            ['',                'OTHER'],
        ];
    }

    // ── buildMediaObject ──────────────────────────────────────────────────────

    public function test_build_media_object_returns_expected_keys(): void
    {
        $media = OctoService::buildMediaObject('https://example.com/img.jpg', 'IMAGE', 'image/jpeg', 'Title', 'Caption', 'Owner');

        $this->assertSame([
            'type'      => 'image/jpeg',
            'rel'       => 'IMAGE',
            'src'       => 'https://example.com/img.jpg',
            'title'     => 'Title',
            'caption'   => 'Caption',
            'copyright' => 'Owner',
        ], $media);
    }

    public function test_build_media_object_defaults_to_image_png(): void
    {
        $media = OctoService::buildMediaObject('https://example.com/logo.png', 'LOGO');
        $this->assertSame('image/png', $media['type']);
    }
}
