<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Tables;

use App\Domain\Access\Role;
use App\Filament\Resources\Staff\Support\StaffRoleField;
use App\Models\User;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The admin roster. Short enough that it needs no cleverness — what it needs is to make the two
 * questions it exists for answerable at a glance: who has the keys, and is anyone holding keys they
 * have stopped using.
 */
final class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.display_name')
                    ->label(__('admin.party.name'))
                    ->searchable()
                    ->sortable()
                    // "(you)" so nobody wonders why their own row has no buttons on it.
                    ->description(fn (User $record): ?string => $record->is(Filament::auth()->user())
                        ? __('admin.staff.you')
                        : null),

                TextColumn::make('phone_e164')
                    ->label(__('admin.safety.phone'))
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label(__('admin.staff.roles'))
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn (string $state): string => __('admin.staff.role.'.$state))
                    // Superadmin in red: not an alarm, a reminder that the row can grant itself
                    // anything, so it should be a short list.
                    ->color(fn (string $state): string => $state === Role::SuperAdmin->value ? 'danger' : 'info'),

                // The dormant-account signal. A staff account nobody has signed into for months is
                // the one most worth revoking, and the one nobody thinks of.
                TextColumn::make('last_login_at')
                    ->label(__('admin.staff.last_seen'))
                    ->dateTime('d M Y H:i')
                    ->placeholder(__('admin.staff.never'))
                    ->sortable(),

                // Panel access without a second factor is the weak link in the roster, so it is a
                // column rather than something you learn by opening each account.
                IconColumn::make('app_authentication_secret')
                    ->label(__('admin.staff.two_factor'))
                    ->boolean()
                    ->state(fn (User $record): bool => $record->app_authentication_secret !== null),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('admin.staff.roles'))
                    ->options(StaffRoleField::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),

                Filter::make('no_two_factor')
                    ->label(__('admin.staff.no_two_factor'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('app_authentication_secret')),
            ])
            ->defaultSort('last_login_at', 'desc')
            ->recordActions([
                Action::make('roles')
                    ->label(__('admin.staff.change_roles'))
                    ->icon(LucideIcon::Key)
                    ->modalHeading(fn (User $record): string => __('admin.staff.change_roles_for', [
                        'name' => $record->party->display_name,
                    ]))
                    ->modalDescription(__('admin.staff.change_roles_hint'))
                    ->schema([StaffRoleField::make()])
                    ->fillForm(fn (User $record): array => [
                        'roles' => $record->roles->pluck('name')->all(),
                    ])
                    ->visible(fn (User $record): bool => ! $record->is(Filament::auth()->user()))
                    ->action(fn (User $record, array $data) => StaffRoleField::apply($record, $data['roles'] ?? [])),

                Action::make('revoke')
                    ->label(__('admin.staff.revoke'))
                    ->icon(LucideIcon::Ban)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => __('admin.staff.revoke_for', [
                        'name' => $record->party->display_name,
                    ]))
                    // Says what revoking does NOT do: their customer and provider life on the
                    // platform is untouched, and staff have sometimes been both.
                    ->modalDescription(__('admin.staff.revoke_hint'))
                    ->visible(fn (User $record): bool => ! $record->is(Filament::auth()->user()))
                    ->action(fn (User $record) => StaffRoleField::apply($record, [])),
            ]);
    }
}
