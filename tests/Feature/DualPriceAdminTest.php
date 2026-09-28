<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Vat;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboard's half of dual pricing.
 *
 * The governing rule is that the application never decides which of two
 * client-supplied prices is the mistaken one. It refuses the combinations that
 * are certainly wrong, reports the ones that merely look wrong, and writes both
 * figures exactly as typed either way.
 */
final class DualPriceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => AdminRole::SuperAdmin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        // Filament resolves the resource's panel from the current one; without
        // this the form under test has no panel to boot against.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Test Serum',
            'sku' => 'KOT-DUAL-1',
            'slug' => 'test-serum-dual',
            'category_id' => Category::factory()->create()->getKey(),
            'price_excl_vat' => '135.00',
            'price_incl_vat' => '155.25',
            'low_stock_threshold' => 3,
        ];
    }

    #[Test]
    public function both_prices_are_required(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->formData(['price_excl_vat' => null, 'price_incl_vat' => null]))
            ->call('create')
            ->assertHasFormErrors(['price_excl_vat', 'price_incl_vat']);
    }

    #[Test]
    public function a_negative_price_is_refused(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->formData(['price_excl_vat' => '-1.00']))
            ->call('create')
            ->assertHasFormErrors(['price_excl_vat']);
    }

    #[Test]
    public function an_inclusive_price_below_the_exclusive_one_is_refused(): void
    {
        /*
         | The one hard rule. Two prices may legitimately disagree with the VAT
         | rate — a brochure is allowed to round to a round number — but an
         | inclusive price BELOW the exclusive one is not a pricing decision,
         | it is a transposition, and it would give the order a negative VAT.
         */
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->formData(['price_excl_vat' => '155.25', 'price_incl_vat' => '135.00']))
            ->call('create')
            ->assertHasFormErrors(['price_incl_vat']);

        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function two_prices_that_disagree_with_the_rate_are_saved_unchanged(): void
    {
        /*
         | Both figures come off a signed-off brochure, so the form has no
         | standing to pick one and overwrite the other. It saves what was
         | typed — this assertion is the whole "do not silently replace an
         | explicitly supplied brochure price" requirement.
         */
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm($this->formData(['price_excl_vat' => '100.00', 'price_incl_vat' => '120.00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'KOT-DUAL-1')->firstOrFail();

        $this->assertSame('100.00', $product->priceExclVat(), 'not rewritten to 104.35');
        $this->assertSame('120.00', $product->priceInclVat(), 'not rewritten to 115.00');
        $this->assertFalse($product->pricesAgreeWithVatRate());
        $this->assertSame('5.00', $product->vatDiscrepancy());
    }

    #[Test]
    public function editing_one_price_never_recalculates_the_other(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);

        // Bound by slug, not id: Product::getRouteKeyName() is 'slug'.
        Livewire::test(EditProduct::class, ['record' => $product->slug])
            ->fillForm(['price_excl_vat' => '140.00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame('140.00', $product->priceExclVat());
        $this->assertSame('155.25', $product->priceInclVat(), 'the untouched field stays exactly as it was');
    }

    #[Test]
    public function the_form_hints_the_counterpart_only_when_the_prices_disagree(): void
    {
        // Quiet in the normal case, so the warning means something when it
        // appears.
        $agreeing = new FakeGet(['price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);
        $this->assertNull(ProductResource::priceHint($agreeing, 'incl'));
        $this->assertNull(ProductResource::priceHint($agreeing, 'excl'));

        $disagreeing = new FakeGet(['price_excl_vat' => '100.00', 'price_incl_vat' => '120.00']);
        $this->assertSame('15% VAT on SAR 100.00 would be SAR 115.00', ProductResource::priceHint($disagreeing, 'incl'));
        $this->assertSame('SAR 120.00 implies SAR 104.35 before 15% VAT', ProductResource::priceHint($disagreeing, 'excl'));

        // Nothing to compare yet while a field is still empty.
        $partial = new FakeGet(['price_excl_vat' => '100.00', 'price_incl_vat' => null]);
        $this->assertNull(ProductResource::priceHint($partial, 'incl'));
    }

    #[Test]
    public function the_product_table_flags_a_mismatched_row(): void
    {
        $this->actingAsAdmin();

        $consistent = Product::factory()->create(['price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);
        $mismatched = Product::factory()->create(['price_excl_vat' => '100.00', 'price_incl_vat' => '120.00']);

        $this->assertTrue($consistent->pricesAgreeWithVatRate());
        $this->assertFalse($mismatched->pricesAgreeWithVatRate());

        /*
         | The table filter, which is the list an admin works through after a
         | rate change.
         |
         | Asserted against the database rather than the rendered table because
         | the first version of this scope bound its numbers as parameters —
         | which SQLite binds as TEXT, making the comparison always false and
         | the filter silently empty. It passed every eyeball test.
         */
        $flagged = Product::query()->vatMismatched()->pluck('sku')->all();

        $this->assertSame([$mismatched->sku], $flagged);
    }

    #[Test]
    public function the_filter_and_the_row_indicator_agree_on_every_product(): void
    {
        /*
         | The table's warning icon asks the model; the filter asks the
         | database. They must give the same answer or an admin sees a row
         | flagged as broken that the filter will not show them, or worse.
         |
         | 100.01 x 1.15 is 115.0115 — 1.15 halalas from a stored 115.00, which
         | is inside tolerance once rounded to money scale and outside it raw.
         | That is exactly the boundary where the two implementations drifted
         | apart the first time.
         */
        Product::factory()->create(['sku' => 'KOT-ROUNDED', 'price_excl_vat' => '100.01', 'price_incl_vat' => '115.00']);
        Product::factory()->create(['sku' => 'KOT-EXACT', 'price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);
        Product::factory()->create(['sku' => 'KOT-WRONG', 'price_excl_vat' => '100.00', 'price_incl_vat' => '120.00']);

        $fromDatabase = Product::query()->vatMismatched()->pluck('sku')->sort()->values()->all();

        $fromModel = Product::query()->get()
            ->reject(fn (Product $p): bool => $p->pricesAgreeWithVatRate())
            ->pluck('sku')->sort()->values()->all();

        $this->assertSame(['KOT-WRONG'], $fromDatabase);
        $this->assertSame($fromDatabase, $fromModel);
    }

    #[Test]
    public function the_admin_order_view_shows_the_full_vat_breakdown(): void
    {
        /*
         | The admin view is where an order gets explained to a customer on the
         | phone, so "VAT" on its own is no longer enough — it has to say which
         | part sits on the goods and which on the delivery, at the rate the
         | order was actually placed at.
         |
         | The factory's order is 260.87 net + 39.13 VAT merchandise, 25.00
         | delivery containing 3.26 VAT, 325.00 payable.
         */
        $this->actingAsAdmin();

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->pricedAt('130.43', 2)->create();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('Merchandise excl. VAT')
            ->assertSee('VAT on goods')
            ->assertSee('VAT on shipping')
            ->assertSee('Rate applied: 15%')
            ->assertSee('Reconciles');

        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function a_vat_rate_change_flags_the_whole_catalog_without_repricing_it(): void
    {
        /*
         | Raising the rate does not raise any price. Every product keeps both
         | figures and is simply reported as no longer reconciling, which is the
         | only honest outcome: what the shop charges after a rate change is a
         | business decision, not an arithmetic one.
         */
        $product = Product::factory()->create(['price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);
        $this->assertTrue($product->pricesAgreeWithVatRate());

        Setting::put('vat_rate', 0.20);
        $product->refresh();

        $this->assertSame('135.00', $product->priceExclVat(), 'prices are untouched by a rate change');
        $this->assertSame('155.25', $product->priceInclVat());
        $this->assertFalse($product->pricesAgreeWithVatRate());
        $this->assertSame('-6.75', $product->vatDiscrepancy());
    }
}

/**
 * A stand-in for Filament\Forms\Get, so the hint logic can be tested directly
 * rather than by scraping rendered HTML. Get is an invokable that returns a
 * field's current state; this returns it from a fixed array.
 */
final class FakeGet extends Get
{
    /** @param array<string, mixed> $state */
    public function __construct(private readonly array $state)
    {
        parent::__construct(TextInput::make('stub'));
    }

    public function __invoke(Component|string $path = '', bool $isAbsolute = false): mixed
    {
        // The hint logic only ever asks for fields by name, so a Component
        // argument would mean the code under test had changed shape — worth
        // failing loudly rather than quietly returning null.
        if (! is_string($path)) {
            throw new \InvalidArgumentException('FakeGet only resolves field names.');
        }

        return $this->state[$path] ?? null;
    }
}
