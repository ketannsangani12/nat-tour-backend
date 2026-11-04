<?php

namespace Tests\Unit\Helpers;

use App\Models\TourPackage;
use Tests\TestCase;

/**
 * Covers the Hotmail/Outlook fallback "from" address used when sending
 * customer booking emails (see app/helpers.php: isHotmailFamilyEmail(),
 * resolveTourPackageFromEmail()).
 */
class EmailFromAddressResolutionTest extends TestCase
{
    public static function hotmailFamilyEmails(): array
    {
        return [
            'hotmail.com' => ['jane@hotmail.com', true],
            'hotmail.co.uk' => ['jane@hotmail.co.uk', true],
            'outlook.com' => ['jane@outlook.com', true],
            'outlook.co.uk' => ['jane@outlook.co.uk', true],
            'live.com' => ['jane@live.com', true],
            'msn.com' => ['jane@msn.com', true],
            'uppercase domain' => ['JANE@HOTMAIL.COM', true],
            'gmail.com' => ['jane@gmail.com', false],
            'yahoo.com' => ['jane@yahoo.com', false],
            'lookalike domain' => ['jane@nothotmail.com', false],
            'empty string' => ['', false],
            'no at sign' => ['not-an-email', false],
        ];
    }

    /**
     * @dataProvider hotmailFamilyEmails
     */
    public function test_is_hotmail_family_email(string $email, bool $expected): void
    {
        $this->assertSame($expected, isHotmailFamilyEmail($email));
    }

    public function test_resolve_returns_default_when_tour_package_id_missing(): void
    {
        $this->assertSame(
            'default@example.com',
            resolveTourPackageFromEmail(null, 'jane@hotmail.com', 'default@example.com')
        );
    }

    public function test_resolve_returns_default_when_customer_email_is_not_hotmail_family(): void
    {
        $this->assertSame(
            'default@example.com',
            resolveTourPackageFromEmail(1, 'jane@gmail.com', 'default@example.com')
        );
    }

    public function test_resolve_uses_hotmail_from_email_when_set_and_customer_is_hotmail_family(): void
    {
        $package = TourPackage::whereNotNull('id')->first();

        if (!$package) {
            $this->markTestSkipped('No tour_packages row available to test against.');
        }

        $originalHotmailFromEmail = $package->from_email_hotmail;

        try {
            $package->from_email_hotmail = 'hotmail-fallback@example.com';
            $package->save();

            $this->assertSame(
                'hotmail-fallback@example.com',
                resolveTourPackageFromEmail($package->id, 'jane@hotmail.com', 'default@example.com')
            );
        } finally {
            $package->from_email_hotmail = $originalHotmailFromEmail;
            $package->save();
        }
    }

    public function test_resolve_falls_back_to_default_when_hotmail_from_email_is_empty(): void
    {
        $package = TourPackage::whereNotNull('id')->first();

        if (!$package) {
            $this->markTestSkipped('No tour_packages row available to test against.');
        }

        $originalHotmailFromEmail = $package->from_email_hotmail;

        try {
            $package->from_email_hotmail = null;
            $package->save();

            $this->assertSame(
                'default@example.com',
                resolveTourPackageFromEmail($package->id, 'jane@hotmail.com', 'default@example.com')
            );
        } finally {
            $package->from_email_hotmail = $originalHotmailFromEmail;
            $package->save();
        }
    }
}
