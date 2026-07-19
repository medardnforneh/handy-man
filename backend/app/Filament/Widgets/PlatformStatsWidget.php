<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\ReconciliationException;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * The marketplace + money at a glance (P2-10 rework). Summary before detail: open work, active
 * engagements, escrow held, GMV, revenue, and — highlighted — open reconciliation exceptions, the one
 * thing that needs a human. All figures come straight from the models and the append-only ledger.
 */
final class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $ledger = app(Ledger::class);

        $openJobs = Job::query()->where('status', 'open')->count();
        $activeEngagements = Engagement::query()->whereNull('completed_at')->count();
        $escrowHeld = $ledger->availableMinor(AccountKind::EscrowLiability);
        $gmv30 = (int) Engagement::query()->where('accepted_at', '>=', now()->subDays(30))->sum('agreed_amount_minor');
        $revenue = $ledger->availableMinor(AccountKind::PlatformRevenue);
        $openExceptions = ReconciliationException::query()->where('status', 'open')->count();

        return [
            Stat::make('Open jobs', (string) $openJobs)
                ->description('Awaiting an offer or quote')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary')
                ->chart($this->dailySeries(Job::query(), 'created_at')),

            Stat::make('Active engagements', (string) $activeEngagements)
                ->description('In flight, not yet completed')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('primary')
                ->chart($this->dailySeries(Engagement::query(), 'accepted_at')),

            Stat::make('Escrow held', $this->money($escrowHeld))
                ->description('Owed to providers on completion')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('info'),

            Stat::make('GMV · 30 days', $this->money($gmv30))
                ->description('Agreed value of new engagements')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Platform revenue', $this->money($revenue))
                ->description('Commission earned to date')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Reconciliation exceptions', (string) $openExceptions)
                ->description($openExceptions === 0 ? 'Ledger matches — all clear' : 'Open · needs a human')
                ->descriptionIcon($openExceptions === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($openExceptions === 0 ? 'success' : 'danger'),
        ];
    }

    /**
     * New rows per day over the last 7 days, for a sparkline.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return list<float>
     */
    private function dailySeries(Builder $query, string $column): array
    {
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $series[] = (float) (clone $query)
                ->whereBetween($column, [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->count();
        }

        return $series;
    }

    private function money(int $minor): string
    {
        return number_format($minor, 0, '.', ' ').' FCFA';
    }
}
