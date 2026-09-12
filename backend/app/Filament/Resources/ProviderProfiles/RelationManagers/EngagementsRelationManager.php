<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderProfiles\RelationManagers;

use App\Filament\Resources\Engagements\EngagementResource;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Review;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The provider's jobs, one row each — the detail behind the aggregates above it.
 *
 * The stats card answers "how is this provider doing"; this answers "which job went wrong", which is
 * the question actually being asked whenever someone opens a provider from a dispute or a complaint.
 * An average cannot name a job, and a rating of 3.1 tells you nothing about which customer to call.
 *
 * Every column is a fact about THAT job rather than a share of a total: what was agreed, what has
 * actually been released, how far through the milestones it is, whether it finished when it was
 * booked to, and what the customer said afterwards.
 */
class EngagementsRelationManager extends RelationManager
{
    protected static string $relationship = 'engagements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.provider.jobs_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['job.customer', 'job.skill'])
                // Released, milestone progress, the customer's rating and whether it ran late are all
                // one-row-per-engagement facts, so they are selected alongside rather than fetched
                // per row — a provider with fifty jobs would otherwise be two hundred queries.
                ->withSum(['milestones as released_minor' => fn (Builder $q) => $q->whereIn('status', ['approved', 'paid'])], 'amount_minor')
                ->withCount([
                    'milestones as milestones_total',
                    'milestones as milestones_done' => fn (Builder $q) => $q->whereIn('status', ['approved', 'paid']),
                ])
                ->addSelect(['received_rating' => Review::query()
                    ->select('rating')
                    ->whereColumn('engagement_id', 'engagements.id')
                    ->whereColumn('subject_party_id', 'engagements.provider_party_id')
                    ->whereNotNull('submitted_at')
                    ->limit(1),
                ])
                ->addSelect(['late_count' => Assignment::query()
                    ->selectRaw('count(*)')
                    ->join('work_sessions as ws', 'ws.assignment_id', '=', 'assignments.id')
                    ->whereColumn('assignments.engagement_id', 'engagements.id')
                    ->whereNotNull('assignments.scheduled_to')
                    ->whereNotNull('ws.ended_at')
                    ->whereColumn('ws.ended_at', '>', 'assignments.scheduled_to'),
                ])
                ->addSelect(['booked_count' => Assignment::query()
                    ->selectRaw('count(*)')
                    ->join('work_sessions as ws', 'ws.assignment_id', '=', 'assignments.id')
                    ->whereColumn('assignments.engagement_id', 'engagements.id')
                    ->whereNotNull('assignments.scheduled_to')
                    ->whereNotNull('ws.ended_at'),
                ])
                ->withCount(['disputes as open_disputes' => fn (Builder $q) => $q->whereIn('status', ['open', 'under_review'])])
            )
            ->columns([
                TextColumn::make('job.title')
                    ->label(__('admin.col.job'))
                    ->searchable()
                    ->wrap()
                    // The reference is what a customer quotes on the phone, so it belongs on screen
                    // even though nobody scans a column of them.
                    ->description(fn (Engagement $record): ?string => $record->job?->reference),

                TextColumn::make('job.customer.display_name')
                    ->label(__('admin.customer'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('agreed_amount_minor')
                    ->label(__('admin.agreed_amount'))
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state, 0, ',', ' '))
                    ->suffix(' '.__('money.currency'))
                    ->alignEnd()
                    ->sortable(),

                // What has actually left escrow on this job. The pair with "agreed" is the whole
                // point: a completed job whose released figure is short of what was agreed is a
                // provider still waiting for money, and that is a support call in the making.
                TextColumn::make('released_minor')
                    ->label(__('admin.released'))
                    ->state(fn (Engagement $record): string => number_format((int) ($record->getAttribute('released_minor') ?? 0), 0, ',', ' '))
                    ->suffix(' '.__('money.currency'))
                    ->alignEnd()
                    ->color(fn (Engagement $record): ?string => $record->completed_at !== null
                        && (int) ($record->getAttribute('released_minor') ?? 0) < (int) $record->agreed_amount_minor
                            ? 'warning'
                            : null),

                TextColumn::make('milestones_done')
                    ->label(__('admin.col.milestones'))
                    ->state(fn (Engagement $record): string => __('admin.provider.n_of_m', [
                        'n' => (int) $record->getAttribute('milestones_done'),
                        'm' => (int) $record->getAttribute('milestones_total'),
                    ]))
                    ->badge()
                    ->color(fn (Engagement $record): string => (int) $record->getAttribute('milestones_total') > 0
                        && (int) $record->getAttribute('milestones_done') === (int) $record->getAttribute('milestones_total')
                            ? 'success'
                            : 'gray'),

                // Late only where the job was actually booked to a time. A job with no scheduled
                // window was never promised for a moment, so calling it "on time" would be an
                // invented compliment and "late" an invented complaint (P6-12).
                TextColumn::make('late_count')
                    ->label(__('admin.provider.on_time'))
                    ->badge()
                    ->state(fn (Engagement $record): string => match (true) {
                        (int) $record->getAttribute('booked_count') === 0 => __('admin.provider.not_booked'),
                        (int) $record->getAttribute('late_count') > 0 => __('admin.provider.late'),
                        default => __('admin.provider.on_time_yes'),
                    })
                    ->color(fn (Engagement $record): string => match (true) {
                        (int) $record->getAttribute('booked_count') === 0 => 'gray',
                        (int) $record->getAttribute('late_count') > 0 => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('received_rating')
                    ->label(__('admin.provider.rating'))
                    ->badge()
                    ->state(fn (Engagement $record): string => $record->getAttribute('received_rating') === null
                        ? '—'
                        : $record->getAttribute('received_rating').'★')
                    ->color(fn (Engagement $record): string => match (true) {
                        $record->getAttribute('received_rating') === null => 'gray',
                        (int) $record->getAttribute('received_rating') <= 2 => 'danger',
                        (int) $record->getAttribute('received_rating') === 3 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('completed_at')
                    ->label(__('admin.completed'))
                    ->dateTime('d M Y')
                    ->placeholder(__('admin.provider.in_progress_short'))
                    ->sortable(),

                TextColumn::make('accepted_at')
                    ->label(__('admin.accepted'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('in_flight')
                    ->label(__('admin.provider.in_flight'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('completed_at')),

                Filter::make('disputed')
                    ->label(__('admin.customers.disputed_only'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'disputes',
                        fn (Builder $q) => $q->whereIn('status', ['open', 'under_review'])
                    )),

                // The two shapes of trouble worth finding without reading every row.
                Filter::make('poorly_rated')
                    ->label(__('admin.provider.poorly_rated'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'reviews',
                        fn (Builder $q) => $q->whereColumn('subject_party_id', 'engagements.provider_party_id')
                            ->whereNotNull('submitted_at')
                            ->where('rating', '<=', 2)
                    )),

                Filter::make('unpaid')
                    ->label(__('admin.provider.unpaid_only'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('completed_at')
                        ->whereRaw('coalesce((select sum(amount_minor) from milestones where milestones.engagement_id = engagements.id and milestones.status in (?, ?)), 0) < engagements.agreed_amount_minor', ['approved', 'paid'])),
            ])
            ->defaultSort('accepted_at', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.details'))
                    ->icon(LucideIcon::ExternalLink)
                    ->url(fn (Engagement $record): string => EngagementResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
