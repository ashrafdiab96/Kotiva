<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\InitialsAvatarProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The KOTIVA dashboard.
 *
 * Two changes from the generated default matter:
 *
 *   1. It authenticates against the `admin` guard and the admins table, not
 *      the `web` guard and `users`. The storefront has no customer login, so
 *      `users` has no legitimate occupant — pointing the panel at it would
 *      mean the same credential surface could one day serve both shoppers and
 *      staff.
 *
 *   2. The palette is the brand's, not Filament's amber. The primary is KOTIVA
 *      blue (#005DBA), the same token the storefront uses for its interaction
 *      colour, so the dashboard reads as part of the same product.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->brandName('KOTIVA')
            ->favicon(asset('assets/favicon.svg'))
            ->colors([
                // KOTIVA blue — the storefront's --accent-deep.
                'primary' => Color::hex('#005DBA'),
                'danger' => Color::hex('#D7282F'),
                'warning' => Color::hex('#EC7725'),
                'success' => Color::hex('#004539'),
                'info' => Color::hex('#8AB7E9'),
                'gray' => Color::Slate,
            ])
            // §7: dark mode enabled, matching the storefront's own toggle.
            ->darkMode()
            // Nothing in the dashboard leaves the domain: the brand's own
            // self-hosted Montserrat instead of Inter from fonts.bunny.net, and
            // initials drawn locally instead of sending admin names to
            // ui-avatars.com on every page load.
            ->font('Montserrat', url: asset('css/admin-font.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // The bell. A queued product import finishes after the page that
            // started it may be closed, so its outcome is delivered here.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
