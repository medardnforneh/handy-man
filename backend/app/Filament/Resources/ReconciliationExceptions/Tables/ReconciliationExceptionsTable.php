<?php

namespace App\Filament\Resources\ReconciliationExceptions\Tables;

use App\Filament\Resources\ReconciliationExceptions\Actions\ResolveExceptionAction;
use App\Filament\Resources\ReconciliationExceptions\ReconciliationExceptionResource;
use App\Models\ReconciliationException;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.recon.kind.'.$state))
                    ->colors(['danger' => 'settlement_mismatch', 'warning' => 'intent_missing_ledger'])
                    ->searchable(),
                TextColumn::make('detail')->limit(70)->wrap(),
                TextColumn::make('amount_minor')
                    ->label(__('admin.amount'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state, 0, '.', ' '))
                    ->alignEnd(),
                TextColumn::make('status')->badge()->colors(['danger' => 'open', 'success' => 'resolved']),
                TextColumn::make('detected_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'resolved' => 'Resolved'])
                    ->default('open'),
            ])
            // Oldest first: an unresolved settlement gap gets worse with age, so the queue should
            // surface the one that has been open longest, not the newest alarm.
            ->defaultSort('detected_at', 'asc')
            ->recordUrl(fn (ReconciliationException $record): string => ReconciliationExceptionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                ResolveExceptionAction::make(),
            ]);
    }
}
