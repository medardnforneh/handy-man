<?php

declare(strict_types=1);

use App\Domain\Access\Role;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;

/**
 * Who can see the access roster. Support and verifier staff are staff, but the list of who holds the
 * keys — and the buttons that hand them out — is superadmin-only: the resource is the one place in
 * the panel where reading is itself a privilege, since it maps the people worth compromising.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
});

function enrolledStaffWith(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP'); // enrolled, so 2FA does not redirect us

    return $user;
}

it('lets a superadmin open the staff roster', function () {
    $this->actingAs(enrolledStaffWith(Role::SuperAdmin->value))
        ->get('/admin/staff')
        ->assertOk();
});

it('hides the staff roster from support staff', function () {
    $this->actingAs(enrolledStaffWith(Role::Support->value))
        ->get('/admin/staff')
        ->assertForbidden();
});

it('hides the staff roster from a verifier', function () {
    $this->actingAs(enrolledStaffWith(Role::Verifier->value))
        ->get('/admin/staff')
        ->assertForbidden();
});

it('keeps the roster out of the navigation for non-superadmin staff', function () {
    $this->actingAs(enrolledStaffWith(Role::FinanceAdmin->value))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('/admin/staff');
});
