<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderProfiles;

use App\Filament\Resources\ProviderProfiles\Pages\CreateProviderProfile;
use App\Filament\Resources\ProviderProfiles\Pages\EditProviderProfile;
use App\Filament\Resources\ProviderProfiles\Pages\ListProviderProfiles;
use App\Filament\Resources\ProviderProfiles\Pages\ViewProviderProfile;
use App\Filament\Resources\ProviderProfiles\Schemas\ProviderProfileForm;
use App\Filament\Resources\ProviderProfiles\Schemas\ProviderProfileInfolist;
use App\Filament\Resources\ProviderProfiles\Tables\ProviderProfilesTable;
use App\Models\ProviderProfile;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The people who do the work — the other half of the marketplace from Customers, and labelled to
 * match.
 *
 * It read "Provider Profiles", which is the table's name rather than the thing's: staff look for a
 * provider, not for a profile, and the pairing with Customers only reads as a pair if both are named
 * after people. The model underneath is still ProviderProfile, because a provider IS a party with
 * one of these — that is precisely what distinguishes them from a customer, who has no profile at all
 * and is defined by having posted a job.
 */
class ProviderProfileResource extends Resource
{
    protected static ?string $model = ProviderProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    /** Immediately before Customers, so the two halves of the marketplace sit together. */
    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'headline';

    /** The list and every stat on the detail page read through the party, so load it once. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['party.user']);
    }

    public static function form(Schema $schema): Schema
    {
        return ProviderProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderProfilesTable::configure($table);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.provider.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.provider.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.provider.plural');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProviderProfiles::route('/'),
            'create' => CreateProviderProfile::route('/create'),
            'view' => ViewProviderProfile::route('/{record}'),
            'edit' => EditProviderProfile::route('/{record}/edit'),
        ];
    }
}
