<?php

declare(strict_types=1);

use App\Domain\Access\Role;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;

/**
 * The customer stats page renders for the awkward cases, not just the tidy one: a party who is also
 * a provider, and a party who has asked to be forgotten.
 */
beforeEach(function () {
    $this->seed(StaffRolesSeeder::class);
});

function viewingStaff(): User
{
    $staff = User::factory()->create();
    $staff->assignRole(Role::SuperAdmin->value);
    $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $staff;
}

function customerUrl(Party $party): string
{
    return CustomerResource::getUrl('view', ['record' => $party], panel: 'admin');
}

it('renders a customer with jobs, an engagement and money', function () {
    $customer = User::factory()->create();
    $provider = User::factory()->create();
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
        'status' => 'in_progress',
    ]);
    Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'agreed_amount_minor' => 75_000,
    ]);

    $this->actingAs(viewingStaff())
        ->get(customerUrl($customer->party))
        ->assertOk()
        ->assertSee($customer->party->display_name);
});

it('links to the provider page when the same account is both', function () {
    $party = User::factory()->create();
    Job::factory()->create([
        'customer_party_id' => $party->party_id,
        'created_by_user_id' => $party->id,
        'status' => 'open',
    ]);
    $profile = ProviderProfile::factory()->create(['party_id' => $party->party_id]);

    // A party is never only a customer (doc 10); staff who arrived from the Customers list would
    // otherwise answer as if this were half an account.
    $this->actingAs(viewingStaff())
        ->get(customerUrl($party->party))
        ->assertOk()
        ->assertSee(ProviderProfileResource::getUrl(
            'view', ['record' => $profile], panel: 'admin'
        ), escape: false);
});

it('renders an erased customer without pretending they were never here', function () {
    $customer = User::factory()->create();
    Job::factory()->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
        'status' => 'completed',
    ]);
    $customer->party->forceFill(['erased_at' => now()])->save();

    // P1-10: the row survives so ledger FKs hold. The page must say so rather than show blanks.
    $this->actingAs(viewingStaff())
        ->get(customerUrl($customer->party->fresh()))
        ->assertOk()
        ->assertSee(__('admin.party.erased_explainer'));
});
