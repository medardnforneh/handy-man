<?php

declare(strict_types=1);

use App\Domain\Jobs\JobProgress;
use App\Domain\Jobs\JobStatus;
use App\Models\Job;

/**
 * The job's own status follows what happened to it (JobProgress). Found by the remote end-to-end
 * walk-through: every lifecycle state past `engaged` was defined and none was ever entered.
 */
function jobAt(JobStatus $status): Job
{
    return Job::factory()->remote()->status($status)->create();
}

it('walks a job from engaged to completed through the states nobody reported', function () {
    $job = jobAt(JobStatus::Engaged);

    app(JobProgress::class)->advanceTo($job, JobStatus::Completed);

    expect($job->fresh()->status)->toBe(JobStatus::Completed);
});

it('takes the biggest legal step rather than inventing an "en route" it was never told about', function () {
    $job = jobAt(JobStatus::Engaged);

    app(JobProgress::class)->advanceTo($job, JobStatus::InProgress);

    expect($job->fresh()->status)->toBe(JobStatus::InProgress);
});

it('moves on the way to en_route, and a later start to in_progress', function () {
    $job = jobAt(JobStatus::Scheduled);
    $progress = app(JobProgress::class);

    $progress->advanceTo($job, JobStatus::EnRoute);
    expect($job->fresh()->status)->toBe(JobStatus::EnRoute);

    $progress->advanceTo($job, JobStatus::InProgress);
    expect($job->fresh()->status)->toBe(JobStatus::InProgress);
});

it('ignores a signal that arrives after the job is already past it', function () {
    $job = jobAt(JobStatus::WorkSubmitted);

    app(JobProgress::class)->advanceTo($job, JobStatus::InProgress);

    expect($job->fresh()->status)->toBe(JobStatus::WorkSubmitted);
});

it('leaves a cancelled, closed or open job alone — those are decided elsewhere', function () {
    foreach ([JobStatus::Cancelled, JobStatus::Closed, JobStatus::Open] as $status) {
        $job = jobAt($status);
        app(JobProgress::class)->advanceTo($job, JobStatus::Completed);
        expect($job->fresh()->status)->toBe($status);
    }
});

it('reopens submitted work when it is sent back, and only then', function () {
    $submitted = jobAt(JobStatus::WorkSubmitted);
    $inProgress = jobAt(JobStatus::InProgress);

    app(JobProgress::class)->reopen($submitted);
    app(JobProgress::class)->reopen($inProgress);

    expect($submitted->fresh()->status)->toBe(JobStatus::InProgress)
        ->and($inProgress->fresh()->status)->toBe(JobStatus::InProgress);
});

it('freezes a job as disputed and settles it by whether the work was finished', function () {
    $progress = app(JobProgress::class);

    $unfinished = jobAt(JobStatus::InProgress);
    $progress->dispute($unfinished);
    expect($unfinished->fresh()->status)->toBe(JobStatus::Disputed);
    $progress->settle($unfinished, engagementCompleted: false);
    expect($unfinished->fresh()->status)->toBe(JobStatus::InProgress);

    $finished = jobAt(JobStatus::Completed);
    $progress->dispute($finished);
    expect($finished->fresh()->status)->toBe(JobStatus::Disputed);
    $progress->settle($finished, engagementCompleted: true);
    expect($finished->fresh()->status)->toBe(JobStatus::Completed);
});
