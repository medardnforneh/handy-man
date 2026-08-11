<?php

namespace App\Filament\Resources\Parties;

use App\Filament\Resources\Parties\Pages\ListParties;
use App\Filament\Resources\Parties\Pages\ViewParty;
use App\Filament\Resources\Parties\Schemas\PartyInfolist;
use App\Filament\Resources\Parties\Tables\PartiesTable;
use App\Models\Party;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * People and organisations on the platform. Read-only by design: a party's identity is established
 * through OTP and verification documents, not by an admin typing a name, and its verification tier
 * is raised only by approving a real document (P6-03) — a form here would be a way to hand someone
 * a tier they never proved.
 *
 * Erased parties (P1-10 crypto-shred) keep their row so ledger FKs survive; the list marks them
 * rather than hiding them, because a staff member searching for a name needs to learn that the
 * person asked to be forgotten, not that they never existed.
 */
class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function table(Table $table): Table
    {
        return PartiesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartyInfolist::configure($schema);
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
            'index' => ListParties::route('/'),
            'view' => ViewParty::route('/{record}'),
        ];
    }
}
