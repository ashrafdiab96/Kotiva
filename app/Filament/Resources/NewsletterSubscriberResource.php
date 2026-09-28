<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Newsletter list (§7.7): read-only.
 *
 * Consent is the record here. An admin adding an address by hand would be
 * recording a subscription that never happened, so the only write offered is
 * unsubscribing — and that is a soft timestamp, not a delete, because proof of
 * when someone opted out is exactly what you need to keep.
 */
final class NewsletterSubscriberResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'subscriber';

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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('subscribed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable()->weight('bold'),

                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime('j M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'Subscribed' : 'Unsubscribed')
                    ->color(fn (?string $state): string => $state === null ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('subscribed')
                    ->label('Currently subscribed')
                    ->query(fn (Builder $query): Builder => $query->whereNull('unsubscribed_at'))
                    ->default(),

                Tables\Filters\SelectFilter::make('source')
                    ->options(fn (): array => NewsletterSubscriber::query()
                        ->whereNotNull('source')
                        ->distinct()
                        ->pluck('source', 'source')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('unsubscribe')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The address stays on the list with an unsubscribed date, which is the record of consent being withdrawn.')
                    ->visible(fn (NewsletterSubscriber $record): bool => $record->unsubscribed_at === null
                        && self::adminCan('canViewCustomers'))
                    ->action(fn (NewsletterSubscriber $record) => $record->update(['unsubscribed_at' => now()])),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No subscribers yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
        ];
    }
}
