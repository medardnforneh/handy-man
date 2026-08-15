<?php

namespace App\Filament\Resources\Jobs\Tables;

use BackedEnum;
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
                // A bare ->badge() takes the panel's primary colour, so every badge in the admin was
                // the same green and a status column carried no information at a glance — the one
                // thing a status column is for. Colour follows MEANING: a job in flight is not the
                // same kind of fact as one that is finished, cancelled or in dispute.
                TextColumn::make('engagement_mode')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof BackedEnum ? $state->value : $state) {
                        'onsite' => 'warning',
                        'remote' => 'info',
                        'hybrid' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof BackedEnum ? $state->value : $state) {
                        'draft' => 'gray',
                        'open', 'offered' => 'info',
                        'engaged', 'scheduled', 'en_route', 'in_progress' => 'warning',
                        'work_submitted' => 'primary',
                        'completed', 'closed' => 'success',
                        'cancelled' => 'gray',
                        'disputed' => 'danger',
                        default => 'gray',
                    }),
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
