<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SetStaffRoles;
use App\Domain\Access\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;

/**
 * Granting staff access is the one write that can hand someone every other write, so the rules
 * around it are tested at the action rather than through the screen: a check that only a modal
 * performs is a check the next caller skips.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
});

function superadmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SuperAdmin->value);

    return $user;
}

it('grants a role to an ordinary account and records who did it', function () {
    $actor = superadmin();
    $target = User::factory()->create();

    app(SetStaffRoles::class)->handle($target, [Role::Support->value], $actor, '10.0.0.1');

    expect($target->fresh()->hasRole(Role::Support->value))->toBeTrue();

    // P6-02: the audit line has to say what it changed FROM, or it answers nothing later.
    $log = ActivityLog::query()->where('action', 'staff.roles_changed')->sole();
    expect($log->actor_user_id)->toBe($actor->id)
        ->and($log->subject_id)->toBe($target->id)
        ->and($log->context['before'])->toBe([])
        ->and($log->context['after'])->toBe([Role::Support->value])
        ->and($log->ip_address)->toBe('10.0.0.1');
});

it('refuses to let anyone edit their own access', function () {
    $actor = superadmin();

    expect(fn () => app(SetStaffRoles::class)->handle($actor, [Role::Support->value], $actor))
        ->toThrow(DomainException::class);

    // Unchanged: the refusal is not a partial application.
    expect($actor->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('refuses to demote the last superadmin, which would lock everyone out of access management', function () {
    $actor = superadmin();
    $other = superadmin();

    // Two exist, so demoting one is allowed.
    app(SetStaffRoles::class)->handle($other, [Role::Support->value], $actor);
    expect($other->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse();

    // $actor is now the only one left, and cannot be demoted by anyone.
    $third = superadmin();
    app(SetStaffRoles::class)->handle($third, [], $actor); // still fine: not a superadmin loss for $actor

    expect(fn () => app(SetStaffRoles::class)->handle($actor, [Role::Support->value], $other))
        ->toThrow(DomainException::class);
});

it('revoking staff access leaves the person\'s own account alone', function () {
    $actor = superadmin();
    $target = User::factory()->create();
    $target->assignRole(Role::Verifier->value);

    app(SetStaffRoles::class)->handle($target, [], $actor);

    $target = $target->fresh();
    expect($target->roles)->toHaveCount(0)
        ->and($target->exists)->toBeTrue()
        ->and($target->status)->toBe('active')
        ->and($target->party)->not->toBeNull();

    expect(ActivityLog::query()->where('action', 'staff.access_revoked')->count())->toBe(1);
});

it('ignores role names that are not staff roles', function () {
    $actor = superadmin();
    $target = User::factory()->create();

    // Organization roles live in `memberships.role`, not Spatie — they must not be grantable here.
    app(SetStaffRoles::class)->handle($target, [Role::OrgOwner->value, Role::Support->value], $actor);

    expect($target->fresh()->roles->pluck('name')->all())->toBe([Role::Support->value]);
});

it('writes no audit line when the roles did not actually change', function () {
    $actor = superadmin();
    $target = User::factory()->create();
    $target->assignRole(Role::Support->value);

    app(SetStaffRoles::class)->handle($target, [Role::Support->value], $actor);

    expect(ActivityLog::query()->where('action', 'like', 'staff.%')->count())->toBe(0);
});
