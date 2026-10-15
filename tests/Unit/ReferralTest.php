<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ReferralPartner;
use App\Support\Referral;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Referral::applyTo() and affiliateQuery() (D-38).
 *
 * Needs the app only for config; the partner is never saved.
 */
final class ReferralTest extends TestCase
{
    private const STORE = 'https://store.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['kotiva.salla.store_url' => self::STORE]);
    }

    private function partner(string $affiliateUrl = self::STORE.'/?ref=ABC123'): ReferralPartner
    {
        return new ReferralPartner([
            'name' => 'Dr Test',
            'code' => 'dr-test',
            'salla_affiliate_url' => $affiliateUrl,
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urls(): array
    {
        return [
            'bare store, no path' => [self::STORE, self::STORE.'/?ref=ABC123'],
            'store root' => [self::STORE.'/', self::STORE.'/?ref=ABC123'],
            'product path' => [self::STORE.'/micellar-water/p1713901761', self::STORE.'/micellar-water/p1713901761?ref=ABC123'],
            'existing filter kept' => [self::STORE.'/?filter=cleanser', self::STORE.'/?filter=cleanser&ref=ABC123'],
            'routine commas stay literal' => [self::STORE.'/?filter=hair&routine=KOT001,KOT005', self::STORE.'/?filter=hair&routine=KOT001,KOT005&ref=ABC123'],
            'fragment kept last' => [self::STORE.'/cart#summary', self::STORE.'/cart?ref=ABC123#summary'],
            'query and fragment' => [self::STORE.'/?filter=spf#grid', self::STORE.'/?filter=spf&ref=ABC123#grid'],
            'affiliate key wins' => [self::STORE.'/?ref=OLD&filter=spf', self::STORE.'/?filter=spf&ref=ABC123'],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function apply_to_merges_the_affiliate_query(string $url, string $expected): void
    {
        $this->assertSame($expected, Referral::applyTo($url, $this->partner()));
    }

    #[Test]
    public function apply_to_carries_every_affiliate_parameter(): void
    {
        $partner = $this->partner(self::STORE.'/?aff=XY%2F9&utm_source=affiliate');

        $this->assertSame(
            self::STORE.'/?filter=body&aff=XY%2F9&utm_source=affiliate',
            Referral::applyTo(self::STORE.'/?filter=body', $partner),
        );
    }

    #[Test]
    public function apply_to_without_a_partner_is_a_no_op(): void
    {
        $this->assertSame(self::STORE.'/?filter=spf', Referral::applyTo(self::STORE.'/?filter=spf', null));
    }

    #[Test]
    public function apply_to_leaves_other_hosts_alone(): void
    {
        foreach ([
            'https://klyvo.example.test/science',
            'https://store.example.test.evil.test/',
            'http://store.example.test/',
            '/shop?filter=spf',
        ] as $url) {
            $this->assertSame($url, Referral::applyTo($url, $this->partner()), $url);
        }
    }

    #[Test]
    public function apply_to_returns_the_url_unchanged_if_the_partner_link_no_longer_validates(): void
    {
        $partner = $this->partner('https://old-store.example.test/?ref=ABC123');

        $this->assertSame(self::STORE.'/?filter=spf', Referral::applyTo(self::STORE.'/?filter=spf', $partner));
    }

    #[Test]
    public function affiliate_query_returns_only_the_query_parameters(): void
    {
        $this->assertSame(
            ['ref' => 'ABC123', 'src' => 'dr'],
            Referral::affiliateQuery($this->partner(self::STORE.'/some/path?ref=ABC123&src=dr#x')),
        );
    }

    #[Test]
    public function affiliate_query_rejects_another_host(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('store.example.test');

        Referral::affiliateQuery($this->partner('https://evil.example.test/?ref=ABC123'));
    }

    #[Test]
    public function affiliate_query_rejects_a_lookalike_host(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Referral::affiliateQuery($this->partner('https://store.example.test.evil.test/?ref=ABC123'));
    }

    #[Test]
    public function affiliate_query_rejects_a_link_without_a_query(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no query string');

        Referral::affiliateQuery($this->partner(self::STORE.'/'));
    }

    #[Test]
    public function the_cookie_name_is_kotiva_ref(): void
    {
        $this->assertSame('kotiva_ref', Referral::cookieName());
    }

    #[Test]
    public function landing_paths_must_stay_on_this_site(): void
    {
        foreach (['/', '/science', '/routine-finder', '/a-b/c'] as $ok) {
            $this->assertTrue(ReferralPartner::isValidLandingPath($ok), $ok);
        }

        foreach (['', 'science', '//evil', 'https://evil.test/', '/Science', '/a?b', '/a.b'] as $bad) {
            $this->assertFalse(ReferralPartner::isValidLandingPath($bad), $bad);
        }
    }
}
