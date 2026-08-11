<?php

namespace App\Filament\Resources\PaymentIntents\Tables;

use App\Domain\Money\PaymentStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentIntentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')->label(__('admin.money.payer'))->searchable(),
                TextColumn::make('purpose')->badge(),
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
                TextColumn::make('msisdn')->label(__('admin.money.msisdn'))->searchable()->toggleable(),
                TextColumn::make('external_ref')->label(__('admin.money.gateway_ref'))->searchable()->copyable()->placeholder('—'),
                // The presence of a ledger transaction is the thing worth scanning for: a succeeded
                // intent WITHOUT one is exactly what the nightly reconciliation raises an exception
                // for, so it should be visible here rather than only in the exception queue.
                TextColumn::make('ledger_transaction_id')
                    ->label(__('admin.money.posted'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? __('admin.money.not_posted') : __('admin.money.posted_yes'))
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'gray' : 'success'),
                TextColumn::make('initiated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PaymentStatus::Pending->value => __('admin.money.status.pending'),
                    PaymentStatus::Processing->value => __('admin.money.status.processing'),
                    PaymentStatus::Succeeded->value => __('admin.money.status.succeeded'),
                    PaymentStatus::Failed->value => __('admin.money.status.failed'),
                    PaymentStatus::Expired->value => __('admin.money.status.expired'),
                ]),
                SelectFilter::make('purpose')->options([
                    'escrow' => __('admin.money.purpose.escrow'),
                    'lead_credits' => __('admin.money.purpose.lead_credits'),
                ]),
            ])
            ->defaultSort('initiated_at', 'desc');
    }
}
