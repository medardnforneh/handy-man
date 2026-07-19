<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ReconciliationException;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Open reconciliation exceptions (P3-09) — the "needs attention" panel. Never auto-corrected; a human
 * resolves each with a balanced adjustment. Empty state is the good state.
 */
final class ReconciliationExceptionsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Needs attention · reconciliation';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ReconciliationException::query()
                    ->where('status', 'open')
                    ->latest('detected_at')
            )
            ->emptyStateHeading('All clear')
            ->emptyStateDescription('The ledger matches — no open exceptions.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false)
            ->columns([
                TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->color(fn (ReconciliationException $r): string => $r->kind === 'settlement_mismatch' ? 'danger' : 'warning')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),
                TextColumn::make('detail')->label('Detail')->wrap()->limit(90),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state, 0, '.', ' ').' FCFA'),
                TextColumn::make('detected_at')->label('Detected')->since()->sortable(),
            ]);
    }
}
