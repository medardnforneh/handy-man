<?php

namespace App\Filament\Resources\JobOffers;

use App\Filament\Resources\JobOffers\Pages\ListJobOffers;
use App\Filament\Resources\JobOffers\Pages\ViewJobOffer;
use App\Filament\Resources\JobOffers\Schemas\JobOfferInfolist;
use App\Filament\Resources\JobOffers\Tables\JobOffersTable;
use App\Models\JobOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin view of offers (build plan P2-10). Read-only: offers are created and accepted through the
 * API + Actions (state transitions never happen by editing a row — CLAUDE.md rule #8/#9).
 */
class JobOfferResource extends Resource
{
    protected static ?string $model = JobOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Offers';

    public static function infolist(Schema $schema): Schema
    {
        return JobOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobOffersTable::configure($table);
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
            'index' => ListJobOffers::route('/'),
            'view' => ViewJobOffer::route('/{record}'),
        ];
    }
}
