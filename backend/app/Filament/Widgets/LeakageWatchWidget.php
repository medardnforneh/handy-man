<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProviderProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Possible-leakage watch (build plan P6-13). Surfaces providers with many completions but few repeat
 * customers — a proxy for taking business off-platform. It **flags for a human, never accuses**: high
 * completion + low repeat is a signal to look, not a verdict. Admin-only (it lives in the panel).
 */
final class LeakageWatchWidget extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Possible leakage — review, do not accuse';
    }

    public function table(Table $table): Table
    {
        $minCompleted = (int) config('metrics.leakage_min_completed', 8);
        $threshold = (float) config('metrics.leakage_repeat_threshold', 0.15);

        $completed = '(select count(*) from engagements e where e.provider_party_id = provider_profiles.party_id and e.completed_at is not null)';
        $distinct = '(select count(distinct j.customer_party_id) from engagements e join service_jobs j on j.id = e.job_id where e.provider_party_id = provider_profiles.party_id and e.completed_at is not null)';

        return $table
            ->query(
                ProviderProfile::query()
                    ->select('provider_profiles.*')
                    ->selectRaw("{$completed} as completed_total")
                    ->selectRaw("{$distinct} as distinct_customers")
                    ->whereRaw("{$completed} >= ?", [$minCompleted])
                    ->whereRaw("({$completed} - {$distinct}) < ? * {$completed}", [$threshold])
            )
            ->columns([
                TextColumn::make('party.display_name')->label('Provider'),
                TextColumn::make('completed_total')->label('Completed')->sortable(),
                TextColumn::make('distinct_customers')->label('Distinct customers'),
                TextColumn::make('repeat_rate')
                    ->label('Repeat rate')
                    ->state(fn (ProviderProfile $record): string => self::repeatRate($record)),
            ])
            ->emptyStateHeading('No providers flagged')
            ->paginated([10]);
    }

    private static function repeatRate(ProviderProfile $record): string
    {
        /** @var int $total */
        $total = $record->getAttribute('completed_total') ?? 0;
        /** @var int $distinct */
        $distinct = $record->getAttribute('distinct_customers') ?? 0;

        if ($total <= 0) {
            return '—';
        }

        return round((($total - $distinct) / $total) * 100).'%';
    }

    public static function canView(): bool
    {
        // Only relevant once there's enough history to be meaningful.
        return true;
    }
}
