<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // 2026-10-13 (Salla handoff): listener auto-discovery off. The only
    // listeners in app/Listeners are the commerce ones — SendOrderPlacedEmails
    // (OrderPlaced), SendOrderStatusEmail (OrderStatusChanged) and
    // SendLowStockAlert (StockLow) — and Salla now sends order and stock mail.
    // With discovery off none of them is registered; the classes and their
    // Mailables stay until the commerce code is deleted in its own PR.
    // To register a future non-commerce listener, use Event::listen() in
    // AppServiceProvider::boot().
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // The retired cart/checkout mutations answer 410 Gone (routes/web.php).
        // Without this a stale POST from cached JS or a crawler would get a
        // 419 for a missing token first and never learn the endpoint is gone.
        // Nothing at these paths changes state any more.
        $middleware->validateCsrfTokens(except: [
            'cart/summary',
            'cart/items',
            'cart/items/*',
            'checkout',
            'checkout/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
