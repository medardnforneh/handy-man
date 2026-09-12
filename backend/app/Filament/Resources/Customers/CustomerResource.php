<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Party;
use BackedEnum;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The people who ask for work.
 *
 * The panel had Provider Profiles and nothing facing the other half of the marketplace, so a support
 * question that starts "a customer says…" had no screen to start from: Parties lists everyone with
 * no way to tell a customer from a provider from a staff account, and every other resource is keyed
 * by job or engagement rather than by person.
 *
 * A customer is a party that has POSTED A JOB. That is the only definition available and the right
 * one — there is no customer profile to have, the way a provider has one. Someone becomes a customer
 * by asking for work, which also means this list cannot show a signed-up account that has never
 * done anything, and should not: there would be nothing to support them about.
 *
 * A party can appear here AND under Provider Profiles. Providers hire other providers, and hiding
 * that would make the busiest accounts on the platform look like two different people.
 *
 * Read-only, for the same reason PartyResource is (see its docblock): identity is established by OTP
 * and by approving real documents, never by an admin typing into a form. What staff need here is to
 * find a person and see what has actually happened to them.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|BackedEnum|null $navigationIcon = LucideIcon::Users;

    /** Beside Provider Profiles, ungrouped: the two halves of the marketplace read as a pair. */
    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'display_name';

    /**
     * Only parties with at least one job. `withCount` here rather than in the table so the count is
     * sortable and filterable, and so the list costs one query instead of one per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('jobs')
            ->withCount('jobs')
            ->with(['user', 'providerProfile']);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
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

    public static function getNavigationLabel(): string
    {
        return __('admin.customers.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.customers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.customers.plural');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
