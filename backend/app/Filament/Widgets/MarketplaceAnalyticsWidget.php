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
        $m = app(MarketplaceAnalytics::class)->summary();

        $timeToOffer = $m['avg_time_to_offer_seconds'] !== null
            ? round($m['avg_time_to_offer_seconds'] / 60).' min'
            : '—';

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
        ];
    }
}
