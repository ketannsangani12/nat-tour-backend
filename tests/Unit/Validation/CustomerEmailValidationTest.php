<?php

namespace Tests\Unit\Validation;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Keeps the admin/tour-operator booking-create email check honest.
 *
 * Two layers are exercised:
 *  - the client-side regex in public/js/user-common/backend-card-reader.js
 *    (and backend-card-reader-info.js) — mirrored here as a PCRE.
 *  - Laravel's `email` rule, which is what BookingRequest/CartBookingRequest
 *    apply server-side.
 *
 * Both must accept every "should pass" address and reject every
 * "should fail" one, otherwise a regression in either layer will let the
 * "Invalid Email" swal fire on legitimate addresses (e.g. the Wanadoo.fr
 * report that kicked off this fix).
 */
class CustomerEmailValidationTest extends TestCase
{
    protected $tenancy = false;

    /** Mirror of the JS regex in backend-card-reader*.js. */
    private const JS_EMAIL_REGEX = '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/';

    public static function validEmails(): array
    {
        return [
            'standard gmail' => ['test@gmail.com'],
            'french wanadoo (original report)' => ['mickael.vanderkelen@wanadoo.fr'],
            'dot in local part' => ['first.last@example.com'],
            'plus tag' => ['user+tag@example.com'],
            'underscore' => ['user_name@example.com'],
            'percent' => ['user%percent@example.com'],
            'hyphen in local' => ['first-last@example.com'],
            'numeric local' => ['123@example.com'],
            'subdomain' => ['user@mail.example.com'],
            'country tld two letters' => ['user@example.fr'],
            'country tld .uk' => ['user@example.uk'],
            'country tld .io' => ['user@example.io'],
            'longer tld .museum' => ['user@example.museum'],
            'longer tld .travel' => ['user@example.travel'],
            'hyphen in domain' => ['user@my-domain.com'],
            'mixed case' => ['User.Name@Example.COM'],
        ];
    }

    /** Rejected by BOTH the JS regex and Laravel's email rule. */
    public static function invalidEmails(): array
    {
        return [
            'empty string' => [''],
            'missing @' => ['not-an-email'],
            'trailing space (pre-trim catches this)' => ['user@example.com '],
            'leading space (pre-trim catches this)' => [' user@example.com'],
            'double @' => ['user@@example.com'],
            'tld dot only' => ['user@example.'],
            'space inside' => ['us er@example.com'],
        ];
    }

    /**
     * Cases where the JS regex is stricter than Laravel's `email` rule.
     * These should be rejected client-side but Laravel will accept them.
     * Documenting them here so a future change to either layer is intentional.
     */
    public static function jsOnlyRejections(): array
    {
        return [
            'missing tld' => ['user@example'],
            'unicode local part' => ['üser@example.com'],
        ];
    }

    /**
     * @dataProvider validEmails
     */
    public function test_js_regex_accepts_valid_email(string $email): void
    {
        $this->assertSame(
            1,
            preg_match(self::JS_EMAIL_REGEX, $email),
            "JS regex should accept: {$email}"
        );
    }

    /**
     * @dataProvider invalidEmails
     */
    public function test_js_regex_rejects_invalid_email(string $email): void
    {
        $this->assertSame(
            0,
            preg_match(self::JS_EMAIL_REGEX, $email),
            "JS regex should reject: {$email}"
        );
    }

    /**
     * @dataProvider validEmails
     */
    public function test_laravel_email_rule_accepts_valid_email(string $email): void
    {
        $validator = Validator::make(
            ['customer_email' => $email],
            ['customer_email' => 'required|email|max:50']
        );
        $this->assertFalse(
            $validator->fails(),
            "Laravel email rule should accept: {$email} — errors: "
                . json_encode($validator->errors()->toArray())
        );
    }

    /**
     * @dataProvider invalidEmails
     */
    public function test_laravel_email_rule_rejects_invalid_email(string $email): void
    {
        $validator = Validator::make(
            ['customer_email' => $email],
            ['customer_email' => 'required|email|max:50']
        );
        $this->assertTrue(
            $validator->fails(),
            "Laravel email rule should reject: {$email}"
        );
    }

    /**
     * @dataProvider jsOnlyRejections
     */
    public function test_js_regex_rejects_while_laravel_accepts(string $email): void
    {
        $this->assertSame(
            0,
            preg_match(self::JS_EMAIL_REGEX, $email),
            "JS regex should reject: {$email}"
        );

        $validator = Validator::make(
            ['customer_email' => $email],
            ['customer_email' => 'required|email|max:50']
        );
        $this->assertFalse(
            $validator->fails(),
            "Laravel email rule is expected to accept (documenting gap): {$email}"
        );
    }

    public function test_trim_normalizes_pasted_whitespace(): void
    {
        // Simulates the trim() the JS now applies to the input before validating.
        $raw = "  mickael.vanderkelen@wanadoo.fr\t";
        $trimmed = trim($raw);

        $this->assertSame('mickael.vanderkelen@wanadoo.fr', $trimmed);
        $this->assertSame(1, preg_match(self::JS_EMAIL_REGEX, $trimmed));
    }
}
