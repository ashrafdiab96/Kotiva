<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Filament\Widgets\Concerns\GatedWidget;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Products at or below their low-stock threshold (§7.1).
 *
 * Sold-out products are included and sorted first. They are the most urgent
 * case, and a "low stock" list that quietly drops everything already at zero
 * would be reassuring precisely when it should not be.
 */
final class LowStockProducts extends TableWidget
{
    use GatedWidget;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected static function requiredCapability(): string
    {
        return 'canManageCatalog';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock')
            ->description('At or below the per-product threshold.')
            ->query(self::query())
            ->defaultSort('stock_qty')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->color('gray'),

                Tables\Columns\TextColumn::make('name')
                    ->limit(32)
                    ->weight('bold')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Left')
                    ->badge()
                    ->color(fn (Product $record): string => $record->isSoldOut() ? 'danger' : 'warning')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Sold out' : (string) $state),

                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Threshold')
                    ->color('gray')
                    ->toggleable(),
            ])
            ->emptyStateHeading('Nothing is running low');
    }

    /**
     * @return Builder<Product>
     */
    public static function query(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold');
    }
}
