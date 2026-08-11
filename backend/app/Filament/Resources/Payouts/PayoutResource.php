<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Money going out to providers (P3-08). Read-only for the same reason as payment intents: a payout's
 * state belongs to the gateway, and `payouts:reconcile` is what moves it.
 *
 * The column that matters most here is the reversal: a confirmed-then-failed payout is corrected by
 * a NEW mirror transaction rather than a delete, so a reversed row still shows its original
 * transaction. Both being present is the correct state, not a duplicate.
 */
class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $recordTitleAttribute = 'external_ref';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
        ];
    }
}
