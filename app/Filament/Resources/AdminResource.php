<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\AdminRole;
use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Dashboard accounts (§7.6). Super admin only.
 */
final class AdminResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = Admin::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function requiredCapability(): string
    {
        return 'canManageAdmins';
    }

    /**
     * An admin must not delete their own account: doing so logs them out
     * mid-session and, if they are the last super admin, locks everyone out of
     * the dashboard permanently.
     */
    public static function canDelete(Model $record): bool
    {
        $current = self::currentAdmin();

        if ($current instanceof Admin && $current->is($record)) {
            return false;
        }

        return self::adminCan('canManageAdmins');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Forms\Components\Select::make('role')
                ->options(AdminRole::options())
                ->default(AdminRole::Staff->value)
                ->required()
                ->native(false)
                ->helperText(fn (?string $state): string => $state !== null && ($role = AdminRole::tryFrom($state)) !== null
                    ? $role->description()
                    : 'Choose what this account may do.')
                ->live(),

            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                // Leaving it blank on edit keeps the existing password rather
                // than blanking it.
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(12)
                ->helperText('At least 12 characters. Leave blank when editing to keep the current password.'),

            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->helperText('Deactivating locks the account out immediately without deleting its history.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (AdminRole $state): string => $state->label())
                    ->color(fn (AdminRole $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean()->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(AdminRole::options()),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No admin accounts');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
