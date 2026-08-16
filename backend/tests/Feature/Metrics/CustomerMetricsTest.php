<?php

declare(strict_types=1);

use App\Domain\Metrics\CustomerMetrics;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\User;

/**
 * The customer-side counterpart of the provider metrics: what staff are shown about someone who
 * hires rather than works. Two rules carry the weight — drafts are not requests, and a rate from a
 * handful of jobs is withheld rather than printed.
 */
function customerWithJobs(int $count, array $attributes = []): User
{
    $user = User::factory()->create();
    Job::factory()->count($count)->create(array_merge([
        'customer_party_id' => $user->party_id,
        'created_by_user_id' => $user->id,
    ], $attributes));

    return $user;
}

it('does not count drafts as requests', function () {
    $user = customerWithJobs(3, ['status' => 'open']);
    Job::factory()->count(2)->create([
        'customer_party_id' => $user->party_id,
        'created_by_user_id' => $user->id,
        'status' => 'draft',
    ]);

    // A draft has never been seen by a provider and cost nobody a lead credit.
    expect(app(CustomerMetrics::class)->forParty($user->party_id)['jobs_posted'])->toBe(3);
});

it('withholds the hire rate below the sample floor', function () {
    $user = customerWithJobs(2, ['status' => 'open']);

    $m = app(CustomerMetrics::class)->forParty($user->party_id);

    expect($m['hire_rate'])->toBeNull()
        ->and($m['hire_sample'])->toBe(0); // both are still awaiting a provider
});

it('excludes cancelled and still-open requests from the hire denominator', function () {
    $user = User::factory()->create();
    $provider = User::factory()->create();

    // Six that ran their course, of which four found a provider.
    $engaged = Job::factory()->count(4)->create([
        'customer_party_id' => $user->party_id,
        'created_by_user_id' => $user->id,
        'status' => 'completed',
    ]);
    foreach ($engaged as $job) {
        Engagement::factory()->create([
            'job_id' => $job->id,
            'provider_party_id' => $provider->party_id,
            'agreed_amount_minor' => 50_000,
            'completed_at' => now(),
        ]);
    }
    Job::factory()->count(2)->create([
        'customer_party_id' => $user->party_id,
        'created_by_user_id' => $user->id,
        'status' => 'closed',
    ]);

    // Noise that must not move the rate: a cancelled request is not a failure to hire, and one
    // still open has not finished asking.
    Job::factory()->create(['customer_party_id' => $user->party_id, 'created_by_user_id' => $user->id, 'status' => 'cancelled']);
    Job::factory()->create(['customer_party_id' => $user->party_id, 'created_by_user_id' => $user->id, 'status' => 'open']);

    $m = app(CustomerMetrics::class)->forParty($user->party_id);

    expect($m['hire_sample'])->toBe(6)
        ->and($m['jobs_hired'])->toBe(4)
        ->and($m['hire_rate'])->toBe(round(4 / 6, 4))
        ->and($m['jobs_cancelled'])->toBe(1)
        ->and($m['jobs_awaiting'])->toBe(1)
        ->and($m['completed'])->toBe(4);
});

it('reports money released rather than money agreed', function () {
    $user = User::factory()->create();
    $provider = User::factory()->create();
    $job = Job::factory()->create([
        'customer_party_id' => $user->party_id,
        'created_by_user_id' => $user->id,
        'status' => 'in_progress',
    ]);
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
        'agreed_amount_minor' => 100_000,
    ]);

    Milestone::factory()->create(['engagement_id' => $engagement->id, 'position' => 0, 'amount_minor' => 40_000, 'status' => 'approved']);
    Milestone::factory()->create(['engagement_id' => $engagement->id, 'position' => 1, 'amount_minor' => 60_000, 'status' => 'pending']);

    $m = app(CustomerMetrics::class)->forParty($user->party_id);

    // Agreed is the promise; released is what reached the provider. The gap is the answer when a
    // provider calls to ask where their money is.
    expect($m['agreed_minor'])->toBe(100_000)
        ->and($m['released_minor'])->toBe(40_000);
});

it('counts distinct providers and how many were used more than once', function () {
    $user = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    foreach ([$a, $a, $b] as $provider) {
        $job = Job::factory()->create([
            'customer_party_id' => $user->party_id,
            'created_by_user_id' => $user->id,
            'status' => 'completed',
        ]);
        Engagement::factory()->create([
            'job_id' => $job->id,
            'provider_party_id' => $provider->party_id,
            'completed_at' => now(),
        ]);
    }

    $m = app(CustomerMetrics::class)->forParty($user->party_id);

    expect($m['providers_used'])->toBe(2)
        ->and($m['repeat_providers'])->toBe(1);
});

it('leaves another customer\'s jobs out of the figures', function () {
    $mine = customerWithJobs(2, ['status' => 'open']);
    customerWithJobs(5, ['status' => 'open']);

    expect(app(CustomerMetrics::class)->forParty($mine->party_id)['jobs_posted'])->toBe(2);
});
