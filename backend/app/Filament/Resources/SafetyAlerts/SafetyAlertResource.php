<?php

namespace App\Filament\Resources\SafetyAlerts;

use App\Filament\Resources\SafetyAlerts\Pages\ListSafetyAlerts;
use App\Filament\Resources\SafetyAlerts\Tables\SafetyAlertsTable;
use App\Models\SafetyAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The safety-alert queue (build plan P6-04). Panic and check-in-overdue alerts land here for staff to
 * acknowledge and resolve — every resolution attributable to a named admin. Read-only over the data;
 * resolution routes through the domain Action.
 */
class SafetyAlertResource extends Resource
{
    protected static ?string $model = SafetyAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Trust & safety';

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return SafetyAlertsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $open = SafetyAlert::query()->where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
            'index' => ListSafetyAlerts::route('/'),
        ];
    }
}
