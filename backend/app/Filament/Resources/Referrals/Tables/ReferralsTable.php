<?php

namespace App\Filament\Resources\Referrals\Tables;

use App\Filament\Resources\Referrals\Actions\ClearReviewAction;
use App\Filament\Resources\Referrals\ReferralResource;
use App\Models\Referral;
use Filament\Actions\ViewAction;
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
                // Both sides were raw UUIDs truncated to 8 characters, which is unreadable and
                // un-searchable — you cannot judge a referral without knowing who is involved.
                TextColumn::make('referrer.display_name')->label('Referrer')->searchable(),
                TextColumn::make('referee.display_name')->label('Referee')->searchable(),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending', 'success' => 'qualified', 'gray' => 'void',
                ]),
                IconColumn::make('flagged_for_review')->boolean()->label('Flagged'),
                TextColumn::make('flag_reason')->placeholder('—')->wrap(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('flagged_for_review')->label('Flagged for review')->default(true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Referral $record): string => ReferralResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                ClearReviewAction::make(),
            ]);
    }
}
