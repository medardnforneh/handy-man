<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Jobs\JobStatus;
use App\Models\Engagement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The latest engagements with their money and job status (P2-10 rework). Read-only — a window onto
 * the marketplace, not a control surface.
 */
final class RecentEngagementsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent engagements';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Engagement::query()
                    ->with(['job', 'provider'])
                    ->withCount('milestones')
                    ->latest('accepted_at')
            )
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('job.reference')->label('Job')->searchable()->weight('semibold'),
                TextColumn::make('provider.display_name')->label('Provider')->searchable(),
                TextColumn::make('job.engagement_mode')->label('Mode')->badge()->toggleable(),
                TextColumn::make('agreed_amount_minor')
                    ->label('Agreed')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, '.', ' ').' FCFA')
                    ->sortable(),
                TextColumn::make('milestones_count')->label('Milestones')->alignCenter(),
                TextColumn::make('job.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (JobStatus $state): string => str_replace('_', ' ', $state->value))
                    ->color(fn (JobStatus $state): string => match ($state) {
                        JobStatus::Completed, JobStatus::Closed => 'success',
                        JobStatus::InProgress, JobStatus::WorkSubmitted => 'warning',
                        JobStatus::Disputed, JobStatus::Cancelled => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('accepted_at')->label('Accepted')->since()->sortable(),
            ]);
    }
}
