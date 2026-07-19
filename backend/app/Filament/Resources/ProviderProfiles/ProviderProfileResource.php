<?php

namespace App\Filament\Resources\ProviderProfiles;

use App\Filament\Resources\ProviderProfiles\Pages\CreateProviderProfile;
use App\Filament\Resources\ProviderProfiles\Pages\EditProviderProfile;
use App\Filament\Resources\ProviderProfiles\Pages\ListProviderProfiles;
use App\Filament\Resources\ProviderProfiles\Schemas\ProviderProfileForm;
use App\Filament\Resources\ProviderProfiles\Tables\ProviderProfilesTable;
use App\Models\ProviderProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderProfileResource extends Resource
{
    protected static ?string $model = ProviderProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProviderProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderProfiles::route('/'),
            'create' => CreateProviderProfile::route('/create'),
            'edit' => EditProviderProfile::route('/{record}/edit'),
        ];
    }
}
