<?php

namespace App\Filament\Resources\Disputes\Tables;

use App\Filament\Resources\Disputes\Actions\AdjudicateAction;
use App\Filament\Resources\Disputes\DisputeResource;
use App\Models\Dispute;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DisputesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge()->searchable(),
                TextColumn::make('engagement_id')->label('Engagement')->limit(8)->copyable(),
                TextColumn::make('body')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->colors([
                    'danger' => 'open', 'warning' => 'reviewing', 'success' => 'resolved', 'gray' => 'rejected',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'rejected' => 'Rejected'])
                    ->default('open'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Dispute $record): string => DisputeResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                AdjudicateAction::make(),
            ]);
    }
}
