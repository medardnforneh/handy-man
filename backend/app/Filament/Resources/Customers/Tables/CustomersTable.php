<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Party;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The customer list. Ordered by most recent activity, because the question that brings someone here
 * is almost always about something that just happened.
 */
final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('admin.party.name'))
                    ->searchable()
                    ->sortable()
                    // An erased party keeps its row (P1-10) — say so, rather than showing a
                    // tombstoned name as if it were a live account.
                    ->description(fn (Party $record): ?string => $record->erased_at !== null
                        ? __('admin.party.erased_on', ['date' => $record->erased_at->format('d M Y')])
                        : null),

                // The support handle. Phone is the identity in this product (doc 02), so it is what
                // a staff member has when someone calls in — and searchable for that reason.
                TextColumn::make('user.phone_e164')
                    ->label(__('admin.safety.phone'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('admin.party.status_label'))
                    ->badge()
                    ->colors([
                        'success' => 'active', 'warning' => 'pending', 'danger' => 'suspended', 'gray' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => __('admin.party.status.'.$state)),

                TextColumn::make('jobs_count')
                    ->label(__('admin.customers.jobs'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // What they have actually spent, from the engagements their jobs formed — not the
                // sum of what was quoted or asked for, which would flatter every abandoned request.
                TextColumn::make('spend')
                    ->label(__('admin.customers.spend'))
                    ->state(fn (Party $record): int => (int) $record->jobs()
                        ->join('engagements', 'engagements.job_id', '=', 'service_jobs.id')
                        ->sum('engagements.agreed_amount_minor'))
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' '))
                    ->suffix(' '.__('money.currency'))
                    ->alignEnd(),

                TextColumn::make('last_job_at')
                    ->label(__('admin.customers.last_job'))
                    ->state(fn (Party $record): ?string => $record->jobs()->max('created_at'))
                    ->dateTime('d M Y')
                    ->placeholder('—'),

                // A customer who is ALSO a provider is the normal case for a busy account, and
                // staff need to know before they answer as if this were only one of the two.
                TextColumn::make('providerProfile.verification_tier')
                    ->label(__('admin.customers.also_provider'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (mixed $state): string => __('admin.party.tier_n', ['n' => $state]))
                    ->colors(['gray' => 0, 'warning' => 1, 'info' => 2, 'success' => 3])
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => __('admin.party.status.pending'),
                    'active' => __('admin.party.status.active'),
                    'suspended' => __('admin.party.status.suspended'),
                    'closed' => __('admin.party.status.closed'),
                ]),

                Filter::make('also_provider')
                    ->label(__('admin.customers.also_provider_only'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('providerProfile')),

                // The people worth looking at when something has gone wrong.
                Filter::make('has_open_dispute')
                    ->label(__('admin.customers.disputed_only'))
                    ->query(fn (Builder $query): Builder => $query->whereExists(
                        fn ($q) => $q->from('disputes')
                            ->join('engagements', 'engagements.id', '=', 'disputes.engagement_id')
                            ->join('service_jobs', 'service_jobs.id', '=', 'engagements.job_id')
                            ->whereColumn('service_jobs.customer_party_id', 'parties.id')
                            ->whereIn('disputes.status', ['open', 'under_review'])
                    )),

                Filter::make('erased')
                    ->label(__('admin.party.erased_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('erased_at')),
            ])
            ->defaultSort('jobs_count', 'desc')
            ->recordUrl(fn (Party $record): string => CustomerResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
