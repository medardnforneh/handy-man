<?php

namespace App\Providers\Filament;

use App\Filament\LucideChrome;
use App\Filament\Widgets\LeakageWatchWidget;
use App\Filament\Widgets\MarketplaceAnalyticsWidget;
use App\Filament\Widgets\OverviewWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
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
            // "HandyMan" — one word, as it is in `app.name`, on the public site and in the app.
            // It read "Handy-Man" here alone, which is the sort of thing only ever seen by the
            // people least likely to report it.
            ->brandName('HandyMan')
            ->login()
            // 2FA is MANDATORY (build plan P1-09): isRequired forces enrolment before the panel is
            // reachable. TOTP app authenticator with recovery codes.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: true,
            )
            // GENERATED from tokens/tokens.json into config/tokens.php, because Filament builds its
            // ramps in PHP at boot and cannot reference a CSS variable the way Blade, Tailwind and
            // Ionic all do. These five were previously copied here by hand — the one surface the
            // token generator did not reach, and therefore the one that could silently drift.
            // Brand green is the single accent; semantic hues are reserved for state, never accent.
            ->colors(array_map(
                static fn (string $hex): array => Color::hex($hex),
                config('tokens.colors'),
            ))
            // Plus Jakarta Sans, from the @font-face in the token stylesheet linked below — the
            // LocalFontProvider with no URL emits no <link>, so the panel makes no request to a font CDN.
            ->font('Plus Jakarta Sans', provider: LocalFontProvider::class)
            // The redesign is dark-only (handoff open item 2 is a light theme, undesigned): forced, so
            // the panel never shows Filament's light chrome under the dark tokens.
            ->darkMode(true, isForced: true)
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
        // The redesign is set in Lucide; this reaches the chrome the resources do not choose.
        LucideChrome::register();

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
