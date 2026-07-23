<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Jobs\ProviderSearch;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Domain\Safety\Actions\BlockParty;
use App\Domain\Safety\PartyBlocked;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-07 acceptance (doc 02/04): a block is honoured in ALL THREE paths — search, dispatch ranking,
 * and offer creation — or it isn't a block. Plus report filing.
 */
it('excludes a blocked provider from search + ranking', function () {
    $skill = Skill::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['skill_id' => $skill->id]);

    $ok = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $ok->id, 'skill_id' => $skill->id]);
    $blocked = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $blocked->id, 'skill_id' => $skill->id]);

    // The customer blocks one provider's party.
    app(BlockParty::class)->handle($job->customer_party_id, $blocked->party_id);

    $results = app(ProviderSearch::class)->forJob($job)->pluck('id');

    expect($results)->toContain($ok->id)->not->toContain($blocked->id);
});

it('honours a block in either direction in search', function () {
    $skill = Skill::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create(['skill_id' => $skill->id]);

    $provider = ProviderProfile::factory()->create();
    ProviderSkill::factory()->create(['provider_profile_id' => $provider->id, 'skill_id' => $skill->id]);

    // The PROVIDER blocks the customer — still excluded (bidirectional).
    app(BlockParty::class)->handle($provider->party_id, $job->customer_party_id);

    expect(app(ProviderSearch::class)->forJob($job)->pluck('id'))->not->toContain($provider->id);
});

it('refuses to create a direct offer to a blocked provider', function () {
    $job = Job::factory()->status(JobStatus::Open)->create();
    $provider = ProviderProfile::factory()->create();

    app(BlockParty::class)->handle($provider->party_id, $job->customer_party_id); // provider blocked customer

    expect(fn () => app(CreateDirectOffer::class)->handle($job, $provider->party_id))
        ->toThrow(PartyBlocked::class);
});

it('lets a user block and unblock a party via the API', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/blocks', ['blocked_party_id' => $other->party_id], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.blocked_party_id', $other->party_id);

    $this->getJson('/api/v1/blocks')->assertOk()->assertJsonCount(1, 'data');

    $this->deleteJson("/api/v1/blocks/{$other->party_id}", [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->getJson('/api/v1/blocks')->assertJsonCount(0, 'data');
});

it('rejects blocking yourself (422)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/blocks', ['blocked_party_id' => $user->party_id], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('files a report and alerts staff via the outbox', function () {
    $user = User::factory()->create();
    $subject = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/reports', [
        'subject_party_id' => $subject->party_id,
        'category' => 'off_platform',
        'body' => 'Asked me to pay him directly on WhatsApp.',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.category', 'off_platform')
        ->assertJsonPath('data.status', 'open');

    expect(Report::query()->count())->toBe(1)
        ->and(OutboxMessage::query()->where('type', 'report.filed')->exists())->toBeTrue();
});
