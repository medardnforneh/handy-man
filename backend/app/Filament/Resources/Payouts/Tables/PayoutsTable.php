<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Domain\Money\PaymentStatus;
use App\Models\Payout;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')->label(__('admin.money.payee'))->searchable(),
                TextColumn::make('amount_minor')
                    ->label(__('admin.amount'))
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, '.', ' '))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')->badge()->colors([
                    'success' => PaymentStatus::Succeeded->value,
                    'warning' => PaymentStatus::Processing->value,
                    'danger' => PaymentStatus::Failed->value,
                    'gray' => PaymentStatus::Expired->value,
                ]),
                // A reversal is the exceptional case a human wants to spot, so it gets its own
                // column rather than hiding inside a status that still reads "succeeded".
                TextColumn::make('reversed_at')
                    ->label(__('admin.money.reversed'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : __('admin.money.reversed_yes'))
                    ->placeholder('—'),
                TextColumn::make('msisdn')->label(__('admin.money.msisdn'))->searchable()->toggleable(),
                TextColumn::make('external_ref')->label(__('admin.money.gateway_ref'))->searchable()->copyable()->placeholder('—'),
                TextColumn::make('requested_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PaymentStatus::Pending->value => __('admin.money.status.pending'),
                    PaymentStatus::Processing->value => __('admin.money.status.processing'),
                    PaymentStatus::Succeeded->value => __('admin.money.status.succeeded'),
                    PaymentStatus::Failed->value => __('admin.money.status.failed'),
                ]),
                Filter::make('reversed')
                    ->label(__('admin.money.reversed_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('reversed_at')),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordUrl(fn (Payout $record): ?string => null);
    }
}
