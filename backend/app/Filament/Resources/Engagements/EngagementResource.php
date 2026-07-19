<?php

namespace App\Filament\Resources\Engagements;

use App\Filament\Resources\Engagements\Pages\ListEngagements;
use App\Filament\Resources\Engagements\Pages\ViewEngagement;
use App\Filament\Resources\Engagements\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Engagements\Schemas\EngagementInfolist;
use App\Filament\Resources\Engagements\Tables\EngagementsTable;
use App\Models\Engagement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin view of engagements (build plan P2-10). The engagement itself is read-only — it is created
 * by AcceptOfferAction and its money/state are owned by Actions. The one supported mutation is
 * manual (re)assignment of workers, done through the {@see AssignmentsRelationManager}, which routes
 * to the same AssignWorker/UnassignWorker Actions the API uses (so the org boundary, one-lead, and
 * no-double-booking rules all still apply).
 */
class EngagementResource extends Resource
{
    protected static ?string $model = Engagement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    public static function infolist(Schema $schema): Schema
    {
        return EngagementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngagementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AssignmentsRelationManager::class,
        ];
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
            'index' => ListEngagements::route('/'),
            'view' => ViewEngagement::route('/{record}'),
        ];
    }
}
