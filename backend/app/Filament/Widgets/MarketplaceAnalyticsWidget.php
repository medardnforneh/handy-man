<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Metrics\MarketplaceAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Marketplace health at a glance (build plan P8-06): liquidity, match rate, time-to-offer, and the
 * leakage-proxy count — the numbers that tell a founder whether to turn on dispatch/bidding.
 */
final class MarketplaceAnalyticsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected function getStats(): array
    {
        $analytics = app(MarketplaceAnalytics::class);
        $m = $analytics->summary();
        $p = $analytics->platformShare();

        $timeToOffer = $m['avg_time_to_offer_seconds'] !== null
            ? round($m['avg_time_to_offer_seconds'] / 60).' min'
            : '—';

        // Doc 08's switch trigger #2, as a number rather than a feeling: the share of person-days
        // spent in the packaged app. The web split is the description, so a founder can see whether
        // the rest is phones in a browser (the PWA doing its job) or desks.
        $pct = fn (float $share): string => round($share * 100).'%';
        $platform = Stat::make('In the app (30d)', $p['person_days'] > 0 ? $pct($p['app_share']) : '—')
            ->description($p['person_days'] > 0
                ? sprintf('%s mobile web · %s desktop · %d person-days', $pct($p['shares']['web_mobile']), $pct($p['shares']['web_desktop']), $p['person_days'])
                : 'No usage recorded yet');

        return [
            Stat::make('Liquidity (offered rate, 30d)', round($m['offered_rate'] * 100).'%')
                ->description('Jobs that drew at least one offer'),
            Stat::make('Match rate (30d)', round($m['match_rate'] * 100).'%')
                ->description('Jobs that converted to an engagement'),
            Stat::make('Avg time to first offer', $timeToOffer),
            Stat::make('Active providers', (string) $m['active_providers']),
            Stat::make('Possible leakage', (string) $m['leakage_flagged'])
                ->color($m['leakage_flagged'] > 0 ? 'warning' : 'gray')
                ->description('Providers flagged for review'),
            $platform,
        ];
    }
}
