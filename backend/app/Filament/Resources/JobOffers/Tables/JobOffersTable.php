<?php

namespace App\Filament\Resources\JobOffers\Tables;

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
                TextColumn::make('origin')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount_minor')->label('Amount')->money('XAF', divideBy: 100)->sortable(),
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
