<?php

namespace App\Filament\Resources\PaymentIntents;

use App\Filament\Resources\PaymentIntents\Pages\ListPaymentIntents;
use App\Filament\Resources\PaymentIntents\Tables\PaymentIntentsTable;
use App\Models\PaymentIntent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Collections in flight and their outcome (P3-04/05/06). Strictly read-only: a payment's status is
 * whatever the gateway says it is — the webhook and the reconciliation poller both re-fetch the
 * authoritative status rather than trusting a callback body, and a staff member editing this row
 * would be asserting a fact about someone else's money.
 *
 * Nothing here can be created either: an intent exists because a customer started a payment.
 */
class PaymentIntentResource extends Resource
{
    protected static ?string $model = PaymentIntent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $recordTitleAttribute = 'external_ref';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return PaymentIntentsTable::configure($table);
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
            'index' => ListPaymentIntents::route('/'),
        ];
    }
}
