<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ShippingCityResource\Pages;
use App\Models\ShippingCity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deliverable cities (§7.3).
 *
 * The city a shopper picks decides the zone, and the zone decides the fee, so
 * an inactive or missing city is the difference between an order and an
 * abandoned checkout. Bulk CSV import lives on the list page.
 */
final class ShippingCityResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ShippingCity::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name_en';

    protected static ?string $modelLabel = 'city';

    protected static ?string $pluralModelLabel = 'cities';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('zone_id')
                ->label('Zone')
                ->relationship('zone', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Determines the delivery fee and estimate.'),

            Forms\Components\TextInput::make('name_en')
                ->label('Name (English)')
                ->required()
                ->maxLength(255)
                // Matches the table's unique(zone_id, name_en).
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where('zone_id', $get('zone_id')))
                ->helperText('Must be unique within its zone.'),

            Forms\Components\TextInput::make('name_ar')
                ->label('Name (Arabic)')
                ->maxLength(255)
                ->helperText('Optional today; stored so the storefront can be translated without back-filling every city.'),

            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->helperText('Inactive cities disappear from the checkout list.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name_en')
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('Arabic')
                    ->searchable()
                    ->placeholder('—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zone')
                    ->relationship('zone', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => self::adminCan('canDeleteRecords')),
                ]),
            ])
            ->emptyStateHeading('No cities yet');
    }

    /**
     * @return Builder<ShippingCity>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('zone');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingCities::route('/'),
            'create' => Pages\CreateShippingCity::route('/create'),
            'edit' => Pages\EditShippingCity::route('/{record}/edit'),
        ];
    }
}
