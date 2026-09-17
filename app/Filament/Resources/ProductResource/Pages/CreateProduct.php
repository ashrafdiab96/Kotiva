<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\StockMovementReason;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Opening stock, captured from the form and applied after the product
     * exists.
     */
    private int $openingStock = 0;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->openingStock = max(0, (int) ($this->data['initial_stock'] ?? 0));

        // Always created at zero: the opening quantity arrives as a ledger
        // movement below, so the column and the ledger agree from the very
        // first row rather than from the first adjustment.
        $data['stock_qty'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->openingStock === 0) {
            return;
        }

        /** @var Product $product */
        $product = $this->record;

        app(StockService::class)->adjust(
            $product,
            $this->openingStock,
            StockMovementReason::Manual,
            'Opening stock',
            // The panel's own guard, not auth(): admins authenticate on the
            // `admin` guard, so auth()->id() reads the empty web guard and
            // would record every movement with no one attached to it.
            Filament::auth()->id(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
