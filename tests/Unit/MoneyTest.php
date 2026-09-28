<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The decimal-safe arithmetic every money figure in the application goes
 * through.
 *
 * Worth its own test file because the failures it prevents are invisible: a
 * truncated halala on one line of one order does not break anything, it just
 * makes a receipt fail to add up months later.
 */
final class MoneyTest extends TestCase
{
    #[Test]
    public function rounding_is_half_up_not_truncation(): void
    {
        // bcmath truncates, which would give 1.00 for all three of these.
        $this->assertSame('1.01', Money::round('1.005'));
        $this->assertSame('1.00', Money::round('1.004'));
        $this->assertSame('1.01', Money::round('1.0051'));

        // The case that motivates all of this: extracting 15% VAT from 155.25
        // is 20.25 exactly, but 0.15 x 155.25 = 23.2875 truncates to 23.28 when
        // 23.29 is correct.
        $this->assertSame('23.29', Money::round('23.2875'));
    }

    #[Test]
    public function rounding_a_negative_rounds_away_from_zero(): void
    {
        // Truncating a negative rounds the wrong way — towards zero — which on
        // a refund line understates what is owed.
        $this->assertSame('-1.01', Money::round('-1.005'));
        $this->assertSame('-1.00', Money::round('-1.004'));
    }

    #[Test]
    public function negative_zero_is_never_produced(): void
    {
        // "-0.00" on a receipt is a defect nobody can explain to a customer.
        $this->assertSame('0.00', Money::round('-0.001'));
        $this->assertSame('0.00', Money::sub('10.00', '10.00'));
    }

    #[Test]
    public function arithmetic_is_exact_at_money_scale(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'), 'the float classic');
        $this->assertSame('402.50', Money::mul('350.00', '1.15'));
        $this->assertSame('135.00', Money::div('155.25', '1.15'));
        $this->assertSame('268.31', Money::multiplyByQty('38.33', 7));
    }

    #[Test]
    public function summing_many_lines_does_not_drift(): void
    {
        // 0.01 a hundred times is exactly 1.00, and a float sum is not.
        $this->assertSame('1.00', Money::sum(array_fill(0, 100, '0.01')));
        $this->assertSame('0.00', Money::sum([]));
    }

    #[Test]
    public function dividing_by_zero_yields_zero_rather_than_an_error(): void
    {
        // A zero rate is a legitimate configuration, and a fatal error on a
        // storefront page is a worse answer than a zero.
        $this->assertSame('0.00', Money::div('100.00', '0'));
    }

    #[Test]
    public function non_numeric_input_becomes_zero_rather_than_a_warning(): void
    {
        // These arrive from cast attributes and view variables, where a null
        // price must not take a page down.
        $this->assertSame('0.00', Money::of(null));
        $this->assertSame('0.00', Money::of(''));
        $this->assertSame('0.00', Money::of('not a price'));
        $this->assertSame('155.25', Money::of(155.25));
        $this->assertSame('155.00', Money::of(155));
    }

    #[Test]
    public function comparison_works_at_money_scale(): void
    {
        $this->assertTrue(Money::equals('300.00', '300.000'));
        $this->assertSame(0, Money::compare('300.00', '300.001'), 'below money scale is not a difference');
        $this->assertSame(1, Money::compare('300.01', '300.00'));
        $this->assertSame(-1, Money::compare('299.99', '300.00'));
        $this->assertTrue(Money::isZero('0.000'));
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
    }

    #[Test]
    public function at_least_zero_clamps_a_negative_remainder(): void
    {
        // Drives "spend X more for free delivery", which is meaningless once
        // the threshold is passed.
        $this->assertSame('50.00', Money::atLeastZero('50.00'));
        $this->assertSame('0.00', Money::atLeastZero('-50.00'));
    }

    #[Test]
    public function formatting_prefixes_the_currency_only_when_asked(): void
    {
        $this->assertSame('155.25', Money::format('155.250'));
        $this->assertSame('SAR 155.25', Money::format('155.25', 'SAR'));
    }
}
