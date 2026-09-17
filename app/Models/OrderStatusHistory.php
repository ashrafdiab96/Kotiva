<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in an order's status timeline.
 *
 * Append-only: a mistaken status change is corrected by making another change,
 * so the trail of who did what always survives.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property int|null $admin_id
 * @property string|null $note
 */
final class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'admin_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            // from_status is null on the row that records the order's creation.
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * "Order placed" for the opening entry, otherwise "Confirmed → Shipped".
     */
    public function describe(): string
    {
        if (! $this->from_status instanceof OrderStatus) {
            return 'Order placed';
        }

        return $this->from_status->label().' → '.$this->to_status->label();
    }
}
