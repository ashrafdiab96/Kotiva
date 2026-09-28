<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\StockMovementReason;
use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ProductStockMovementResource\Pages;
use App\Models\ProductStockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The stock ledger (§7.2): read-only, filterable by product, reason and date.
 *
 * Deliberately has no create, edit or delete. The ledger is the authoritative
 * record that products.stock_qty is reconciled against — a hand-edited row
 * would break that reconciliation silently and with no way to tell afterwards.
 * Corrections are made by recording a NEW movement through the stock form on
 * the product page, which is what an append-only ledger means in practice.
 */
final class ProductStockMovementResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ProductStockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?string $modelLabel = 'stock movement';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        // Nothing is editable here; the resource exists to be read.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('delta')
                    ->label('Change')
                    // Signed and coloured, because "+12" and "-12" are the
                    // whole meaning of the row.
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementReason $state): string => $state->label())
                    ->color(fn (StockMovementReason $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('note')
                    ->limit(40)
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Source')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('reason')
                    ->options(fn (): array => collect(StockMovementReason::cases())
                        ->mapWithKeys(fn (StockMovementReason $r): array => [$r->value => $r->label()])
                        ->all()),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('created_at', '<=', $d))),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No stock movements yet');
    }

    /**
     * @return Builder<ProductStockMovement>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('product');
    }

    public static function getPages(): array
    {
        // Index only — see the class docblock.
        return [
            'index' => Pages\ListProductStockMovements::route('/'),
        ];
    }
}
