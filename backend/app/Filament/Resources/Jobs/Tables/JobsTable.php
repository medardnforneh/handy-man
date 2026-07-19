<?php

namespace App\Filament\Resources\Jobs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('title')->limit(40)->searchable(),
                TextColumn::make('customer.display_name')->label('Customer')->searchable(),
                TextColumn::make('skill.name_fr')->label('Skill')->toggleable(),
                TextColumn::make('engagement_mode')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('urgency')->sortable()->toggleable(),
                TextColumn::make('published_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'open' => 'Open', 'offered' => 'Offered',
                    'engaged' => 'Engaged', 'in_progress' => 'In progress',
                    'completed' => 'Completed', 'cancelled' => 'Cancelled',
                ]),
                SelectFilter::make('engagement_mode')->options([
                    'onsite' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
