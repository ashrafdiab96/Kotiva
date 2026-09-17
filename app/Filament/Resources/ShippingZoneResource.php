<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Filament\Resources\ShippingZoneResource\RelationManagers\RatesRelationManager;
use App\Models\ShippingZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Delivery zones (§7.3).
 *
 * Rates live on the zone's own page as a relation manager, because a rate has
 * no meaning apart from its zone and managing them as a separate top-level
 * resource would invite creating one that belongs to nothing.
 */
final class ShippingZoneResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ShippingZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'zone';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Shown to the shopper on the checkout shipping step.'),

            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first. Drag rows in the table to reorder.'),

            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->helperText('An inactive zone cannot be quoted, so its cities disappear from checkout.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('cities_count')
                    ->label('Cities')
                    ->counts('cities')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('activeRate.fee')
                    ->label('Fee')
                    ->money(config('kotiva.currency.code'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('activeRate.free_shipping_threshold')
                    ->label('Free over')
                    ->money(config('kotiva.currency.code'))
                    ->placeholder('Never'),

                // The failure this exists to surface: an active zone with no
                // active rate cannot be quoted at all, and ShippingQuote treats
                // that as "unavailable" — which is emphatically not free
                // delivery. Without this column the zone simply looks fine.
                Tables\Columns\IconColumn::make('quotable')
                    ->label('Quotable')
                    ->getStateUsing(fn (ShippingZone $record): bool => $record->activeRate !== null)
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-s-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (ShippingZone $record): ?string => $record->activeRate !== null
                        ? null
                        : 'No active rate — checkout cannot quote this zone.'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => self::adminCan('canDeleteRecords')),
                ]),
            ])
            ->emptyStateHeading('No delivery zones yet');
    }

    public static function getRelations(): array
    {
        return [
            RatesRelationManager::class,
        ];
    }

    /**
     * @return Builder<ShippingZone>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('activeRate')->withCount('cities');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit' => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}
