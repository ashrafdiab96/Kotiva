<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customers (§7.5): read-only, with order count and lifetime value.
 *
 * Read-only on purpose. These rows are created by checkout from what the
 * buyer typed; editing them here would silently disagree with the address and
 * phone snapshotted on their orders, and there is no second place that would
 * reveal the divergence.
 */
final class CustomerResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'email';

    protected static function requiredCapability(): string
    {
        return 'canViewCustomers';
    }

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
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Customer $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->copyable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->badge()
                    ->sortable(),

                // Revenue statuses only — a cancelled order is not value. The
                // status list comes from the enum rather than the scope
                // because Filament hands this closure an unparameterised
                // Builder, on which a model scope cannot be resolved.
                Tables\Columns\TextColumn::make('orders_sum_grand_total')
                    ->label('Lifetime value')
                    ->sum([
                        'orders' => fn (Builder $query): Builder => $query->whereIn('status', OrderStatus::revenueStatuses()),
                    ], 'grand_total')
                    ->money('SAR')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('marketing_opt_in')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('First seen')
                    ->dateTime('j M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('marketing_opt_in')->label('Marketing opt-in'),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No customers yet');
    }

    /**
     * @return Builder<Customer>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('orders');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
        ];
    }
}
