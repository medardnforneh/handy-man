<?php

namespace App\Filament\Resources\SafetyAlerts\Tables;

use App\Domain\Safety\Actions\ResolveSafetyAlert;
use App\Models\SafetyAlert;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SafetyAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->badge()->colors(['danger' => 'panic'])->searchable(),
                TextColumn::make('user.party.display_name')->label('User')->searchable(),
                TextColumn::make('note')->limit(50)->placeholder('—'),
                TextColumn::make('status')->badge()->colors([
                    'danger' => 'open', 'warning' => 'acknowledged', 'success' => 'resolved',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved'])
                    ->default('open'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('acknowledge')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (SafetyAlert $record): bool => $record->status === 'open')
                    ->action(fn (SafetyAlert $record) => self::settle($record, 'acknowledged')),
                Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SafetyAlert $record): bool => $record->status !== 'resolved')
                    ->action(fn (SafetyAlert $record) => self::settle($record, 'resolved')),
            ]);
    }

    private static function settle(SafetyAlert $record, string $status): void
    {
        /** @var User $admin */
        $admin = Auth::user();
        app(ResolveSafetyAlert::class)->handle($record, $admin, $status);
        Notification::make()->title("Alert {$status}")->success()->send();
    }
}
