<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Expired carts hold reserved stock. Without this the shop would slowly
 | starve itself: every abandoned cart keeps its units out of circulation
 | for ever while the ledger still shows them as held. Hourly is well inside
 | the default 72h TTL, so stock returns promptly after a cart lapses.
 */
Schedule::command('carts:purge')->hourly()->withoutOverlapping();
