<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deletes expired carts and — critically — gives their reserved stock back.
 *
 * Without this, every abandoned cart would hold its units out of circulation
 * permanently and the shop would slowly starve itself of sellable stock while
 * reporting plenty in the ledger.
 */
final class PurgeExpiredCarts extends Command
{
    protected $signature = 'carts:purge';

    protected $description = 'Delete expired carts and release the stock they were holding';

    public function handle(CartService $carts): int
    {
        $purged = 0;
        $released = 0;

        Cart::query()
            ->expired()
            ->with('items.product')
            ->chunkById(100, function ($expired) use ($carts, &$purged, &$released): void {
                foreach ($expired as $cart) {
                    $released += $cart->items->sum('qty');

                    $carts->releaseAll($cart, 'Cart expired');
                    $cart->delete();

                    $purged++;
                }
            });

        Log::info('Expired carts purged', [
            'carts' => $purged,
            'units_released' => $released,
        ]);

        $this->info(sprintf('Purged %d expired cart(s); released %d unit(s).', $purged, $released));

        return self::SUCCESS;
    }
}
