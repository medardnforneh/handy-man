<?php

namespace App\Filament\Resources\SafetyAlerts\Tables;

use App\Filament\Resources\SafetyAlerts\Actions\SettleAlertActions;
use App\Filament\Resources\SafetyAlerts\SafetyAlertResource;
use App\Models\SafetyAlert;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
            ->recordUrl(fn (SafetyAlert $record): string => SafetyAlertResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                SettleAlertActions::acknowledge(),
                SettleAlertActions::resolve(),
            ]);
    }
}
