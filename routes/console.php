<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Expired carts hold reserved stock. Without this the shop would slowly
 | starve itself: every abandoned cart keeps its units out of circulation
 | for ever while the ledger still shows them as held. Hourly is well inside
 | the default 72h TTL, so stock returns promptly after a cart lapses.
 */
// 2026-10-13 (Salla handoff): unscheduled. Carts, checkout and stock moved to
// Salla; Laravel no longer creates carts, and StockService refuses to release
// stock while kotiva.commerce_enabled is false, so purging would only fail.
// The command class stays until the commerce code is deleted in its own PR.
// Schedule::command('carts:purge')->hourly()->withoutOverlapping();
