<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Filament\Concerns\GatedByRole;
use App\Filament\Resources\OrderResource\Pages;
use App\Mail\OrderPlacedCustomer;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orders (§7.4).
 *
 * Read plus actions, never a form. Every mutation goes through
 * OrderStatusService, because a status change is not a column write: it can
 * return stock to the catalog, it stamps a timestamp, it appends to the audit
 * trail and it decides whether the customer is emailed. A Filament form writing
 * `status` directly would skip all four and leave no trace that it had.
 */
final class OrderResource extends Resource
{
    use GatedByRole;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'order_no';

    protected static function requiredCapability(): string
    {
        return 'canViewOrders';
    }

    /**
     * Orders are never authored here — see ListOrders.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Pending orders are the ones somebody has to act on.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = self::getModel()::query()->where('status', OrderStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /* ── global search (§7.4: order no., email, phone) ───────── */

    public static function getGloballySearchableAttributes(): array
    {
        // Dot notation resolves to whereHas() on the relationship.
        return ['order_no', 'customer.email', 'customer.phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Order $record */
        return $record->order_no;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Order $record */
        return [
            'Customer' => $record->customer?->fullName() ?? '—',
            'Total' => $record->currency.' '.$record->grand_total,
            'Status' => $record->status->label(),
        ];
    }

    /**
     * Phones are stored canonically as +9665XXXXXXXX, so a staff member typing
     * the number the way the customer says it ("0551234567") would match
     * nothing at all on a plain LIKE. Normalising the term and adding it as an
     * alternative is what makes the search usable on the phone.
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $normalised = Customer::normalisePhone($search);

        if ($normalised === null) {
            // Not a whole valid KSA mobile — the LIKE constraints already
            // applied still cover partial numbers.
            return;
        }

        $query->orWhereHas('customer', fn (Builder $q): Builder => $q->where('phone', $normalised));
    }

    /** @return Builder<Order> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('customer');
    }

    public static function form(Form $form): Form
    {
        // Intentionally empty: there is no editable form for an order.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('placed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn (Order $record): string => $record->customer?->fullName() ?? '—')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Order $record): ?string => $record->customer?->phone),

                Tables\Columns\TextColumn::make('shipping_city_name')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Placed')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn (): array => collect(OrderStatus::cases())
                        ->mapWithKeys(fn (OrderStatus $s): array => [$s->value => $s->label()])
                        ->all())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(fn (): array => collect(PaymentStatus::cases())
                        ->mapWithKeys(fn (PaymentStatus $s): array => [$s->value => $s->label()])
                        ->all()),

                Tables\Filters\SelectFilter::make('shipping_zone_id')
                    ->label('Zone')
                    ->relationship('shippingZone', 'name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('shipping_city_name')
                    ->label('City')
                    ->options(fn (): array => Order::query()
                        ->whereNotNull('shipping_city_name')
                        ->distinct()
                        ->orderBy('shipping_city_name')
                        ->pluck('shipping_city_name', 'shipping_city_name')
                        ->all())
                    ->searchable(),

                Tables\Filters\Filter::make('placed_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Placed from'),
                        Forms\Components\DatePicker::make('until')->label('Placed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('placed_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('placed_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No orders yet');
    }

    /* ── view page ───────────────────────────────────────────── */

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Order')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('order_no')->label('Order number')->weight('bold')->copyable(),

                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                        ->color(fn (OrderStatus $state): string => $state->color()),

                    Infolists\Components\TextEntry::make('payment_status')
                        ->label('Payment')
                        ->badge()
                        ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                        ->color(fn (PaymentStatus $state): string => $state->color()),

                    Infolists\Components\TextEntry::make('payment_method')
                        ->label('Method')
                        ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),

                    Infolists\Components\TextEntry::make('placed_at')->label('Placed')->dateTime('j M Y, H:i'),
                    Infolists\Components\TextEntry::make('confirmed_at')->label('Confirmed')->dateTime('j M Y, H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('shipped_at')->label('Shipped')->dateTime('j M Y, H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('delivered_at')->label('Delivered')->dateTime('j M Y, H:i')->placeholder('—'),
                ]),

            Infolists\Components\Section::make('Customer')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('customer.first_name')
                        ->label('Name')
                        ->formatStateUsing(fn (Order $record): string => $record->customer?->fullName() ?? '—'),

                    Infolists\Components\TextEntry::make('customer.email')->label('Email')->copyable(),
                    Infolists\Components\TextEntry::make('customer.phone')->label('Phone')->copyable(),

                    // Repeat business is the single most useful thing to know
                    // when deciding how to handle a problem order.
                    Infolists\Components\TextEntry::make('previous_orders')
                        ->label('Previous orders')
                        ->badge()
                        ->state(fn (Order $record): int => $record->customer === null
                            ? 0
                            : $record->customer->orders()->where('id', '!=', $record->getKey())->count()),
                ]),

            Infolists\Components\Section::make('Delivery')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('shipping_name')->label('Recipient'),
                    Infolists\Components\TextEntry::make('shipping_phone')->label('Phone'),
                    Infolists\Components\TextEntry::make('shippingZone.name')->label('Zone')->placeholder('—'),
                    Infolists\Components\TextEntry::make('address')
                        ->label('Address')
                        ->columnSpanFull()
                        ->state(fn (Order $record): string => $record->formattedAddress()),
                    Infolists\Components\TextEntry::make('customer_note')
                        ->label('Customer note')
                        ->columnSpanFull()
                        ->placeholder('None'),
                ]),

            Infolists\Components\Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            // Snapshots: this must read the same years after
                            // the catalog has moved on.
                            Infolists\Components\TextEntry::make('name_snapshot')->label('Product')->weight('bold'),
                            Infolists\Components\TextEntry::make('sku_snapshot')->label('SKU')->color('gray'),
                            Infolists\Components\TextEntry::make('qty')->label('Qty'),
                            Infolists\Components\TextEntry::make('line_total')
                                ->label('Line total')
                                // The line's own order, so a reprint shows the
                                // currency the customer was charged in rather
                                // than today's default.
                                ->money(fn (OrderItem $record): string => $record->order->currency),
                        ]),
                ]),

            Infolists\Components\Section::make('Totals')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->money(fn (Order $record): string => $record->currency),
                    Infolists\Components\TextEntry::make('shipping_fee')->label('Shipping')->money(fn (Order $record): string => $record->currency),
                    Infolists\Components\TextEntry::make('vat_amount')
                        ->label('VAT included')
                        ->money(fn (Order $record): string => $record->currency)
                        ->helperText('Prices are VAT-inclusive; this is the portion, not an addition.'),
                    Infolists\Components\TextEntry::make('grand_total')
                        ->label('Total')
                        ->money(fn (Order $record): string => $record->currency)
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                ]),

            Infolists\Components\Section::make('Timeline')
                ->description('Append-only. A mistaken change is corrected by making another one.')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('statusHistories')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                            Infolists\Components\TextEntry::make('change')
                                ->label('Change')
                                ->state(fn (Model $record): string => method_exists($record, 'describe') ? $record->describe() : ''),
                            Infolists\Components\TextEntry::make('note')->label('Note')->placeholder('—'),
                        ]),
                ]),

            Infolists\Components\Section::make('Admin note')
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('admin_note')->hiddenLabel()->placeholder('No internal note.'),
                ]),
        ]);
    }

    /* ── actions (§7.4) ──────────────────────────────────────── */

    /**
     * Defined once and reused by the view page's header, so the row action and
     * the page action cannot drift apart.
     *
     * @return array<int, Action>
     */
    public static function recordActions(): array
    {
        return [
            self::changeStatusAction(),
            self::markPaidAction(),
            self::packingSlipAction(),
            self::resendConfirmationAction(),
            self::adminNoteAction(),
        ];
    }

    private static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            // Hidden outright when the order is finished, rather than offering
            // a dropdown with nothing legal in it.
            ->visible(fn (Order $record): bool => ! $record->status->isTerminal())
            ->form(fn (Order $record): array => [
                Forms\Components\Select::make('status')
                    ->label('New status')
                    ->options(self::transitionOptions($record))
                    ->required()
                    ->native(false)
                    ->live()
                    // Cancelling is the one transition with a consequence the
                    // admin cannot undo, so it is spelled out before they act.
                    ->helperText(fn (?string $state): ?string => $state === OrderStatus::Cancelled->value
                        ? sprintf(
                            'Cancelling returns %d unit(s) to stock and emails the customer.',
                            app(OrderStatusService::class)->wouldRestoreUnits($record)
                        )
                        : null),

                Forms\Components\Textarea::make('note')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Recorded on the timeline. Not shown to the customer.'),
            ])
            ->action(function (Order $record, array $data): void {
                $to = OrderStatus::from((string) $data['status']);

                try {
                    app(OrderStatusService::class)->transition(
                        $record,
                        $to,
                        $data['note'] ?? null,
                        Filament::auth()->id(),
                    );
                } catch (InvalidOrderTransition $e) {
                    // The exception's message already names what IS allowed.
                    Notification::make()
                        ->danger()
                        ->title('Status not changed')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Order is now '.$to->label())
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function transitionOptions(Order $order): array
    {
        $options = [];

        // Straight from the service, so the UI cannot offer a move the service
        // would then refuse.
        foreach (app(OrderStatusService::class)->availableTransitions($order) as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    private static function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Mark COD paid')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Records that the courier collected the cash. Separate from the order status: a COD order ships while still unpaid.')
            ->visible(fn (Order $record): bool => $record->payment_status !== PaymentStatus::Paid)
            ->action(function (Order $record): void {
                app(OrderStatusService::class)->markPaid($record, Filament::auth()->id());

                Notification::make()->success()->title('Marked as paid')->send();
            });
    }

    private static function packingSlipAction(): Action
    {
        return Action::make('packingSlip')
            ->label('Packing slip')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (Order $record): StreamedResponse {
                $order = $record->loadMissing(['items', 'customer']);

                $pdf = Pdf::loadView('pdf.packing-slip', ['order' => $order])->setPaper('a4');

                // Streamed rather than written to disk: a packing slip is
                // reproducible from the order at any time, so storing copies
                // would only create stale ones.
                return response()->streamDownload(
                    fn () => print $pdf->output(),
                    'packing-slip-'.$order->order_no.'.pdf',
                );
            });
    }

    private static function resendConfirmationAction(): Action
    {
        return Action::make('resendConfirmation')
            ->label('Resend confirmation')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Sends the original confirmation email again, to the address on the order.')
            ->visible(fn (Order $record): bool => $record->customer !== null)
            ->action(function (Order $record): void {
                $email = $record->customer?->email;

                if ($email === null) {
                    Notification::make()->danger()->title('No customer email on this order')->send();

                    return;
                }

                // Queued (the Mailable is ShouldQueue), so the dashboard does
                // not wait on an SMTP handshake.
                Mail::to($email)->send(new OrderPlacedCustomer($record));

                Notification::make()
                    ->success()
                    ->title('Confirmation queued')
                    ->body('Sending again to '.$email.'.')
                    ->send();
            });
    }

    private static function adminNoteAction(): Action
    {
        return Action::make('adminNote')
            ->label('Internal note')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (Order $record): array => ['admin_note' => $record->admin_note])
            ->form([
                Forms\Components\Textarea::make('admin_note')
                    ->label('Internal note')
                    ->rows(4)
                    ->maxLength(1000)
                    ->helperText('Visible only in the dashboard. Never sent to the customer.'),
            ])
            ->action(function (Order $record, array $data): void {
                // The only column on an order the dashboard writes directly,
                // and it carries no side effects by design.
                $record->update(['admin_note' => $data['admin_note'] ?? null]);

                Notification::make()->success()->title('Note saved')->send();
            });
    }

    /**
     * @return Builder<Order>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'shippingZone'])->withCount('items');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            // View, never create or edit: an order is written by checkout
            // inside the transaction that reserves its stock, and every
            // subsequent change goes through OrderStatusService so the ledger,
            // the timestamps, the audit trail and the customer email stay in
            // step. A form that wrote `status` directly would skip all four.
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
