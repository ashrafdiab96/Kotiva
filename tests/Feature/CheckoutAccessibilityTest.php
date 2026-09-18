<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementReason;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\StockService;
use DOMDocument;
use DOMXPath;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Checkout errors are heard, not only seen (Phase 6 accessibility pass).
 *
 * The audit found 14 visible error messages after a failed shipping submit and
 * none of them reachable by a screen reader: no aria-invalid, no
 * aria-describedby, no announcement. A shopper using one would submit a bad
 * phone number and hear nothing at all.
 */
final class CheckoutAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private ?Cart $cart = null;

    /**
     * @return array<string, string>
     */
    private function cookies(): array
    {
        if (! $this->cart instanceof Cart) {
            return [];
        }

        $name = (string) config('kotiva.cart.cookie');

        return [$name => encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$this->cart->token, false)];
    }

    private function withItemInCart(): void
    {
        $product = Product::factory()->withStock(0)->create(['price' => '100.00']);
        app(StockService::class)->adjust($product, 5, StockMovementReason::Restock, 'Opening stock');

        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create();
        ShippingCity::factory()->for($zone, 'zone')->create(['name_en' => 'Riyadh']);

        $this->call('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();
        $this->cart = Cart::query()->latest('id')->firstOrFail();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    #[Test]
    public function a_failed_shipping_submit_announces_itself_and_marks_each_field(): void
    {
        $this->withItemInCart();

        $this->call('GET', '/checkout/shipping', cookies: $this->cookies())->assertOk();

        $response = $this->call('POST', '/checkout/shipping', [
            'first_name' => '',
            'email' => 'not-an-email',
            'phone' => '123',
        ], $this->cookies());

        $html = (string) $this->call('GET', (string) $response->headers->get('Location'), cookies: $this->cookies())->getContent();
        $xp = $this->xpath($html);

        $this->assertGreaterThan(0, $xp->query('//*[@role="alert"]')->length, 'the failure must be announced');

        foreach (['first_name', 'email', 'phone'] as $field) {
            $input = $xp->query('//*[@name="'.$field.'"]')->item(0);
            $this->assertInstanceOf(\DOMElement::class, $input, $field.' not rendered');

            $this->assertSame('true', $input->getAttribute('aria-invalid'), $field.' must be marked invalid');

            $describedBy = $input->getAttribute('aria-describedby');
            $message = $xp->query('//*[@id="'.$describedBy.'"]')->item(0);

            $this->assertNotNull($message, $field.' must point at its error message');
            $this->assertNotSame('', trim((string) $message->textContent), $field.' error message is empty');
        }
    }

    #[Test]
    public function a_clean_form_carries_no_error_markers(): void
    {
        $this->withItemInCart();

        $html = (string) $this->call('GET', '/checkout/shipping', cookies: $this->cookies())->getContent();
        $xp = $this->xpath($html);

        // Marked invalid before anything was submitted would be read out as an
        // error on every field.
        $this->assertSame(0, $xp->query('//*[@aria-invalid]')->length);
        $this->assertSame(0, $xp->query('//form//*[@role="alert"]')->length);
    }
}
