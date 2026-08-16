<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Support;

use App\Domain\Access\Actions\SetStaffRoles;
use App\Domain\Access\Role;
use App\Models\User;
use DomainException;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

/**
 * The role picker, shared by the grant form and the change-roles modal so the two cannot drift into
 * offering different sets of powers.
 *
 * Every role carries a description of what it actually lets someone do. Handing out access is the
 * one place where a bare list of nouns is dangerous: `finance_admin` sounds like a job title and
 * reads as harmless, and the person ticking it deserves to see "can move money" before they do.
 */
final class StaffRoleField
{
    public static function make(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->label(__('admin.staff.roles'))
            ->options(self::options())
            ->descriptions(self::descriptions())
            ->required()
            ->bulkToggleable(false)
            ->columns(1);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (Role::staffRoles() as $role) {
            $options[$role->value] = __('admin.staff.role.'.$role->value);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (Role::staffRoles() as $role) {
            $descriptions[$role->value] = __('admin.staff.role_hint.'.$role->value);
        }

        return $descriptions;
    }

    /**
     * Runs the change and reports it. The domain action refuses some changes outright (P6-02 and
     * the last-superadmin rule); those come back as a plain message rather than a stack trace,
     * because every one of them is a decision a person needs to read, not a fault.
     *
     * @param  array<int, string>  $roles
     */
    public static function apply(User $target, array $roles): void
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            app(SetStaffRoles::class)->handle($target, $roles, $actor, request()->ip());
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $name = $target->party->display_name;

        Notification::make()
            ->title($roles === []
                ? __('admin.staff.revoked_toast', ['name' => $name])
                : __('admin.staff.changed_toast', ['name' => $name]))
            ->success()
            ->send();
    }

    /**
     * Candidates for a grant: real accounts that are not already staff.
     *
     * Searching is by phone or name, and never lists everyone — an empty search returning the whole
     * user table would turn a promotion form into a directory of every customer on the platform.
     *
     * @return array<string, string>
     */
    public static function searchCandidates(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 3) {
            return [];
        }

        return User::query()
            ->whereDoesntHave('roles')
            // A suspended or closed account cannot be handed the panel: whatever got it suspended
            // is a reason not to, and an erased party (P1-10) has no name left to grant it to.
            ->where('status', 'active')
            ->whereHas('party', fn ($q) => $q->whereNull('erased_at'))
            ->where(fn ($q) => $q
                ->where('phone_e164', 'like', '%'.$search.'%')
                ->orWhereHas('party', fn ($p) => $p->where('display_name', 'ilike', '%'.$search.'%')))
            ->with('party')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (User $u): array => [
                $u->id => $u->party->display_name.' — '.$u->phone_e164,
            ])
            ->all();
    }

    /** Keeps the chosen person visible in the select after the search box is cleared. */
    public static function labelFor(?string $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $user = User::query()->with('party')->find($userId);

        return $user === null ? null : $user->party->display_name.' — '.$user->phone_e164;
    }

    /**
     * Guarded against a hand-typed id: the select is searchable, so its value is user input.
     *
     * @param  array<int, string>  $roles
     */
    public static function grant(string $userId, array $roles): void
    {
        $target = User::query()->whereDoesntHave('roles')->find($userId);

        if ($target === null) {
            Notification::make()->title(__('admin.staff.error_not_found'))->danger()->send();

            return;
        }

        self::apply($target, $roles);
    }
}
