<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingZoneResource\RelationManagers;

use App\Models\Admin;
use App\Models\ShippingRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A zone's shipping rates, on the zone page (§7.3).
 *
 * Rates are kept as history rather than edited in place: the table has no
 * unique constraint on zone_id, and a quote reads activeRate(), the newest
 * active row. Saving a rate as active therefore retires the previous one —
 * see ShippingRate::activate().
 *
 * Changing a rate never alters an existing order. Orders snapshot
 * `shipping_fee` at placement in CheckoutService and nothing reads a rate back
 * afterwards, which is asserted in ShippingAdminTest rather than left to this
 * comment.
 */
final class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Rates';

    protected static ?string $icon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'fee';

    public function form(Form $form): Form
    {
        $currency = (string) config('kotiva.currency.code');

        return $form->schema([
            Forms\Components\TextInput::make('fee')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix($currency)
                ->helperText('Charged when the order is below the free-shipping threshold.'),

            Forms\Components\TextInput::make('free_shipping_threshold')
                ->label('Free shipping over')
                ->numeric()
                ->minValue(0)
                ->prefix($currency)
                // Null is meaningful here, and different from 0.
                ->helperText('Leave blank if this zone never ships free. An order exactly on the threshold ships free.'),

            Forms\Components\TextInput::make('estimated_days_min')
                ->label('Delivery, from')
                ->required()
                ->numeric()
                ->minValue(0)
                ->default(2)
                ->suffix('working days'),

            Forms\Components\TextInput::make('estimated_days_max')
                ->label('Delivery, to')
                ->required()
                ->numeric()
                ->minValue(0)
                ->default(4)
                ->suffix('working days')
                ->gte('estimated_days_min'),

            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->helperText('A zone quotes from one active rate. Activating this one retires the others; the old rows stay as history.'),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = (string) config('kotiva.currency.code');

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('In use')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->tooltip(fn (ShippingRate $record): string => $record->is_active
                        ? 'Quotes use this rate.'
                        : 'Retired — kept as history.'),

                Tables\Columns\TextColumn::make('fee')
                    ->money($currency)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('free_shipping_threshold')
                    ->label('Free over')
                    ->money($currency)
                    ->placeholder('Never free'),

                Tables\Columns\TextColumn::make('estimate')
                    ->label('Delivery')
                    ->getStateUsing(fn (ShippingRate $record): string => $record->estimateLabel()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Set')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New rate')
                    ->after(function (ShippingRate $record): void {
                        if ($record->is_active) {
                            $record->activate();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (ShippingRate $record): void {
                        if ($record->is_active) {
                            $record->activate();
                        }
                    }),

                Tables\Actions\Action::make('activate')
                    ->label('Use this rate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Quotes will use this rate from now on. The current one is retired but kept as history. Orders already placed keep the fee they were charged.')
                    ->visible(fn (ShippingRate $record): bool => ! $record->is_active)
                    ->action(function (ShippingRate $record): void {
                        $record->activate();

                        Notification::make()
                            ->success()
                            ->title('Rate in use')
                            ->body('New quotes for this zone charge '.config('kotiva.currency.code').' '.$record->fee.'.')
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => self::currentAdminCanDelete()),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No rate set')
            ->emptyStateDescription('Checkout cannot quote this zone until a rate is active.');
    }

    private static function currentAdminCanDelete(): bool
    {
        $user = auth()->user();

        return $user instanceof Admin && $user->canDeleteRecords();
    }
}
