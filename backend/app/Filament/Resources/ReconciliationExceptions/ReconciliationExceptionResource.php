<?php

namespace App\Filament\Resources\ReconciliationExceptions;

use App\Filament\Resources\ReconciliationExceptions\Pages\ListReconciliationExceptions;
use App\Filament\Resources\ReconciliationExceptions\Pages\ViewReconciliationException;
use App\Filament\Resources\ReconciliationExceptions\Schemas\ReconciliationExceptionInfolist;
use App\Filament\Resources\ReconciliationExceptions\Tables\ReconciliationExceptionsTable;
use App\Models\ReconciliationException;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The reconciliation queue (build plan P3-09). The dashboard has counted these as "needs a human"
 * since the rework while linking nowhere; this is the somewhere.
 *
 * Never editable: an exception is resolved by posting a balanced adjustment through the domain
 * Action, which stamps the resolver, or by recording it as a false alarm with no ledger movement.
 */
class ReconciliationExceptionResource extends Resource
{
    protected static ?string $model = ReconciliationException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?string $recordTitleAttribute = 'kind';

    public static function getNavigationBadge(): ?string
    {
        $open = ReconciliationException::query()->where('status', ReconciliationException::STATUS_OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof ReconciliationException) {
            return null;
        }

        return __('admin.recon.kind.'.$record->kind);
    }

    public static function table(Table $table): Table
    {
        return ReconciliationExceptionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReconciliationExceptionInfolist::configure($schema);
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
            'index' => ListReconciliationExceptions::route('/'),
            'view' => ViewReconciliationException::route('/{record}'),
        ];
    }
}
