<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\PlatformStatsWidget;
use App\Filament\Widgets\RecentEngagementsWidget;
use App\Filament\Widgets\ReconciliationExceptionsWidget;
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
use Illuminate\Support\Facades\Blade;
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
                PlatformStatsWidget::class,
                ReconciliationExceptionsWidget::class,
                RecentEngagementsWidget::class,
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
        // Light, additive polish aligned with the design tokens — refined card radius, softer
        // shadows, and calmer table headers. Additive-only so it can't break Filament's layout.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render(<<<'HTML'
                <style>
                    .fi-wi, .fi-section, .fi-ta-ctn { border-radius: 16px !important; }
                    .fi-wi-stats-overview-stat { border-radius: 14px !important; box-shadow: 0 1px 2px rgba(16,24,32,.04), 0 4px 16px rgba(16,24,32,.05); }
                    .fi-ta-header-cell { letter-spacing: .04em; text-transform: uppercase; font-size: .7rem; }
                    .fi-sidebar-nav .fi-sidebar-group-label { letter-spacing: .08em; }
                    .fi-topbar { backdrop-filter: saturate(1.3) blur(8px); }
                </style>
            HTML),
        );
    }
}
