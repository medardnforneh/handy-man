<?php

declare(strict_types=1);

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\EngagementModePolicy;
use App\Models\Job;

it('encodes the doc-06 feature matrix per mode', function () {
    $policy = app(EngagementModePolicy::class);

    // onsite — full physical apparatus, no deliverables.
    expect($policy->requiresAddress(EngagementMode::Onsite))->toBeTrue()
        ->and($policy->supportsCheckIn(EngagementMode::Onsite))->toBeTrue()
        ->and($policy->supportsPanic(EngagementMode::Onsite))->toBeTrue()
        ->and($policy->usesDeliverables(EngagementMode::Onsite))->toBeFalse();

    // remote — none of the physical apparatus, deliverables instead.
    expect($policy->requiresAddress(EngagementMode::Remote))->toBeFalse()
        ->and($policy->supportsDispatch(EngagementMode::Remote))->toBeFalse()
        ->and($policy->supportsCheckIn(EngagementMode::Remote))->toBeFalse()
        ->and($policy->supportsPanic(EngagementMode::Remote))->toBeFalse()
        ->and($policy->supportsShareJob(EngagementMode::Remote))->toBeFalse()
        ->and($policy->supportsSiteVisit(EngagementMode::Remote))->toBeFalse()
        ->and($policy->usesDeliverables(EngagementMode::Remote))->toBeTrue();

    // hybrid — physical apparatus AND deliverables.
    expect($policy->requiresAddress(EngagementMode::Hybrid))->toBeTrue()
        ->and($policy->supportsCheckIn(EngagementMode::Hybrid))->toBeTrue()
        ->and($policy->usesDeliverables(EngagementMode::Hybrid))->toBeTrue();
});

it('returns the applicability row for a job', function () {
    $job = Job::factory()->remote()->create();

    expect(app(EngagementModePolicy::class)->forJob($job))
        ->toMatchArray(['address' => false, 'check_in' => false, 'deliverables' => true]);
});

it('has NO scattered engagement-mode equality branching outside the policy (P2-02 acceptance)', function () {
    $offenders = [];
    $allowed = ['EngagementModePolicy.php', 'EngagementMode.php'];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || in_array($file->getFilename(), $allowed, true)) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        // The literal thing the acceptance forbids: `... === 'remote'` (or onsite/hybrid), either order.
        if (preg_match("/[=!]==\\s*['\"](remote|onsite|hybrid)['\"]/", $contents)
            || preg_match("/['\"](remote|onsite|hybrid)['\"]\\s*[=!]==/", $contents)) {
            $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
