<?php

declare(strict_types=1);

use App\Models\Engagement;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;

/**
 * P2-10 acceptance: staff can browse jobs, offers and engagements in the admin panel. The resources
 * are read-only (no create/edit routes) so status/money can't be mutated outside the state
 * machines/Actions; the one mutation — manual (re)assignment — lives on the engagement's assignments
 * relation manager and routes through the domain Actions.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
});

function enrolledAdmin(): User
{
    $staff = User::factory()->create(['email' => 'admin+'.uniqid().'@handyman.cm']);
    $staff->assignRole('superadmin');
    $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP'); // enrolled in 2FA

    return $staff;
}

it('lists jobs, offers and engagements for enrolled staff', function () {
    Engagement::factory()->create(); // seeds a job + offer + engagement graph

    $this->actingAs(enrolledAdmin());

    $this->get('/admin/jobs')->assertOk();
    $this->get('/admin/job-offers')->assertOk();
    $this->get('/admin/engagements')->assertOk();
});

it('shows the view pages for each record', function () {
    $engagement = Engagement::factory()->create();

    $this->actingAs(enrolledAdmin());

    $this->get("/admin/jobs/{$engagement->job_id}")->assertOk();
    $this->get("/admin/job-offers/{$engagement->offer_id}")->assertOk();
    $this->get("/admin/engagements/{$engagement->id}")->assertOk();
});

it('exposes no create routes (read-only resources)', function () {
    $this->actingAs(enrolledAdmin());

    // getPages() omits 'create', so the route does not exist.
    $this->get('/admin/jobs/create')->assertNotFound();
    $this->get('/admin/engagements/create')->assertNotFound();
});

it('keeps the marketplace out of the panel for non-staff', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/jobs')->assertForbidden();
});
