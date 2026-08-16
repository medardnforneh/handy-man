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
                // NOT ->money('XAF', divideBy: 100). The franc has no minor unit (App\Support\Money:
                // XAF is scale 0, one minor unit IS one franc), so dividing showed every agreed
                // amount at a hundredth of itself — a 780 000 FCFA job read "FCFA 7,800". Formatted
                // the way the rest of the admin does it: space groups, currency after the number.
                TextColumn::make('agreed_amount_minor')
                    ->label('Agreed')
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state, 0, ',', ' '))
                    ->suffix(' '.__('money.currency'))
                    ->alignEnd()
                    ->sortable(),
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
