<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->adjustStockAction(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * The only route by which an admin changes a quantity.
     *
     * Routed through StockService rather than a form field so the change is
     * written inside the same transaction as its ledger movement, and so a
     * removal cannot take stock below zero.
     */
    private function adjustStockAction(): Actions\Action
    {
        return Actions\Action::make('adjustStock')
            ->label('Adjust stock')
            ->icon('heroicon-o-arrows-up-down')
            ->color('primary')
            ->modalHeading('Adjust stock')
            ->modalSubmitActionLabel('Record movement')
            ->form([
                Forms\Components\Placeholder::make('current')
                    ->label('Current quantity')
                    ->content(fn (): string => (string) $this->currentProduct()->stock_qty),

                Forms\Components\TextInput::make('delta')
                    ->label('Change')
                    ->numeric()
                    ->required()
                    ->helperText('Positive to add stock, negative to remove. For example -3 for damaged units.')
                    ->rule('not_in:0'),

                Forms\Components\Select::make('reason')
                    ->options(collect(StockMovementReason::adminSelectable())
                        ->mapWithKeys(fn (StockMovementReason $r): array => [$r->value => $r->label()])
                        ->all())
                    ->default(StockMovementReason::Restock->value)
                    ->required()
                    ->native(false),

                Forms\Components\Textarea::make('note')
                    ->rows(2)
                    ->maxLength(255)
                    ->helperText('Optional, but the ledger is much easier to read later with one.'),
            ])
            ->action(function (array $data): void {
                $product = $this->currentProduct();
                $delta = (int) $data['delta'];

                $reason = StockMovementReason::from((string) $data['reason']);

                try {
                    app(StockService::class)->adjust(
                        $product,
                        $delta,
                        $reason,
                        $data['note'] ?? null,
                        // The panel's own guard — auth() reads the web guard,
                        // which has no admin on it, so attribution would be
                        // null on every movement the dashboard records.
                        Filament::auth()->id(),
                    );
                } catch (InsufficientStockException|InvalidArgumentException $e) {
                    // Most often: removing more units than exist. Caught by
                    // concrete class — StockService throws these two, and
                    // guessing at a shared parent is how a friendly message
                    // silently becomes a 500.
                    Notification::make()
                        ->danger()
                        ->title('Stock not adjusted')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                $this->refreshFormData(['stock_qty']);

                Notification::make()
                    ->success()
                    ->title('Stock adjusted')
                    ->body(($delta > 0 ? '+' : '').$delta.' recorded. New quantity: '.$product->refresh()->stock_qty.'.')
                    ->send();
            });
    }

    private function currentProduct(): Product
    {
        /** @var Product $product */
        $product = $this->record;

        return $product;
    }
}
