<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Filament\Resources\Reports\Actions\ReviewReportAction;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge()->searchable(),
                TextColumn::make('subject.display_name')->label('Subject')->searchable(),
                TextColumn::make('body')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'open', 'info' => 'reviewing', 'success' => 'resolved', 'gray' => 'dismissed',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'])
                    ->default('open'),
                SelectFilter::make('category')->options([
                    'fraud' => 'Fraud', 'no_show' => 'No-show', 'harassment' => 'Harassment',
                    'safety' => 'Safety', 'spam' => 'Spam', 'off_platform' => 'Off-platform', 'other' => 'Other',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Report $record): string => ReportResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                ReviewReportAction::make(),
            ]);
    }
}
