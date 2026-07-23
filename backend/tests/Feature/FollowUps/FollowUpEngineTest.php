<?php

declare(strict_types=1);

use App\Domain\FollowUps\Actions\DispatchFollowUps;
use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Models\Consent;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * P7-01 / P7-03 / P7-04 acceptance (doc 07): the follow-up engine. Scheduling is idempotent on the
 * dedupe key; the channel budget suppresses over-cap sends; marketing kinds are gated on consent
 * while service (transactional) kinds always get through.
 */
function grantMarketing(User $user, bool $granted): void
{
    Consent::query()->create([
        'user_id' => $user->id, 'purpose' => 'marketing', 'granted' => $granted,
        'policy_version' => 'v1', 'presented_locale' => 'en', 'created_at' => now(),
    ]);
}

it('schedules idempotently — the same event 50x yields exactly one follow-up (P7-01)', function () {
    $user = User::factory()->create();
    $scheduler = app(FollowUpScheduler::class);
    $anchorId = (string) Str::uuid();

    for ($i = 0; $i < 50; $i++) {
        $scheduler->schedule(FollowUpKind::ReviewRequest, $user, FollowUpChannel::Push, now(), 'engagement', $anchorId);
    }

    expect(FollowUp::query()->count())->toBe(1);
});

it('suppresses sends over the channel budget (P7-03)', function () {
    $user = User::factory()->create();
    $scheduler = app(FollowUpScheduler::class);

    // 5 distinct SMS follow-ups, all due now. SMS budget is 2/day.
    for ($i = 0; $i < 5; $i++) {
        $scheduler->schedule(FollowUpKind::QuotePendingCustomer, $user, FollowUpChannel::Sms, now(), 'quotation', (string) Str::uuid());
    }

    app(DispatchFollowUps::class)->handle();

    expect(FollowUp::query()->where('status', 'sent')->count())->toBe(2)
        ->and(FollowUp::query()->where('status', 'suppressed')->count())->toBe(3);
});

it('gates marketing kinds on consent but lets service kinds through (P7-04)', function () {
    $user = User::factory()->create();
    grantMarketing($user, false); // marketing revoked
    $scheduler = app(FollowUpScheduler::class);

    $scheduler->schedule(FollowUpKind::Reengagement, $user, FollowUpChannel::Push, now(), 'user', $user->id);
    $scheduler->schedule(FollowUpKind::CheckInOverdue, $user, FollowUpChannel::Push, now(), 'assignment', (string) Str::uuid());

    app(DispatchFollowUps::class)->handle();

    // Marketing suppressed; the transactional service message still sent.
    expect(FollowUp::query()->where('kind', 'reengagement')->value('status')?->value)->toBe('suppressed')
        ->and(FollowUp::query()->where('kind', 'check_in_overdue')->value('status')?->value)->toBe('sent');
});

it('sends a marketing kind once consent is granted', function () {
    $user = User::factory()->create();
    grantMarketing($user, true);

    app(FollowUpScheduler::class)->schedule(FollowUpKind::Reengagement, $user, FollowUpChannel::Push, now(), 'user', $user->id);
    app(DispatchFollowUps::class)->handle();

    expect(FollowUp::query()->where('kind', 'reengagement')->value('status')?->value)->toBe('sent');
});

it('does not send a follow-up scheduled for the future', function () {
    $user = User::factory()->create();
    app(FollowUpScheduler::class)->schedule(FollowUpKind::ReviewRequest, $user, FollowUpChannel::Push, now()->addDay(), 'engagement', (string) Str::uuid());

    expect(app(DispatchFollowUps::class)->handle())->toBe(0);
    expect(FollowUp::query()->where('status', 'scheduled')->count())->toBe(1);
});
