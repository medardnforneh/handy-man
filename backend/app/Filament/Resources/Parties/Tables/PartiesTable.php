<?php

namespace App\Filament\Resources\Parties\Tables;

use App\Filament\Resources\Parties\PartyResource;
use App\Models\Party;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartiesTable
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
                    ->description(fn (Party $record): ?string => $record->erased_at !== null ? __('admin.party.erased_on', ['date' => $record->erased_at->format('d M Y')]) : null),
                TextColumn::make('kind')->badge()->formatStateUsing(fn (string $state): string => __('admin.party.kind.'.$state)),
                TextColumn::make('status')->badge()->colors([
                    'success' => 'active', 'warning' => 'pending', 'danger' => 'suspended', 'gray' => 'closed',
                ])->formatStateUsing(fn (string $state): string => __('admin.party.status.'.$state)),
                TextColumn::make('user.phone_e164')->label(__('admin.safety.phone'))->searchable()->placeholder('—'),
                TextColumn::make('providerProfile.verification_tier')
                    ->label(__('admin.party.tier'))
                    ->badge()
                    ->placeholder('—')
                    ->colors(['gray' => 0, 'warning' => 1, 'info' => 2, 'success' => 3]),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    'individual' => __('admin.party.kind.individual'),
                    'organization' => __('admin.party.kind.organization'),
                ]),
                SelectFilter::make('status')->options([
                    'pending' => __('admin.party.status.pending'),
                    'active' => __('admin.party.status.active'),
                    'suspended' => __('admin.party.status.suspended'),
                    'closed' => __('admin.party.status.closed'),
                ]),
                Filter::make('providers_only')
                    ->label(__('admin.party.providers_only'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('providerProfile')),
                Filter::make('erased')
                    ->label(__('admin.party.erased_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('erased_at')),
            ])
            ->defaultSort('display_name')
            ->recordUrl(fn (Party $record): string => PartyResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
