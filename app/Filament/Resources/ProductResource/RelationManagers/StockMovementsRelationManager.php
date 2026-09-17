<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Enums\StockMovementReason;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The stock ledger for one product, on its edit page (§7.2).
 *
 * Read-only for the same reason the Stock Movements resource is: the ledger is
 * what products.stock_qty is reconciled against. New rows arrive only through
 * StockService, via the "Adjust stock" action on this page.
 */
final class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Stock movements';

    protected static ?string $icon = 'heroicon-o-arrows-up-down';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delta')
                    ->label('Change')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementReason $state): string => $state->label())
                    ->color(fn (StockMovementReason $state): string => $state->color()),

                Tables\Columns\TextColumn::make('note')->limit(50)->color('gray')->placeholder('—'),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Source')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'Dashboard' : class_basename($state))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->options(fn (): array => collect(StockMovementReason::cases())
                        ->mapWithKeys(fn (StockMovementReason $r): array => [$r->value => $r->label()])
                        ->all()),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No movements recorded yet');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
