<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\ReconciliationException;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The reworked staff dashboard (design-debt rework). Rendered through a bespoke Blade view for exact
 * visual fidelity to the approved proposal — the marketplace + money at a glance, computed live from
 * the models and the append-only ledger. Read-only; "view all" links lead to the resources.
 */
final class OverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $ledger = app(Ledger::class);

        $openExceptions = ReconciliationException::query()->where('status', 'open')->count();

        return [
            'stats' => [
                $this->stat(__('admin.kpi.open_jobs'), number_format(Job::query()->where('status', 'open')->count()), null,
                    __('admin.kpi.open_jobs_hint'), 'up', $this->spark($this->dailySeries(Job::query(), 'created_at'))),
                $this->stat(__('admin.kpi.active_engagements'), number_format(Engagement::query()->whereNull('completed_at')->count()), null,
                    __('admin.kpi.active_engagements_hint'), 'up', $this->spark($this->dailySeries(Engagement::query(), 'accepted_at'))),
                $this->stat(__('admin.kpi.escrow_held'), $this->money($ledger->totalByKindMinor(AccountKind::EscrowLiability)), __('money.currency'),
                    __('admin.kpi.escrow_held_hint'), 'flat', null, 'info'),
                $this->stat(__('admin.kpi.gmv'), $this->money((int) Engagement::query()->where('accepted_at', '>=', now()->subDays(30))->sum('agreed_amount_minor')), __('money.currency'),
                    __('admin.kpi.gmv_hint'), 'up', null),
                $this->stat(__('admin.kpi.revenue'), $this->money($ledger->totalByKindMinor(AccountKind::PlatformRevenue)), __('money.currency'),
                    __('admin.kpi.revenue_hint'), 'up', null),
                $this->stat(__('admin.kpi.exceptions'), number_format($openExceptions), null,
                    $openExceptions === 0 ? __('admin.kpi.exceptions_clear') : __('admin.kpi.exceptions_open'),
                    $openExceptions === 0 ? 'up' : 'down', null, $openExceptions === 0 ? null : 'attention'),
            ],
            'engagements' => Engagement::query()
                ->with(['job.skill', 'provider'])
                ->withCount('milestones')
                ->withCount(['milestones as milestones_paid' => fn (Builder $q) => $q->whereIn('status', ['approved', 'paid'])])
                ->latest('accepted_at')
                ->limit(5)
                ->get(),
            'exceptions' => ReconciliationException::query()->where('status', 'open')->latest('detected_at')->limit(3)->get(),
            'money' => [
                'escrow' => $ledger->totalByKindMinor(AccountKind::EscrowLiability),
                'payable' => $ledger->totalByKindMinor(AccountKind::ProviderPayable),
                'lead' => $ledger->totalByKindMinor(AccountKind::LeadCreditLiability),
                'receivable' => $ledger->totalByKindMinor(AccountKind::GatewayReceivable),
            ],
        ];
    }

    /**
     * @param  array{line: string, area: string, cx: float, cy: float}|null  $spark
     * @return array<string, mixed>
     */
    private function stat(string $label, string $value, ?string $unit, string $desc, string $dir, ?array $spark, ?string $flag = null): array
    {
        return compact('label', 'value', 'unit', 'desc', 'dir', 'spark', 'flag');
    }

    private function money(int $minor): string
    {
        return number_format($minor, 0, '.', ' ');
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return list<int>
     */
    private function dailySeries(Builder $query, string $column): array
    {
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $series[] = (int) (clone $query)->whereBetween($column, [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->count();
        }

        return $series;
    }

    /**
     * Map a series to SVG polyline/area points for a 72×40 sparkline.
     *
     * @param  list<int>  $values
     * @return array{line: string, area: string, cx: float, cy: float}
     */
    private function spark(array $values): array
    {
        $n = count($values);
        $max = max($values);
        $min = min($values);
        $range = max($max - $min, 1);
        $w = 72;
        $h = 40;
        $pad = 5;

        $pts = [];
        foreach ($values as $i => $v) {
            $x = $n <= 1 ? $w : round(($i / ($n - 1)) * $w, 1);
            $y = round($pad + (1 - (($v - $min) / $range)) * ($h - 2 * $pad), 1);
            $pts[] = [$x, $y];
        }

        $line = implode(' ', array_map(static fn (array $p): string => "{$p[0]},{$p[1]}", $pts));
        $last = $pts[$n - 1];

        return [
            'line' => $line,
            'area' => "{$line} {$w},{$h} 0,{$h}",
            'cx' => $last[0],
            'cy' => $last[1],
        ];
    }
}
