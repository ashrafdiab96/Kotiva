<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ReferralPartnerResource\Pages;
use App\Models\ReferralPartner;
use App\Support\Referral;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Doctor referral partners (D-38).
 *
 * Each row maps a /dr/<code> share link onto the affiliate link Salla
 * generated for that doctor. The dashboard counts visits only; orders and
 * commission are in Salla's Affiliate marketing dashboard.
 *
 * Soft delete only: a visit history is the record of what a doctor's link
 * did, and a hard delete would cascade it away. ip_hash and user_agent are
 * never shown here.
 */
final class ReferralPartnerResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = ReferralPartner::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'referral partner';

    protected static ?string $navigationLabel = 'Doctor referrals';

    /**
     * The site's public pages a partner can land on. "/shop" hands off to the
     * Salla listing, carrying the referral.
     *
     * @var array<string, string>
     */
    public const LANDING_PATHS = [
        '/' => 'Home',
        '/shop' => 'Shop (Salla handoff)',
        '/routine-finder' => 'Routine Finder',
        '/science' => 'Science',
        '/about' => 'About',
    ];

    protected static function requiredCapability(): string
    {
        return 'canManageCatalog';
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    /**
     * Visit counts as subqueries on the list query, not one query per row.
     *
     * @return Builder<ReferralPartner>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReferralPartner> $query */
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        return self::withVisitStats($query);
    }

    /**
     * @param  Builder<ReferralPartner>  $query
     * @return Builder<ReferralPartner>
     */
    public static function withVisitStats(Builder $query): Builder
    {
        return $query
            ->withCount([
                'visits as visits_total',
                'visits as visits_unique_30d' => fn (Builder $q): Builder => $q
                    ->where('is_unique_day', true)
                    ->where('visited_at', '>=', now()->subDays(30)),
            ])
            ->withMax('visits as last_visit_at', 'visited_at');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    // Suggest a code from the name on create, until the code
                    // has been typed by hand.
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                        if ($operation !== 'create' || filled($get('code'))) {
                            return;
                        }

                        $set('code', Str::limit(Str::slug((string) $state), 32, ''));
                    }),

                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->helperText('The share link is /dr/<code>. Lowercase letters, digits and hyphens, 3–32 characters.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(32)
                    ->regex(ReferralPartner::CODE_PATTERN)
                    ->validationMessages(['regex' => 'Use lowercase letters, digits and single hyphens, e.g. dr-ahmed.'])
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state))),

                Forms\Components\TextInput::make('salla_affiliate_url')
                    ->label('Salla affiliate link')
                    ->helperText('Paste the full link from Salla → Marketing → Affiliate marketing. Only its query string is used.')
                    ->required()
                    ->maxLength(2048)
                    ->url()
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            try {
                                Referral::queryOf((string) $value);
                            } catch (InvalidArgumentException $e) {
                                $fail($e->getMessage());
                            }
                        },
                    ])
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('landing_path')
                    ->label('Landing page')
                    ->helperText('Where /dr/<code> sends the patient. Pick one or type a path on this site, e.g. /science.')
                    ->required()
                    ->default('/')
                    ->datalist(array_keys(self::LANDING_PATHS))
                    ->maxLength(255)
                    ->regex(ReferralPartner::LANDING_PATH_PATTERN)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! ReferralPartner::isValidLandingPath($value)) {
                                $fail('Use a path on this site that starts with a single "/", e.g. /science.');
                            }
                        },
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('An inactive code sends visitors to the home page and sets no cookie.')
                    ->default(true)
                    ->inline(false),

                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),

                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('share_link')
                    ->label('Share link')
                    ->state(fn (ReferralPartner $record): string => $record->shareUrl())
                    ->copyable()
                    ->copyMessage('Share link copied'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (ReferralPartner $record): bool => $record->trashed()),

                Tables\Columns\TextColumn::make('visits_total')
                    ->label('Visits')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visits_unique_30d')
                    ->label('Unique, 30 days')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label('Last visit')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Their links will send visitors to the home page and set no cookie. Visits already recorded are kept.')
                    ->action(fn (Collection $records) => ReferralPartner::query()
                        ->whereKey($records->modelKeys())
                        ->update(['is_active' => false]))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('No referral partners yet')
            ->emptyStateDescription('Create the affiliate in Salla first, then add the doctor here with the link Salla gives you.');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('share_link')
                    ->label('Share link')
                    ->state(fn (ReferralPartner $record): string => $record->shareUrl())
                    ->copyable(),
                Infolists\Components\IconEntry::make('is_active')->label('Active')->boolean(),
                Infolists\Components\TextEntry::make('salla_affiliate_url')->label('Salla affiliate link')->columnSpan(2),
                Infolists\Components\TextEntry::make('landing_path')->label('Landing page'),
                Infolists\Components\TextEntry::make('visits_total')->label('Visits')->numeric(),
                Infolists\Components\TextEntry::make('visits_unique_30d')->label('Unique visits, 30 days')->numeric(),
                Infolists\Components\TextEntry::make('last_visit_at')->label('Last visit')->dateTime('j M Y, H:i')->placeholder('Never'),
                Infolists\Components\TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function getWidgets(): array
    {
        return [
            ReferralPartnerResource\Widgets\PartnerVisitsChart::class,
            ReferralPartnerResource\Widgets\PartnerRecentVisits::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferralPartners::route('/'),
            'create' => Pages\CreateReferralPartner::route('/create'),
            'view' => Pages\ViewReferralPartner::route('/{record}'),
            'edit' => Pages\EditReferralPartner::route('/{record}/edit'),
        ];
    }
}
