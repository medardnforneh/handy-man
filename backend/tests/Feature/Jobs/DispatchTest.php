<?php

declare(strict_types=1);

use App\Domain\Jobs\Actions\DispatchJob;
use App\Domain\Jobs\Actions\RedispatchStaleJobs;
use App\Domain\Jobs\JobStatus;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Skill;

/**
 * P8-03 acceptance (doc 01/05): dispatch mode — rank providers, fan out to the top N, and cascade to
 * the next batch when offers expire.
 */

/**
 * @return array{job: Job, skill: Skill}
 */
function dispatchJobWithProviders(int $providerCount): array
{
    $skill = Skill::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'skill_id' => $skill->id, 'assignment_mode' => 'dispatch',
    ]);

    for ($i = 0; $i < $providerCount; $i++) {
        $profile = ProviderProfile::factory()->create();
        ProviderSkill::factory()->create(['provider_profile_id' => $profile->id, 'skill_id' => $skill->id]);
    }

    return ['job' => $job, 'skill' => $skill];
}

it('fans out to the top N ranked providers (P8-03)', function () {
    ['job' => $job] = dispatchJobWithProviders(6);

    expect(app(DispatchJob::class)->handle($job, 3))->toBe(3);
    expect(JobOffer::query()->where('job_id', $job->id)->count())->toBe(3)
        ->and($job->fresh()->status)->toBe(JobStatus::Offered);
});

it('cascades to the next batch when the offers expire (P8-03)', function () {
    ['job' => $job] = dispatchJobWithProviders(6);
    app(DispatchJob::class)->handle($job, 3);

    // All three offers expire without acceptance.
    JobOffer::query()->where('job_id', $job->id)->update(['status' => 'expired', 'expires_at' => now()->subHour()]);

    // The cascade re-dispatches to the next three providers.
    expect(app(RedispatchStaleJobs::class)->handle())->toBe(3);
    expect(JobOffer::query()->where('job_id', $job->id)->count())->toBe(6);
});
