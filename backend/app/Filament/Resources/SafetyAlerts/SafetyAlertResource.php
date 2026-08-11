<?php

namespace App\Filament\Resources\SafetyAlerts;

use App\Filament\Resources\SafetyAlerts\Pages\ListSafetyAlerts;
use App\Filament\Resources\SafetyAlerts\Pages\ViewSafetyAlert;
use App\Filament\Resources\SafetyAlerts\Schemas\SafetyAlertInfolist;
use App\Filament\Resources\SafetyAlerts\Tables\SafetyAlertsTable;
use App\Models\SafetyAlert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

    /**
     * A panic alert headed by a bare UUID tells a responder nothing. Name it by what happened and
     * to whom.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof SafetyAlert) {
            return null;
        }

        $who = $record->user?->party?->display_name;

        return trim(__('admin.safety.kind.'.$record->kind->value).($who ? ' · '.$who : ''));
    }

    public static function infolist(Schema $schema): Schema
    {
        return SafetyAlertInfolist::configure($schema);
    }

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
            'view' => ViewSafetyAlert::route('/{record}'),
        ];
    }
}
