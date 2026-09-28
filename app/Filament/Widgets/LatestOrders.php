<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\Concerns\GatedWidget;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ten most recent orders (§7.1).
 *
 * Available to every role: this is the staff role's working list.
 */
final class LatestOrders extends TableWidget
{
    use GatedWidget;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected static function requiredCapability(): string
    {
        return 'canViewOrders';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest orders')
            ->query(self::query())
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('Order')
                    ->weight('bold')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn (Order $record): string => $record->customer?->fullName() ?? '—')
                    ->limit(24),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Paid')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Placed')
                    ->since()
                    ->tooltip(fn (Order $record): ?string => $record->placed_at?->format('j M Y, H:i')),
            ])
            ->emptyStateHeading('No orders yet');
    }

    /**
     * @return Builder<Order>
     */
    public static function query(): Builder
    {
        return Order::query()
            ->with('customer')
            ->latest('placed_at')
            ->limit(10);
    }
}
