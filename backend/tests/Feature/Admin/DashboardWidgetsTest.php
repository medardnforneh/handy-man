<?php

declare(strict_types=1);

use App\Filament\Widgets\OverviewWidget;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\ReconciliationException;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Livewire\Livewire;

/**
 * P2-10 rework (full-fidelity): the reworked admin dashboard is a single bespoke-view widget that
 * surfaces the marketplace + money at a glance, computed from the models and the ledger.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
    $staff = User::factory()->create(['email' => 'admin+'.uniqid().'@handyman.cm']);
    $staff->assignRole('superadmin');
    $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($staff);
});

it('renders the overview KPIs, a recent engagement, and an open exception', function () {
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->create([
        'customer_party_id' => $customer->party_id,
        'reference' => 'JOB-DASH1',
    ]);
    Engagement::factory()->create(['job_id' => $job->id]);
    ReconciliationException::factory()->create([
        'status' => 'open',
        'detail' => 'Ledger platform_cash short by 2000 vs wallet.',
    ]);

    Livewire::test(OverviewWidget::class)
        ->assertSee('Open jobs')
        ->assertSee('Escrow held')
        ->assertSee('Platform revenue')
        ->assertSee('Reconciliation exceptions')
        ->assertSee('Recent engagements')
        ->assertSee('JOB-DASH1')
        ->assertSee('Needs attention')
        ->assertSee('Settlement mismatch');
});

it('shows the all-clear state when there are no open exceptions', function () {
    Livewire::test(OverviewWidget::class)
        ->assertSee('All clear — the ledger matches');
});
