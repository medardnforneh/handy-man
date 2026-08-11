<?php

declare(strict_types=1);

use App\Domain\Safety\Actions\ReviewReport;
use App\Domain\Safety\ReportAlreadyClosed;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\User;

/**
 * P6-07 follow-up: a human closes a report. Until this existed the queue could only be looked at —
 * `resolved` and `dismissed` were valid statuses nothing could ever set, so every report rested at
 * `open` forever.
 *
 * The reports table carries no reviewer column, so the attribution lives in the append-only activity
 * log (P6-02). These tests pin that: who decided, what they decided, and that a second decision on a
 * closed report cannot quietly overwrite the first.
 */
it('closes a report and attributes the decision in the audit log', function (): void {
    $report = Report::factory()->create(['status' => 'open']);
    $admin = User::factory()->create();

    $reviewed = app(ReviewReport::class)->handle($report, $admin, 'dismissed', 'Reporter withdrew it.', '10.0.0.7');

    expect($reviewed->status)->toBe('dismissed')
        ->and($reviewed->resolved_at)->not->toBeNull();

    $log = ActivityLog::query()
        ->where('action', 'report.reviewed')
        ->where('subject_id', $report->id)
        ->sole();

    expect($log->actor_user_id)->toBe($admin->id)
        ->and($log->context['decision'])->toBe('dismissed')
        ->and($log->context['note'])->toBe('Reporter withdrew it.')
        ->and($log->ip_address)->toBe('10.0.0.7');
});

it('treats reviewing as a step, not an outcome, so it stamps no close time', function (): void {
    $report = Report::factory()->create(['status' => 'open']);

    $reviewed = app(ReviewReport::class)->handle($report, User::factory()->create(), 'reviewing', 'Looking into it.');

    expect($reviewed->status)->toBe('reviewing')
        ->and($reviewed->resolved_at)->toBeNull();
});

it('refuses a second decision on an already-closed report', function (): void {
    $report = Report::factory()->create(['status' => 'open']);
    $first = User::factory()->create();

    app(ReviewReport::class)->handle($report, $first, 'resolved', 'Warned the provider.');

    expect(fn () => app(ReviewReport::class)->handle($report->refresh(), User::factory()->create(), 'dismissed', 'Actually fine.'))
        ->toThrow(ReportAlreadyClosed::class);

    // The first decision — and only the first — remains the record of who closed it.
    expect($report->refresh()->status)->toBe('resolved')
        ->and(ActivityLog::query()->where('action', 'report.reviewed')->where('subject_id', $report->id)->count())->toBe(1);
});

it('rejects a decision that is not one a human may record', function (): void {
    $report = Report::factory()->create(['status' => 'open']);

    expect(fn () => app(ReviewReport::class)->handle($report, User::factory()->create(), 'deleted', 'nope'))
        ->toThrow(InvalidArgumentException::class);
});
