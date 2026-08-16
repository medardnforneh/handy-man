<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff;

use App\Domain\Access\Role;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Who can get into this panel, and what they can do once they are in.
 *
 * Until now the answer lived in DemoSeeder and tinker: staff access was granted by editing code and
 * re-running a seeder, which means it was also never revoked — the fastest thing to need doing when
 * someone leaves is the thing that had no button. An access list nobody can read is also an access
 * list nobody audits.
 *
 * Deliberately NOT a user-creation screen. Everyone signs in with the same phone OTP, so there are
 * no credentials to mint here; a staff member is an ordinary account that has been given a role.
 * Granting therefore starts by finding a real person who has already signed in at least once, which
 * has the useful property that the account being promoted is one that demonstrably belongs to them.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Identity';

    /** Last in Identity: the rarest visit, and the one nobody should land on by accident. */
    protected static ?int $navigationSort = 95;

    /**
     * Only a superadmin sees this at all — support and verifier staff have no business knowing the
     * shape of the admin roster, and less business editing it. Filament asks this for the nav item,
     * the list page and every action on it, so one override closes all three doors.
     */
    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasRole(Role::SuperAdmin->value);
    }

    /** Staff ARE the users with a Spatie role; there is no separate table and should not be one. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles')
            ->with(['party', 'roles']);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.staff.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.staff.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.staff.plural');
    }

    /** An account is not created, edited or deleted here — only its roles change. */
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

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
        ];
    }
}
