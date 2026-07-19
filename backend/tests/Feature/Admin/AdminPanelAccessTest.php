<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\StaffRolesSeeder;

/**
 * P1-09 acceptance: the /admin panel is staff-only and 2FA is MANDATORY — you cannot reach it
 * without enrolling in two-factor authentication.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
});

function makeStaff(): User
{
    $staff = User::factory()->create(['email' => 'admin+'.uniqid().'@handyman.cm']);
    $staff->assignRole('superadmin'); // global staff role (Spatie teams off)

    return $staff;
}

it('forbids the admin panel to non-staff users', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get('/admin')->assertForbidden(); // canAccessPanel = false
});

it('cannot reach the dashboard without 2FA enrolled — redirects a staff member to set it up', function () {
    $staff = makeStaff();
    expect($staff->getAppAuthenticationSecret())->toBeNull();

    // Staff passes the role gate but has no 2FA → forced to the setup, not the dashboard.
    $this->actingAs($staff)->get('/admin')->assertRedirect();
});

it('lets an enrolled staff member reach the dashboard', function () {
    $staff = makeStaff();
    $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP'); // a stored TOTP secret == enrolled

    $this->actingAs($staff)->get('/admin')->assertOk();
});

it('redirects an unauthenticated visitor to the admin login', function () {
    $this->get('/admin')->assertRedirect();
    $this->get('/admin/login')->assertOk();
});
