<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * KSA mobile normalisation (§6.6).
 *
 * Storing one canonical form is what stops the same buyer becoming two
 * customer records because they typed 05… on Monday and +9665… on Friday —
 * and it is what makes the dashboard's phone search find anything at all.
 */
final class CustomerPhoneTest extends TestCase
{
    /**
     * Keyed so a failure names the form that broke rather than an index.
     *
     * @return array<string, array{string, string}>
     */
    public static function acceptedForms(): array
    {
        return [
            'local with leading zero' => ['0512345678', '+966512345678'],
            'bare national' => ['512345678', '+966512345678'],
            'full international' => ['+966512345678', '+966512345678'],
            'international without plus' => ['966512345678', '+966512345678'],
            'double-zero international' => ['00966512345678', '+966512345678'],
            'spaced' => ['05 1234 5678', '+966512345678'],
            'dashed' => ['05-1234-5678', '+966512345678'],
            'spaced international' => ['+966 51 234 5678', '+966512345678'],
            'other mobile prefixes' => ['0551234567', '+966551234567'],
            'prefix 59' => ['0591234567', '+966591234567'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedForms')]
    public function it_normalises_what_people_actually_type(string $input, string $expected): void
    {
        $this->assertSame($expected, Customer::normalisePhone($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedForms(): array
    {
        return [
            'empty' => [''],
            'landline' => ['0112345678'],
            'too short' => ['05123456'],
            'too long' => ['05123456789'],
            'not a mobile prefix' => ['0412345678'],
            'letters' => ['not-a-phone'],
            'foreign number' => ['+447911123456'],
            'just the country code' => ['+966'],
        ];
    }

    #[Test]
    #[DataProvider('rejectedForms')]
    public function it_rejects_anything_that_is_not_a_ksa_mobile(string $input): void
    {
        $this->assertNull(Customer::normalisePhone($input), "should have rejected: {$input}");
    }

    #[Test]
    public function normalising_is_idempotent(): void
    {
        $once = Customer::normalisePhone('0512345678');
        $this->assertNotNull($once);

        $this->assertSame($once, Customer::normalisePhone($once));
    }

    #[Test]
    public function every_accepted_form_of_one_number_collapses_to_the_same_customer_key(): void
    {
        $forms = ['0512345678', '512345678', '+966512345678', '00966512345678', '05 1234 5678'];

        $normalised = array_map(
            fn (string $form): ?string => Customer::normalisePhone($form),
            $forms
        );

        $this->assertCount(1, array_unique($normalised), 'one person must not become several customers');
    }
}
