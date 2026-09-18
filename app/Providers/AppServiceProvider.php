<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\Payments\PaymentGateways;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Every payment gateway the checkout may offer. Adding one is a case on
        // PaymentMethod, an implementation of PaymentGateway, and a line here.
        $this->app->singleton(PaymentGateways::class, fn ($app): PaymentGateways => new PaymentGateways([
            $app->make(CashOnDeliveryGateway::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The announcement bar (§7.6). Shared through a composer rather than
        // read inside the layout, so the view stays free of data access and
        // every page gets the same answer from one place. Setting caches its
        // map, so this costs no query per request.
        ViewFacade::composer('layouts.app', function (View $view): void {
            $view->with('announcementText', self::announcementText());
        });
    }

    /**
     * The bar's message, or null when it should not render at all.
     *
     * Blank text with the switch on counts as off: an empty bar would still
     * push the whole page down by its height for no reason.
     */
    private static function announcementText(): ?string
    {
        $bar = Setting::get('announcement_bar', ['enabled' => false, 'text' => '']);

        if (! is_array($bar) || ! ($bar['enabled'] ?? false)) {
            return null;
        }

        $text = trim((string) ($bar['text'] ?? ''));

        return $text === '' ? null : $text;
    }
}
