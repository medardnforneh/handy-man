<?php

namespace App\Filament\Resources\Referrals\Tables;

use App\Domain\Referrals\ReferralService;
use App\Models\Referral;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReferralsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer_party_id')->label('Referrer')->limit(8)->copyable(),
                TextColumn::make('referee_party_id')->label('Referee')->limit(8)->copyable(),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending', 'success' => 'qualified', 'gray' => 'void',
                ]),
                IconColumn::make('flagged_for_review')->boolean()->label('Flagged'),
                TextColumn::make('flag_reason')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('flagged_for_review')->label('Flagged for review')->default(true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('clear')
                    ->label('Clear review')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Referral $record): bool => $record->flagged_for_review)
                    ->action(function (Referral $record): void {
                        app(ReferralService::class)->clearReview($record);
                        Notification::make()->title('Referral cleared')->success()->send();
                    }),
            ]);
    }
}
