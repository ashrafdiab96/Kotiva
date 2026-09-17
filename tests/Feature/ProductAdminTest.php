<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\StockMovementReason;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The products dashboard (§7.2).
 *
 * The page-load tests matter more than they look: a Filament form schema is
 * only built when Livewire mounts it, so a malformed field is invisible until
 * something renders the page, and nothing else in the suite does.
 *
 * The stock tests exist because stock_qty has exactly one legal mutator. If the
 * dashboard can move that column without writing a ledger row, the ledger stops
 * being a reconciliation and becomes decoration.
 */
final class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * Created empty then stocked through the service, matching the rest of the
     * suite — so the fixture itself obeys the single-mutator rule.
     */
    private function product(int $stock = 10): Product
    {
        $product = Product::factory()->withStock(0)->create();
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    #[Test]
    public function the_list_create_and_edit_pages_all_render(): void
    {
        $this->actingAsAdmin();
        $product = $this->product();

        $this->assertSame(200, $this->get(ProductResource::getUrl('index'))->status(), 'list page');
        $this->assertSame(200, $this->get(ProductResource::getUrl('create'))->status(), 'create page');
        $this->assertSame(
            200,
            $this->get(ProductResource::getUrl('edit', ['record' => $product]))->status(),
            'edit page'
        );
    }

    #[Test]
    public function adjusting_stock_writes_both_the_quantity_and_a_ledger_row(): void
    {
        $admin = $this->actingAsAdmin();
        $product = $this->product(10);
        $movementsBefore = $product->stockMovements()->count();

        // Bound by slug: Product::getRouteKeyName() is 'slug', so the id is
        // not what Filament resolves the record with.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('adjustStock', [
                'delta' => 7,
                'reason' => StockMovementReason::Restock->value,
                'note' => 'Delivery from the lab',
            ]);

        $product->refresh();

        $this->assertSame(17, $product->stock_qty);
        $this->assertSame($movementsBefore + 1, $product->stockMovements()->count());

        $movement = $product->stockMovements()->latest('id')->firstOrFail();

        $this->assertSame(7, $movement->delta);
        $this->assertSame(StockMovementReason::Restock, $movement->reason);
        $this->assertSame('Delivery from the lab', $movement->note);
        // Attribution is the entire point of an audit trail, and it is written
        // from the panel guard rather than the default one.
        $this->assertSame($admin->getKey(), $movement->admin_id);
    }

    #[Test]
    public function stock_cannot_be_driven_negative_from_the_dashboard(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(10);
        $movementsBefore = $product->stockMovements()->count();

        // Bound by slug: Product::getRouteKeyName() is 'slug', so the id is
        // not what Filament resolves the record with.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('adjustStock', [
                'delta' => -60,
                'reason' => StockMovementReason::Manual->value,
            ]);

        $product->refresh();

        // Refused, with no partial write and no orphan ledger row.
        $this->assertSame(10, $product->stock_qty);
        $this->assertSame($movementsBefore, $product->stockMovements()->count());
    }

    #[Test]
    public function saving_the_edit_form_never_changes_the_quantity(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(10);

        // Bound by slug: Product::getRouteKeyName() is 'slug', so the id is
        // not what Filament resolves the record with.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            // A plain rename, which is the everyday edit.
            ->fillForm(['name' => 'Renamed By Test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame('Renamed By Test', $product->name);
        $this->assertSame(10, $product->stock_qty, 'stock_qty must not be writable through the product form');
        $this->assertSame(1, $product->stockMovements()->count(), 'a rename must not write a stock movement');
    }

    #[Test]
    public function creating_a_product_records_its_opening_stock_as_a_movement(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Serum',
                'sku' => 'KOT-TEST-1',
                'slug' => 'test-serum',
                'category_id' => $category->getKey(),
                'price' => '120.00',
                'initial_stock' => 12,
                'low_stock_threshold' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'KOT-TEST-1')->firstOrFail();

        $this->assertSame(12, $product->stock_qty);

        $movement = $product->stockMovements()->firstOrFail();
        $this->assertSame(12, $movement->delta);
        $this->assertSame('Opening stock', $movement->note);
    }

    #[Test]
    public function the_filter_tag_options_come_from_the_curated_taxonomy(): void
    {
        // The shop's pills are curated, not derived — "all" is a pseudo-filter
        // and must never be offerable as a product tag.
        $this->actingAsAdmin();

        $options = ProductResource::getFilterTagOptions();

        $this->assertArrayNotHasKey('all', $options);
        $this->assertArrayHasKey('spf', $options);
        $this->assertSame('SPF', $options['spf']);
    }
}
