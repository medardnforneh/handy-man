<?php

namespace App\Filament\Resources\Engagements\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EngagementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job.reference')->label('Job')->searchable()->sortable(),
                TextColumn::make('provider.display_name')->label('Provider')->searchable(),
                TextColumn::make('agreed_amount_minor')->label('Agreed')->money('XAF', divideBy: 100)->sortable(),
                TextColumn::make('assignments_count')->counts('assignments')->label('Workers'),
                IconColumn::make('is_escrowed')->boolean()->label('Escrow'),
                TextColumn::make('accepted_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('accepted_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
