<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cart endpoints.
 *
 * Every mutation answers twice: JSON for the enhanced UI, and a redirect back
 * for a visitor with JavaScript disabled. §9 requires the forms to work either
 * way, so the controller never assumes a fetch() caller.
 *
 * Totals are always recomputed from the server's own state and returned in the
 * response — the client never does cart arithmetic (§6.3).
 */
final class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function index(Request $request): View
    {
        $cart = $this->carts->current($request);

        return view('cart.index', [
            'cart' => $cart,
            'items' => $cart instanceof Cart ? $cart->items : collect(),
        ]);
    }

    /**
     * Nav badge source. Deliberately cheap and cache-free: a stale badge after
     * a stock change is worse than an extra query.
     */
    public function summary(Request $request): JsonResponse
    {
        $cart = $this->carts->current($request);

        return response()->json($this->payload($cart));
    }

    public function store(StoreCartItemRequest $request): JsonResponse|RedirectResponse
    {
        /** @var Product $product */
        $product = Product::query()->active()->findOrFail($request->integer('product_id'));

        $cart = $this->carts->resolve($request);

        try {
            $this->carts->add($cart, $product, $request->integer('qty'));
        } catch (InsufficientStockException $e) {
            return $this->stockFailure($request, $e);
        }

        return $this->success($request, $cart->fresh(), sprintf('%s added to your cart.', $product->displayName()));
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResponse|RedirectResponse
    {
        $cart = $this->ownedCart($request, $item);

        try {
            $this->carts->updateQty($cart, $item, $request->integer('qty'));
        } catch (InsufficientStockException $e) {
            return $this->stockFailure($request, $e);
        }

        return $this->success($request, $cart->fresh(), 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $item): JsonResponse|RedirectResponse
    {
        $cart = $this->ownedCart($request, $item);
        $name = $item->product->displayName();

        $this->carts->remove($cart, $item);

        return $this->success($request, $cart->fresh(), sprintf('%s removed.', $name));
    }

    /**
     * A cart item may only be touched through the cookie that owns it.
     *
     * Without this an incrementing id would let anyone edit or delete a
     * stranger's cart line — and, because lines hold stock reservations,
     * release stock they never reserved.
     */
    private function ownedCart(Request $request, CartItem $item): Cart
    {
        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $item->cart_id !== $cart->getKey()) {
            throw new NotFoundHttpException('That cart item does not belong to this cart.');
        }

        return $cart;
    }

    private function success(Request $request, ?Cart $cart, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($this->payload($cart) + ['message' => $message]);
        }

        return back()->with('cart_status', $message);
    }

    private function stockFailure(Request $request, InsufficientStockException $e): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $e->userMessage(),
                'product_id' => $e->product->getKey(),
                'available' => $e->available,
            ], 422);
        }

        return back()->withErrors(['qty' => $e->userMessage()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?Cart $cart): array
    {
        if (! $cart instanceof Cart) {
            return ['count' => 0, 'subtotal' => '0.00', 'currency' => config('kotiva.currency.code'), 'items' => []];
        }

        $cart->loadMissing('items.product');

        return [
            'count' => $cart->itemCount(),
            'subtotal' => $cart->subtotal(),
            'currency' => config('kotiva.currency.code'),
            'items' => $cart->items->map(fn (CartItem $item): array => [
                'id' => $item->getKey(),
                'product_id' => $item->product_id,
                'name' => $item->product->displayName(),
                'slug' => $item->product->slug,
                'image' => (string) $item->product->imageUrl(),
                'url' => route('product.show', ['slug' => $item->product->slug]),
                'qty' => $item->qty,
                'unit_price' => $item->unitPrice(),
                'line_total' => $item->lineTotal(),
                'max_qty' => max($item->qty, $item->product->maxOrderableQty()),
                'price_changed' => $item->priceHasChanged(),
            ])->values()->all(),
        ];
    }
}
