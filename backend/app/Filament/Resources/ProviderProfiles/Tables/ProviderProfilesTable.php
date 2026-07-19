<?php

namespace App\Filament\Resources\ProviderProfiles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProviderProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')->label('Provider')->searchable(),
                TextColumn::make('headline')->limit(40)->toggleable(),
                TextColumn::make('verification_tier')->badge()->label('Verif. tier'),
                TextColumn::make('skills_count')->counts('skills')->label('Skills'),
                TextColumn::make('jobs_completed')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
