<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;

/**
 * The order view page (§7.4).
 *
 * Header actions — change status, mark COD paid, packing slip, resend
 * confirmation — are defined on the resource so the same definitions can be
 * offered from the table row as well, rather than existing twice and drifting.
 */
final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return OrderResource::recordActions();
    }

    public function getTitle(): string
    {
        return $this->currentOrder()->order_no;
    }

    public function getSubheading(): string
    {
        $order = $this->currentOrder();

        return $order->customer?->fullName().' · '.$order->shipping_city_name;
    }

    private function currentOrder(): Order
    {
        /** @var Order $order */
        $order = $this->record;

        return $order;
    }
}
