<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Contact form submissions (§7.7).
 *
 * These rows are written by the public contact form, so the dashboard reads
 * and triages them rather than authoring them — there is no such thing as an
 * admin-written enquiry. "Handled" is the only field an admin actually owns.
 */
final class ContactMessageResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'contact message';

    protected static function requiredCapability(): string
    {
        return 'canViewCustomers';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * The unhandled count is the point of the nav entry — a badge of 0 is
     * quieter than a badge showing every message ever received.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = self::getModel()::query()->where('is_handled', false)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        // Read-only: shown in the view modal, never used to author a message.
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('email')->disabled(),
            Forms\Components\TextInput::make('enquiry_type')->label('Enquiry type')->disabled(),
            Forms\Components\Textarea::make('message')->rows(8)->columnSpanFull()->disabled(),
            Forms\Components\Placeholder::make('received')
                ->content(fn (?ContactMessage $record): string => $record?->created_at?->format('j M Y, H:i') ?? '—'),
            Forms\Components\TextInput::make('ip_address')->label('IP address')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),

                Tables\Columns\TextColumn::make('enquiry_type')
                    ->label('Type')
                    ->badge()
                    ->placeholder('General')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->limit(60)
                    ->tooltip(fn (ContactMessage $record): string => (string) $record->message)
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_handled')
                    ->label('Handled')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_handled')
                    ->label('Handled')
                    ->placeholder('All messages')
                    ->trueLabel('Handled only')
                    ->falseLabel('Awaiting reply'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('toggleHandled')
                    ->label(fn (ContactMessage $record): string => $record->is_handled ? 'Mark unhandled' : 'Mark handled')
                    ->icon(fn (ContactMessage $record): string => $record->is_handled ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check')
                    ->color(fn (ContactMessage $record): string => $record->is_handled ? 'gray' : 'success')
                    ->visible(fn (): bool => self::adminCan('canViewCustomers'))
                    ->action(function (ContactMessage $record): void {
                        $nowHandled = ! $record->is_handled;

                        $record->update([
                            'is_handled' => $nowHandled,
                            // Cleared on un-handling so the timestamp never
                            // outlives the flag it describes.
                            'handled_at' => $nowHandled ? now() : null,
                        ]);
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No contact messages');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
