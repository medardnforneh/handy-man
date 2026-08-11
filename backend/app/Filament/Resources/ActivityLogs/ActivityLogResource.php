<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The audit trail (P6-02). These rows have been written since verification review shipped and there
 * has never been a way to read them in the panel — an insider-threat control nobody can consult is
 * only half a control.
 *
 * Append-only at the database (a trigger forbids UPDATE and DELETE), so the resource offers no
 * create, edit or delete: the UI simply matches what the table already enforces.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?string $recordTitleAttribute = 'action';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
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
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
