<?php

namespace App\Filament\Resources\JobOffers\Tables;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job.reference')->label('Job')->searchable()->sortable(),
                TextColumn::make('provider.display_name')->label('Provider')->searchable(),
                // Origin is a KIND, not a state, so its colours only need to be distinguishable —
                // where an offer came from is the first thing to check when one looks wrong.
                TextColumn::make('origin')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof BackedEnum ? $state->value : $state) {
                        'customer_direct' => 'info',
                        'system_dispatch' => 'primary',
                        'provider_bid' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof BackedEnum ? $state->value : $state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        // Neither a success nor a failure — the offer simply stopped being live.
                        'withdrawn', 'expired', 'superseded' => 'gray',
                        default => 'gray',
                    }),
                // Same hundredfold understatement as the engagements list had — see the note there.
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state, 0, ',', ' '))
                    ->suffix(' '.__('money.currency'))
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('responded_at')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined',
                    'withdrawn' => 'Withdrawn', 'expired' => 'Expired', 'superseded' => 'Superseded',
                ]),
                SelectFilter::make('origin')->options([
                    'customer_direct' => 'Customer direct', 'system_dispatch' => 'System dispatch',
                    'provider_bid' => 'Provider bid',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
