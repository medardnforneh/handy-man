<?php

namespace App\Filament\Resources\Disputes;

use App\Filament\Resources\Disputes\Pages\ListDisputes;
use App\Filament\Resources\Disputes\Pages\ViewDispute;
use App\Filament\Resources\Disputes\Schemas\DisputeInfolist;
use App\Filament\Resources\Disputes\Tables\DisputesTable;
use App\Models\Dispute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The dispute queue (build plan P6-10). Staff adjudicate; any money effect is a balanced adjustment
 * transaction attributed to the admin (routed through the domain Action), never a row edit.
 */
class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Trust & safety';

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Without this the heading and breadcrumb are the bare UUID — unreadable, and identical between
     * two open disputes. Name the case by what it is about instead.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Dispute) {
            return null;
        }

        $reference = $record->engagement?->job?->reference;

        return trim(__('admin.dispute.category.'.$record->category).($reference ? ' · '.$reference : ''));
    }

    public static function table(Table $table): Table
    {
        return DisputesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DisputeInfolist::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Dispute::query()->whereIn('status', ['open', 'reviewing'])->count();

        return $open > 0 ? (string) $open : null;
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
            'index' => ListDisputes::route('/'),
            'view' => ViewDispute::route('/{record}'),
        ];
    }
}
