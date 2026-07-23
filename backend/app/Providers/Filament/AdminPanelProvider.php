<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LeakageWatchWidget;
use App\Filament\Widgets\MarketplaceAnalyticsWidget;
use App\Filament\Widgets\OverviewWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Handy-Man')
            ->login()
            // 2FA is MANDATORY (build plan P1-09): isRequired forces enrolment before the panel is
            // reachable. TOTP app authenticator with recovery codes.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: true,
            )
            ->colors([
                // From the design tokens (tokens/tokens.json). Brand green is the single accent;
                // semantic hues are reserved for state (badges/alerts), never as the accent.
                'primary' => Color::hex('#0a7d54'),
                'info' => Color::hex('#1f6feb'),
                'success' => Color::hex('#1a7f43'),
                'warning' => Color::hex('#b3620a'),
                'danger' => Color::hex('#c0392b'),
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                OverviewWidget::class,
                MarketplaceAnalyticsWidget::class,
                LeakageWatchWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        // The bespoke admin views consume the SAME generated design tokens as the app and Blade
        // (tokens/tokens.json → public/css/tokens.css) — no palette is ever redeclared here.
        // Filament switches themes with a `dark` class on <html>; the token stylesheet keys off
        // `data-theme`, so mirror one onto the other and both stay in lockstep.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => <<<'HTML'
                <link rel="stylesheet" href="/css/tokens.css">
                <link rel="stylesheet" href="/css/admin.css">
                <script>
                    (function () {
                        var root = document.documentElement;
                        var sync = function () {
                            root.setAttribute('data-theme', root.classList.contains('dark') ? 'dark' : 'light');
                        };
                        sync();
                        new MutationObserver(sync).observe(root, { attributes: true, attributeFilter: ['class'] });
                    })();
                </script>
                HTML,
        );
    }
}
