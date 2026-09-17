<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the cart's identity and lifecycle.
 *
 * The browser holds nothing but an httpOnly cookie containing a uuid; the cart
 * itself is a server row. That is deliberate — a cart in localStorage cannot
 * hold a stock reservation, and reservations are what stop the last unit being
 * promised to two shoppers (§6.5).
 */
final class CartService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * The current cart, or null. Never creates a row — a visitor reading the
     * home page should not leave a cart behind.
     */
    public function current(Request $request): ?Cart
    {
        $token = $request->cookie($this->cookieName());

        if (! is_string($token) || $token === '') {
            return null;
        }

        $cart = Cart::query()->with('items.product')->where('token', $token)->first();

        if (! $cart instanceof Cart) {
            return null;
        }

        // Lazy expiry: a cookie pointing at an expired cart behaves as empty.
        // The scheduled purge releases the stock; this only stops the stale
        // cart being used in the meantime.
        if ($cart->isExpired()) {
            $this->expire($cart);

            return null;
        }

        return $cart;
    }

    /**
     * The current cart, creating one if needed. Only called on mutation.
     */
    public function resolve(Request $request): Cart
    {
        $cart = $this->current($request);

        if ($cart instanceof Cart) {
            return $cart;
        }

        $cart = Cart::create([
            'token' => (string) Str::uuid(),
            'last_activity_at' => now(),
            'expires_at' => now()->addHours($this->ttlHours()),
        ]);

        // httpOnly, so no script can read or forge it.
        Cookie::queue(Cookie::make(
            name: $this->cookieName(),
            value: $cart->token,
            minutes: $this->ttlHours() * 60,
            httpOnly: true,
        ));

        return $cart;
    }

    /**
     * Add a product, or increase its existing line.
     *
     * @throws InsufficientStockException
     */
    public function add(Cart $cart, Product $product, int $qty): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $qty): CartItem {
            $existing = $cart->items()->where('product_id', $product->getKey())->first();

            $currentQty = $existing instanceof CartItem ? $existing->qty : 0;
            $target = $this->clampToLineMaximum($currentQty + $qty);

            // Already at the ceiling: nothing to reserve, and the caller is
            // told what the real maximum is rather than silently no-op'ing.
            if ($target === $currentQty) {
                throw new InsufficientStockException($product, $currentQty + $qty, $currentQty);
            }

            $item = $existing ?? new CartItem([
                'cart_id' => $cart->getKey(),
                'product_id' => $product->getKey(),
                'qty' => 0,
                'unit_price_snapshot' => $product->price,
            ]);

            if (! $item->exists) {
                $item->save();
            }

            // Reserve first: if stock refuses, the transaction rolls back and
            // the line never appears.
            $this->stock->reserve($product, $target - $currentQty, $item);

            $item->forceFill(['qty' => $target])->save();

            $this->touch($cart);

            return $item->refresh();
        });
    }

    /**
     * Set a line to an exact quantity. Zero removes it.
     *
     * @throws InsufficientStockException
     */
    public function updateQty(Cart $cart, CartItem $item, int $qty): void
    {
        if ($qty <= 0) {
            $this->remove($cart, $item);

            return;
        }

        DB::transaction(function () use ($cart, $item, $qty): void {
            $target = $this->clampToLineMaximum($qty);

            $this->stock->adjustReservation($item, $target);

            $item->forceFill(['qty' => $target])->save();

            $this->touch($cart);
        });
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        DB::transaction(function () use ($cart, $item): void {
            $this->stock->release($item->product, $item->qty, $item, 'Removed from cart');

            $item->delete();

            $this->touch($cart);
        });
    }

    /**
     * Release everything a cart holds and empty it. Used by checkout completion
     * and by the purge command.
     */
    public function releaseAll(Cart $cart, string $note = 'Cart released'): void
    {
        DB::transaction(function () use ($cart, $note): void {
            foreach ($cart->items()->with('product')->get() as $item) {
                if ($item->product instanceof Product) {
                    $this->stock->release($item->product, $item->qty, $item, $note);
                }

                $item->delete();
            }
        });
    }

    /**
     * Refresh the sliding expiry window. Called on every mutation.
     */
    public function touch(Cart $cart): void
    {
        $now = now();

        $cart->forceFill([
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addHours($this->ttlHours()),
        ])->save();
    }

    /**
     * An expired cart found on access: give its stock back immediately rather
     * than waiting for the hourly purge, then drop it and its cookie.
     */
    private function expire(Cart $cart): void
    {
        $this->releaseAll($cart, 'Cart expired');

        $cart->delete();

        Cookie::queue(Cookie::forget($this->cookieName()));
    }

    /**
     * A single line can never exceed the configured ceiling. Stock is enforced
     * separately by StockService, which is the only thing that may refuse.
     */
    private function clampToLineMaximum(int $qty): int
    {
        return min($qty, (int) config('kotiva.cart.max_qty_per_line'));
    }

    private function ttlHours(): int
    {
        return (int) Setting::get('cart_ttl_hours', config('kotiva.cart.ttl_hours'));
    }

    private function cookieName(): string
    {
        return (string) config('kotiva.cart.cookie');
    }
}
