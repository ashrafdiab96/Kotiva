<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\StockMovementsRelationManager;
use App\Models\Product;
use App\Services\ProductImageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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

            Forms\Components\TextInput::make('price')
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix(config('kotiva.currency.code'))
                ->helperText('VAT-inclusive, as displayed to the customer.'),

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
                ->options(self::getFilterTagOptions())
                ->columns(3)
                ->columnSpanFull()
                ->helperText('Controls which shop filter pills this product appears under.'),

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
            Forms\Components\FileUpload::make('image')
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

            Forms\Components\FileUpload::make('gallery')
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
                ->default((int) config('kotiva.stock.low_stock_threshold'))
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

                Tables\Columns\TextColumn::make('price')
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
