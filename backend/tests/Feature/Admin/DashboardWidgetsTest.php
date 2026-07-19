<?php

declare(strict_types=1);

use App\Filament\Widgets\PlatformStatsWidget;
use App\Filament\Widgets\RecentEngagementsWidget;
use App\Filament\Widgets\ReconciliationExceptionsWidget;
use App\Models\Engagement;
use App\Models\ReconciliationException;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Livewire\Livewire;

/**
 * P2-10 rework: the reworked admin dashboard surfaces the marketplace + money at a glance via real
 * widgets computed from the models and the ledger.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
    $staff = User::factory()->create(['email' => 'admin+'.uniqid().'@handyman.cm']);
    $staff->assignRole('superadmin');
    $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->actingAs($staff);
});

it('renders the platform stats widget with the KPI labels', function () {
    Livewire::test(PlatformStatsWidget::class)
        ->assertSee('Open jobs')
        ->assertSee('Escrow held')
        ->assertSee('Platform revenue')
        ->assertSee('Reconciliation exceptions');
});

it('shows recent engagements in the widget table', function () {
    $engagement = Engagement::factory()->create();

    Livewire::test(RecentEngagementsWidget::class)
        ->assertCanSeeTableRecords([$engagement]);
});

it('surfaces open reconciliation exceptions and hides resolved ones', function () {
    $open = ReconciliationException::factory()->create(['status' => 'open']);
    $resolved = ReconciliationException::factory()->create(['status' => 'resolved']);

    Livewire::test(ReconciliationExceptionsWidget::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$resolved]);
});
