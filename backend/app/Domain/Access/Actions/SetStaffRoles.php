<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Grants and revokes staff access — the most privileged write in the product, since the roles it
 * hands out are what open the admin panel at all ({@see User::canAccessPanel()}).
 *
 * It lives here rather than in the Filament action because the rules below are properties of the
 * system, not of one screen: anything that ever changes a staff role — a console command, a future
 * API, a test — has to obey them, and a check that only exists in a modal is a check that the next
 * caller skips.
 *
 * Spatie roles are staff-only in this system (teams are off; organization roles live in
 * `memberships.role` — see config/permission.php), so syncing them cannot disturb someone's standing
 * inside their own company. The filter to {@see Role::staffRoles()} is belt-and-braces against that
 * assumption quietly changing.
 */
final class SetStaffRoles
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array<int, string>  $roles  role values to end up with; [] revokes all staff access
     *
     * @throws DomainException when the change would lock the actor out or empty the superadmin seat
     */
    public function handle(User $target, array $roles, User $actor, ?string $ip = null): void
    {
        // Nobody edits their own access. Not because a superadmin could not be trusted with it, but
        // because self-service promotion leaves no second person in the story: the whole value of
        // the audit line "A granted B" is that A and B are different people. It also removes the
        // accidental-demotion foot-gun of unticking your own last role and losing the panel.
        if ($target->is($actor)) {
            throw new DomainException(__('admin.staff.error_self'));
        }

        $wanted = array_values(array_intersect(
            $roles,
            array_map(fn (Role $r): string => $r->value, Role::staffRoles()),
        ));

        DB::transaction(function () use ($target, $wanted, $actor, $ip): void {
            $before = $target->roles()->pluck('name')->sort()->values()->all();

            // Leaving zero superadmins is unrecoverable through the UI: this screen is superadmin-only,
            // so the last one demoting the second-to-last would leave the seeder or tinker as the only
            // way back in. Refuse it here rather than let someone discover it afterwards.
            $losingSuperAdmin = in_array(Role::SuperAdmin->value, $before, true)
                && ! in_array(Role::SuperAdmin->value, $wanted, true);

            if ($losingSuperAdmin && $this->superAdminCount() <= 1) {
                throw new DomainException(__('admin.staff.error_last_superadmin'));
            }

            $target->syncRoles($wanted);

            $after = $target->roles()->pluck('name')->sort()->values()->all();

            if ($before === $after) {
                return; // Nothing moved — an audit line saying so would only be noise.
            }

            // P6-02. The context carries both sides because "roles changed" answers nothing six
            // months later: the question asked of an audit trail is always what it changed FROM.
            $this->log->log(
                action: $after === [] ? 'staff.access_revoked' : 'staff.roles_changed',
                subject: $target,
                actorUserId: $actor->id,
                context: ['before' => $before, 'after' => $after],
                ip: $ip,
            );
        });
    }

    /**
     * Locked FOR UPDATE so two concurrent demotions cannot both see a count of two and each let the
     * other proceed. Postgres refuses FOR UPDATE alongside an aggregate, so the rows are fetched and
     * counted here — there are never more than a handful of them.
     */
    private function superAdminCount(): int
    {
        return count(DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::SuperAdmin->value)
            ->select('model_has_roles.model_id')
            ->lockForUpdate()
            ->get()
            ->all());
    }
}
