<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderProfiles\Tables;

use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\ProviderProfile;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The provider list, as the counterpart of the customer one.
 *
 * It shipped as the Filament scaffold — six columns with hardcoded English labels, no filters and no
 * way in but Edit. What staff come here to do is find a provider and judge them, so the columns are
 * the things that judgement rests on: how verified they are, how much work they have finished, and
 * what their customers said about it.
 */
final class ProviderProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')
                    ->label(__('admin.provider.name'))
                    ->searchable()
                    ->sortable()
                    // The headline is what a customer sees instead of a name before an engagement
                    // exists (P2-03), so it belongs with the name rather than in a column of its own.
                    ->description(fn (ProviderProfile $record): ?string => $record->headline),

                TextColumn::make('party.user.phone_e164')
                    ->label(__('admin.safety.phone'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                // The gate on paid on-site work (P0-17/P6-03) — the single most operative fact here.
                TextColumn::make('verification_tier')
                    ->label(__('admin.provider.tier'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => __('admin.party.tier_n', ['n' => $state]))
                    ->colors(['gray' => 0, 'warning' => 1, 'info' => 2, 'success' => 3])
                    ->sortable(),

                TextColumn::make('jobs_completed')
                    ->label(__('admin.provider.jobs_completed'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // Withheld below the sample floor rather than shown thin (P6-09/P6-12): a single
                // five-star review is not a five-star provider, and staff should not act as if it is.
                // Built with `state()` rather than `formatStateUsing()`: Filament never calls a
                // formatter for a null state, so an unrated provider rendered as an empty cell —
                // which reads as missing data rather than as "nobody has reviewed them yet".
                TextColumn::make('rating_avg')
                    ->label(__('admin.provider.rating'))
                    ->state(fn (ProviderProfile $record): string => $record->rating_avg === null
                        ? __('admin.provider.unrated')
                        : number_format((float) $record->rating_avg, 2).' ('.$record->rating_count.')')
                    ->sortable(),

                TextColumn::make('skills_count')
                    ->counts('skills')
                    ->label(__('admin.provider.skills'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin.provider.joined'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('verification_tier')
                    ->label(__('admin.provider.tier'))
                    ->options([
                        0 => __('admin.party.tier_n', ['n' => 0]),
                        1 => __('admin.party.tier_n', ['n' => 1]),
                        2 => __('admin.party.tier_n', ['n' => 2]),
                        3 => __('admin.party.tier_n', ['n' => 3]),
                    ]),

                // The two ends of the queue staff work: someone who cannot be paid for on-site work
                // yet, and someone nobody has reviewed.
                Filter::make('below_tier_2')
                    ->label(__('admin.provider.below_tier_2'))
                    ->query(fn (Builder $query): Builder => $query->where('verification_tier', '<', 2)),

                Filter::make('unrated')
                    ->label(__('admin.provider.unrated_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('rating_avg')),

                Filter::make('has_open_dispute')
                    ->label(__('admin.customers.disputed_only'))
                    ->query(fn (Builder $query): Builder => $query->whereExists(
                        fn ($q) => $q->from('disputes')
                            ->join('engagements', 'engagements.id', '=', 'disputes.engagement_id')
                            ->whereColumn('engagements.provider_party_id', 'provider_profiles.party_id')
                            ->whereIn('disputes.status', ['open', 'under_review'])
                    )),
            ])
            ->defaultSort('jobs_completed', 'desc')
            ->recordUrl(fn (ProviderProfile $record): string => ProviderProfileResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
