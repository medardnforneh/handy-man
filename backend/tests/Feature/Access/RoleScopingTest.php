<?php

declare(strict_types=1);

use App\Domain\Access\Capabilities\AcceptPaidJob;
use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Access\Facts\FactResult;
use App\Domain\Access\PreconditionUnmetException;
use App\Domain\Access\Role;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * P0-18 acceptance (doc 10): Spatie roles exist ONLY for org-internal + staff/admin use, and no
 * role gates the customer/provider section split.
 */
it('defines no customer or provider role — the section split is never a permission', function () {
    $values = array_map(fn (Role $r) => $r->value, Role::cases());

    expect($values)->not->toContain('customer')
        ->and($values)->not->toContain('provider');

    // Every defined role is either an org-internal role or a staff role — nothing else.
    foreach (Role::cases() as $role) {
        expect($role->isOrganizationRole() || $role->isStaffRole())->toBeTrue();
    }
});

it('does not gate access-model capabilities on roles — a roleless user is judged on facts', function () {
    // A brand-new user with ZERO roles.
    $user = User::factory()->create();
    expect($user->getRoleNames())->toBeEmpty();

    // With identity unverified, an on-site accept fails on the FACT (precondition_unmet), and this
    // has nothing to do with roles — the capability never consults Spatie.
    app(FactDeriver::class)->register(
        Fact::IdentityVerified,
        fn (): FactResult => FactResult::tier(0),
    );

    expect(fn () => app(AcceptPaidJob::class)->authorize($user, ['engagement_mode' => 'onsite']))
        ->toThrow(PreconditionUnmetException::class);

    // Grant it a verified identity fact (still no roles) → it now passes. Roles were never involved.
    // (Re-registering changes the source; the cached fact is invalidated exactly as a real event
    //  handler would do on "verification approved".)
    app(FactDeriver::class)->register(
        Fact::IdentityVerified,
        fn (): FactResult => FactResult::tier(2),
    );
    app(FactDeriver::class)->forget($user, Fact::IdentityVerified);

    expect(fn () => app(AcceptPaidJob::class)->authorize($user, ['engagement_mode' => 'onsite']))
        ->not->toThrow(PreconditionUnmetException::class);
});

it('seeds the staff roles as global (team-independent)', function () {
    $this->seed(StaffRolesSeeder::class);

    foreach (Role::staffRoles() as $role) {
        expect(SpatieRole::where('name', $role->value)->exists())->toBeTrue();
    }

    // No org roles are seeded globally — they are created per organization later.
    expect(SpatieRole::where('name', Role::OrgDispatcher->value)->exists())->toBeFalse();
});

it('attaches the HasRoles capability to the User model', function () {
    $user = User::factory()->create();

    expect(method_exists($user, 'assignRole'))->toBeTrue()
        ->and(method_exists($user, 'hasRole'))->toBeTrue();
});
