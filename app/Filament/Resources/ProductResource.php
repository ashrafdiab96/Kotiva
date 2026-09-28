<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\StockMovementsRelationManager;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ProductImageService;
use App\Support\Money;
use App\Support\Vat;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;

/**
 * Products (§7.2).
 *
 * Two constraints shape this form and neither is obvious from the brief:
 *
 * 1. `description` and `science` are PLAIN TEXT, not rich text. The storefront
 *    escapes `description` and ScienceRenderer parses `science` line by line
 *    ("MOA:", "Ref:"), asserting no client sentence is lost. A rich editor here
 *    would print literal markup on the product page and break that assertion on
 *    all 25 approved science sections.
 *
 * 2. `stock_qty` is not writable here. StockService is its sole mutator, so
 *    that every change lands in the append-only ledger the quantity is
 *    reconciled against. Editing the number directly would desynchronise the
 *    two with nothing to reveal it afterwards.
 */
final class ProductResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'slug'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('product')
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('General')->schema(self::generalFields()),
                    Forms\Components\Tabs\Tab::make('Content')->schema(self::contentFields()),
                    Forms\Components\Tabs\Tab::make('Media')->schema(self::mediaFields()),
                    Forms\Components\Tabs\Tab::make('Inventory')->schema(self::inventoryFields()),
                    Forms\Components\Tabs\Tab::make('SEO')->schema(self::seoFields()),
                ]),
        ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function generalFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, Forms\Set $set): void {
                    // Only on create: a slug is a live product URL, so renaming
                    // an existing product must not silently move its page.
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            Forms\Components\TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Also the key used when importing by SKU.'),

            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Appears in the product URL. Changing it breaks existing links.'),

            Forms\Components\Select::make('category_id')
                ->label('Category')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first in the shop.'),

            /*
             | Both brochure prices, side by side and both required.
             |
             | Neither field recalculates the other. The client's two A2
             | brochures are both signed off, and a form that "helpfully"
             | overwrote a typed figure with excl × 1.15 would silently replace
             | an approved price with a computed one — the exact failure this
             | design exists to prevent. Instead each field carries a live hint
             | naming what the other implies at the configured rate, and a
             | disagreement is reported on save for a person to resolve.
             */
            Forms\Components\TextInput::make('price_excl_vat')
                ->label('Price excluding VAT')
                ->required()
                ->numeric()
                ->minValue(0)
                ->live(onBlur: true)
                ->prefix(config('kotiva.currency.code'))
                ->helperText('From the VAT-exclusive brochure. This is the price the shop, the home page and the product page display, and what price sorting uses.')
                ->hint(fn (Forms\Get $get): ?string => self::priceHint($get, 'excl'))
                ->hintColor('warning'),

            Forms\Components\TextInput::make('price_incl_vat')
                ->label('Price including VAT')
                ->required()
                ->numeric()
                ->minValue(0)
                ->live(onBlur: true)
                ->prefix(config('kotiva.currency.code'))
                ->helperText('From the VAT-inclusive brochure. This is what the customer is charged at checkout.')
                ->hint(fn (Forms\Get $get): ?string => self::priceHint($get, 'incl'))
                ->hintColor('warning')
                // The one hard rule. The two prices may legitimately disagree
                // with the configured rate — a brochure is allowed to round to
                // a round number — but an inclusive price BELOW the exclusive
                // one is not a pricing decision, it is a transposition, and it
                // would make the order's VAT negative.
                ->rule(static function (Forms\Get $get): Closure {
                    return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $exclusive = $get('price_excl_vat');

                        if (! is_numeric($value) || ! is_numeric($exclusive)) {
                            return;
                        }

                        if (Money::compare(Money::of($value), Money::of($exclusive)) < 0) {
                            $fail('The VAT-inclusive price cannot be lower than the VAT-exclusive price. Check the two figures have not been swapped.');
                        }
                    };
                }),

            Forms\Components\TextInput::make('compare_at_price')
                ->numeric()
                ->minValue(0)
                ->prefix(config('kotiva.currency.code'))
                ->helperText('Optional. Shown struck through when higher than the price.'),

            Forms\Components\TextInput::make('volume')->maxLength(255)->helperText('e.g. 200ml'),
            Forms\Components\TextInput::make('skin_type')->maxLength(255),
            Forms\Components\TextInput::make('concern')->maxLength(255),
            Forms\Components\TextInput::make('action')
                ->maxLength(255)
                ->helperText('The short claim line shown under the product name.'),

            Forms\Components\Fieldset::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('Inactive products leave the shop, the sitemap and the API.'),
                Forms\Components\Toggle::make('is_featured'),
                Forms\Components\Toggle::make('is_best_seller')
                    ->helperText('Shown on the home page rail.'),
            ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function contentFields(): array
    {
        return [
            Forms\Components\Textarea::make('description')
                ->rows(4)
                ->columnSpanFull()
                // Plain text, deliberately — see the class docblock.
                ->helperText('Plain text. The product page renders this as a single paragraph; HTML would be shown literally.'),

            Forms\Components\Repeater::make('benefits')
                ->simple(Forms\Components\TextInput::make('benefit')->required())
                // Repeaters default to one item; a new product has no benefits
                // yet, and an empty required row makes it unsaveable.
                ->defaultItems(0)
                ->columnSpanFull()
                ->addActionLabel('Add benefit'),

            Forms\Components\Textarea::make('how_to_use')->rows(3)->columnSpanFull(),

            Forms\Components\Repeater::make('ingredients')
                ->simple(Forms\Components\TextInput::make('ingredient')->required())
                ->defaultItems(0)
                ->columnSpanFull()
                ->addActionLabel('Add ingredient'),

            Forms\Components\Repeater::make('free_from')
                ->simple(Forms\Components\TextInput::make('free_from_item')->required())
                ->defaultItems(0)
                ->columnSpanFull()
                ->addActionLabel('Add "free from" claim'),

            Forms\Components\CheckboxList::make('filter_tags')
                ->label('Shop filters')
                // The shop's pills, plus any other tag this product already
                // carries. Seven launch products are tagged `cleanser`, which
                // has no pill; listing only the pills would leave that tag in
                // the data where the admin could neither see nor remove it.
                ->options(fn (?Product $record): array => self::getFilterTagOptions() + self::extraTagOptions($record))
                ->columns(3)
                ->columnSpanFull()
                ->helperText('Controls which shop filter pills this product appears under. Tags marked "no pill" are kept on the product but are not a shop filter.'),

            Forms\Components\Textarea::make('science')
                ->rows(12)
                ->columnSpanFull()
                ->helperText('Plain text only. Keep the "MOA:" and "Ref:" line prefixes — the product page builds the Science section from them, and it refuses to render if a sentence or citation would be lost.'),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function mediaFields(): array
    {
        return [
            self::keepCatalogArtwork(Forms\Components\FileUpload::make('image'))
                ->label('Main image')
                ->image()
                ->imagePreviewHeight('180')
                ->disk('public')
                ->directory('products')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(8192)
                // Server-side so the stored renditions never depend on which
                // browser the admin used.
                ->saveUploadedFileUsing(fn (UploadedFile $file): string => app(ProductImageService::class)->store($file))
                ->deleteUploadedFileUsing(fn (?string $file): null => tap(null, fn () => app(ProductImageService::class)->delete($file)))
                ->helperText('Stored as a 1200px WebP with a 600px thumbnail. The 25 launch products keep their existing committed artwork until replaced here.'),

            self::keepCatalogArtwork(Forms\Components\FileUpload::make('gallery'))
                ->multiple()
                ->image()
                ->reorderable()
                ->disk('public')
                ->directory('products')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(8192)
                ->saveUploadedFileUsing(fn (UploadedFile $file): string => app(ProductImageService::class)->store($file))
                ->deleteUploadedFileUsing(fn (?string $file): null => tap(null, fn () => app(ProductImageService::class)->delete($file)))
                ->columnSpanFull(),
        ];
    }

    /**
     * Teach a FileUpload about the committed launch artwork.
     *
     * The 25 launch products store `assets/products/...` — a file under
     * public/, not on the `public` disk. Filament's default hydration keeps
     * only paths that exist on the field's disk, so the field loaded EMPTY and
     * the next save of any other field wrote null over the image. These two
     * callbacks replace the defaults so an `assets/` path survives hydration
     * and previews from public/; disk paths behave exactly as before.
     */
    private static function keepCatalogArtwork(Forms\Components\FileUpload $upload): Forms\Components\FileUpload
    {
        return $upload
            ->afterStateHydrated(static function (Forms\Components\FileUpload $component, string|array|null $state): void {
                $files = collect(Arr::wrap($state))
                    ->filter(static function ($file) use ($component): bool {
                        if (! is_string($file) || $file === '') {
                            return false;
                        }

                        // Never drop committed artwork, even if the file is
                        // missing — silently losing the stored path is the bug.
                        if (str_starts_with($file, 'assets/')) {
                            return true;
                        }

                        try {
                            return $component->getDisk()->exists($file);
                        } catch (UnableToCheckFileExistence) {
                            return false;
                        }
                    })
                    ->mapWithKeys(static fn (string $file): array => [(string) Str::uuid() => $file])
                    ->all();

                $component->state($files);
            })
            ->getUploadedFileUsing(static function (Forms\Components\FileUpload $component, string $file): ?array {
                if (str_starts_with($file, 'assets/')) {
                    $path = public_path($file);

                    return is_file($path) ? [
                        'name' => basename($file),
                        'size' => (int) filesize($path),
                        'type' => mime_content_type($path) ?: null,
                        'url' => asset($file),
                    ] : null;
                }

                $storage = $component->getDisk();

                try {
                    if (! $storage->exists($file)) {
                        return null;
                    }
                } catch (UnableToCheckFileExistence) {
                    return null;
                }

                return [
                    'name' => basename($file),
                    'size' => $storage->size($file),
                    'type' => $storage->mimeType($file),
                    'url' => $storage->url($file),
                ];
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function inventoryFields(): array
    {
        return [
            // Create only: recorded as a ledger movement after the product
            // exists, never written straight onto the column.
            Forms\Components\TextInput::make('initial_stock')
                ->label('Opening stock')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->dehydrated(false)
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->helperText('Recorded as the first stock movement for this product.'),

            Forms\Components\TextInput::make('stock_qty')
                ->label('Current stock')
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->helperText('Changed only through "Adjust stock", so every movement is recorded in the ledger.'),

            Forms\Components\TextInput::make('low_stock_threshold')
                ->numeric()
                ->minValue(0)
                // The dashboard default, so changing it in Settings governs the
                // next product rather than only the config file.
                ->default((int) Setting::get('low_stock_threshold', config('kotiva.stock.low_stock_threshold')))
                ->helperText('At or below this, the shop shows "Only N left" and an alert is raised.'),

            Forms\Components\TextInput::make('weight_grams')
                ->numeric()
                ->minValue(0)
                ->suffix('g'),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function seoFields(): array
    {
        return [
            Forms\Components\TextInput::make('meta_title')
                ->maxLength(255)
                ->columnSpanFull()
                ->helperText('Falls back to the product name when empty.'),

            Forms\Components\Textarea::make('meta_description')
                ->maxLength(512)
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * The live warning shown beside a price field when the two disagree at the
     * configured VAT rate.
     *
     * Returns null while they agree, so the form is quiet in the normal case
     * and the hint means something when it appears. It states the counterpart
     * the rate implies and leaves the decision to the admin — nothing here
     * writes a value.
     */
    public static function priceHint(Forms\Get $get, string $field): ?string
    {
        $exclusive = $get('price_excl_vat');
        $inclusive = $get('price_incl_vat');

        if (! is_numeric($exclusive) || ! is_numeric($inclusive)) {
            return null;
        }

        $excl = Money::of($exclusive);
        $incl = Money::of($inclusive);

        if (Vat::pricesAgree($excl, $incl)) {
            return null;
        }

        $currency = (string) config('kotiva.currency.code');
        $rate = Vat::rateLabel();

        return $field === 'incl'
            ? sprintf('%s VAT on %s would be %s', $rate, Money::format($excl, $currency), Money::format(Vat::inclusiveOf($excl), $currency))
            : sprintf('%s implies %s before %s VAT', Money::format($incl, $currency), Money::format(Vat::exclusiveOf($incl), $currency), $rate);
    }

    /**
     * The curated pill taxonomy, minus the "all" pseudo-filter.
     *
     * @return array<string, string>
     */
    public static function getFilterTagOptions(): array
    {
        /** @var array<string, string> $filters */
        $filters = config('kotiva.shop.filters', []);

        return array_diff_key($filters, ['all' => '']);
    }

    /**
     * Tags on this product that are not shop pills, labelled as such.
     *
     * @return array<string, string>
     */
    public static function extraTagOptions(?Product $record): array
    {
        $pills = self::getFilterTagOptions();
        $extra = [];

        foreach ($record->filter_tags ?? [] as $tag) {
            if (! array_key_exists($tag, $pills)) {
                $extra[$tag] = $tag.' (no pill)';
            }
        }

        return $extra;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    // Resolved through the model so legacy committed artwork
                    // and dashboard uploads both render.
                    ->getStateUsing(fn (Product $record): ?string => $record->imageUrl())
                    ->height(44),

                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable()->color('gray'),

                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold')->limit(40),

                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->badge(),

                Tables\Columns\TextColumn::make('price_excl_vat')
                    ->label('Excl. VAT')
                    ->money(config('kotiva.currency.code'))
                    ->sortable()
                    // The warning icon is the discrepancy flag at list level:
                    // an admin scanning the catalog after a rate change can see
                    // which rows need a decision without opening each one.
                    ->icon(fn (Product $record): ?string => $record->pricesAgreeWithVatRate()
                        ? null
                        : 'heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->tooltip(fn (Product $record): ?string => $record->pricesAgreeWithVatRate()
                        ? null
                        : sprintf(
                            'The two prices differ by %s from %s VAT. Neither has been changed — open the product to resolve it.',
                            Money::format($record->vatDiscrepancy(), (string) config('kotiva.currency.code')),
                            Vat::rateLabel()
                        )),

                Tables\Columns\TextColumn::make('price_incl_vat')
                    ->label('Incl. VAT')
                    ->money(config('kotiva.currency.code'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->badge()
                    // Sold out and nearly-sold-out are the two states worth
                    // spotting from across the table.
                    ->color(fn (Product $record): string => match (true) {
                        $record->isSoldOut() => 'danger',
                        $record->isLowStock() => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean()->sortable(),

                Tables\Columns\IconColumn::make('is_best_seller')
                    ->label('Best seller')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('j M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),

                Tables\Filters\SelectFilter::make('stock_level')
                    ->label('Stock level')
                    ->options([
                        'out' => 'Sold out',
                        'low' => 'Low stock',
                        'in' => 'In stock',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'out' => $query->where('stock_qty', '<=', 0),
                            'low' => $query->where('stock_qty', '>', 0)
                                ->whereColumn('stock_qty', '<=', 'low_stock_threshold'),
                            'in' => $query->whereColumn('stock_qty', '>', 'low_stock_threshold'),
                            default => $query,
                        };
                    }),

                // Everything whose two prices no longer reconcile at the
                // current VAT rate — the list to work through after a rate
                // change. In SQL rather than in PHP so it works on a catalog of
                // any size and composes with the paginator; see the scope for
                // why its numbers are literals.
                Tables\Filters\Filter::make('vat_mismatch')
                    ->label('VAT price mismatch')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereRaw(Product::vatMismatchExpression())),

                Tables\Filters\TrashedFilter::make(),
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
            ->emptyStateHeading('No products yet');
    }

    public static function getRelations(): array
    {
        return [
            StockMovementsRelationManager::class,
        ];
    }

    /**
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
