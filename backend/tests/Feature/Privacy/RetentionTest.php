<?php

declare(strict_types=1);

use App\Domain\Privacy\ApplyRetention;
use App\Domain\Verification\DocStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Conversation;
use App\Models\IdempotencyKey;
use App\Models\Media;
use App\Models\Message;
use App\Models\Job;
use App\Models\OtpChallenge;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\WorkSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * The retention schedule (doc 04, config/retention.php): personal data does not outlive its
 * purpose, for people who never asked. Every rule: something past the line goes, the same thing
 * one day inside the line stays.
 */
/** An assignment the worker-boundary trigger accepts: the worker IS the engagement's provider. */
function retentionAssignment(int $slot): Assignment
{
    $provider = User::factory()->create();
    $job = Job::factory()->create();
    $engagement = Engagement::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);

    return Assignment::factory()->create([
        'engagement_id' => $engagement->id, 'worker_user_id' => $provider->id, 'assigned_by_user_id' => $provider->id,
        'role' => 'lead', 'scheduled_from' => now()->addHours($slot * 3), 'scheduled_to' => now()->addHours($slot * 3 + 1),
    ]);
}

function encryptedDoc(array $attributes = []): VerificationDocument
{
    $file = UploadedFile::fake()->createWithContent('id.jpg', 'PLAINTEXT-ID-'.uniqid());
    $owner = User::factory()->create();
    [$path, $sha, $scheme] = app(VerificationStorage::class)->store($file, $owner->party);

    return VerificationDocument::factory()->create(array_merge([
        'party_id' => $owner->party_id, 'storage_path' => $path, 'sha256' => $sha,
        'encryption_scheme' => $scheme,
    ], $attributes));
}

beforeEach(function () {
    Storage::fake('verification');
    config()->set('retention', [
        'otp_challenges_days' => 1, 'idempotency_keys_days' => 0, 'refresh_tokens_days' => 30,
        'work_session_geo_days' => 90, 'rejected_documents_days' => 30, 'expired_documents_days' => 30,
    ]);
});

it('destroys spent codes, dead idempotency records and old session tokens — and nothing still in use', function () {
    $oldOtp = OtpChallenge::factory()->create(['expires_at' => now()->subDays(2)]);
    $recentOtp = OtpChallenge::factory()->expired()->create(); // expired minutes ago: the rate limit still counts it
    IdempotencyKey::query()->insert([
        ['idempotency_key' => 'dead', 'request_method' => 'POST', 'request_path' => '/x', 'request_hash' => 'h', 'status' => 'completed', 'created_at' => now()->subDays(2), 'expires_at' => now()->subHour()],
        ['idempotency_key' => 'live', 'request_method' => 'POST', 'request_path' => '/x', 'request_hash' => 'h', 'status' => 'completed', 'created_at' => now(), 'expires_at' => now()->addHour()],
    ]);
    $oldRevoked = RefreshToken::factory()->create(['revoked_at' => now()->subDays(31)]);
    $recentRevoked = RefreshToken::factory()->revoked()->create();
    $live = RefreshToken::factory()->create();

    $report = app(ApplyRetention::class)->handle();

    expect($report['otp_challenges'])->toBe(1)
        ->and(OtpChallenge::query()->whereKey($oldOtp->id)->exists())->toBeFalse()
        ->and(OtpChallenge::query()->whereKey($recentOtp->id)->exists())->toBeTrue()
        ->and($report['idempotency_keys'])->toBe(1)
        ->and(IdempotencyKey::query()->pluck('idempotency_key')->all())->toBe(['live'])
        ->and($report['refresh_tokens'])->toBe(1)
        ->and(RefreshToken::query()->whereKey($oldRevoked->id)->exists())->toBeFalse()
        ->and(RefreshToken::query()->whereKey($recentRevoked->id)->exists())->toBeTrue()
        ->and(RefreshToken::query()->whereKey($live->id)->exists())->toBeTrue();
});

it('strips the coordinates off a work session after 90 days and keeps the session', function () {
    $old = WorkSession::factory()->closed()->create(['assignment_id' => retentionAssignment(1)->id, 'started_at' => now()->subDays(100), 'ended_at' => now()->subDays(100)]);
    $recent = WorkSession::factory()->closed()->create(['assignment_id' => retentionAssignment(2)->id, 'started_at' => now()->subDays(80), 'ended_at' => now()->subDays(80)]);
    $open = WorkSession::factory()->create(['assignment_id' => retentionAssignment(3)->id, 'started_at' => now()->subDays(100)]); // never checked out: still open, still evidence

    $report = app(ApplyRetention::class)->handle();

    $old->refresh();
    expect($report['work_session_geo'])->toBe(1)
        ->and($old->start_point)->toBeNull()
        ->and($old->end_point)->toBeNull()
        ->and($old->start_accuracy_m)->toBeNull()
        ->and($old->ended_at)->not->toBeNull() // the session itself is not personal data
        ->and($recent->refresh()->start_point)->not->toBeNull()
        ->and($open->refresh()->start_point)->not->toBeNull();

    // Running again finds nothing: the rule is idempotent and the nightly log stays honest.
    expect(app(ApplyRetention::class)->handle()['work_session_geo'])->toBe(0);
});

it('purges the bytes of a rejected identity document after 30 days and keeps its audit row', function () {
    $oldRejected = encryptedDoc(['status' => DocStatus::Rejected->value, 'reject_reason' => 'blurry', 'reviewed_at' => now()->subDays(31)]);
    $freshRejected = encryptedDoc(['status' => DocStatus::Rejected->value, 'reject_reason' => 'blurry', 'reviewed_at' => now()->subDays(29)]);
    $expired = encryptedDoc(['status' => DocStatus::Approved->value, 'expires_at' => now()->subDays(31)]);
    $approved = encryptedDoc(['status' => DocStatus::Approved->value, 'reviewed_at' => now()->subYear()]);

    $report = app(ApplyRetention::class)->handle();

    expect($report['verification_documents'])->toBe(2);
    Storage::disk('verification')->assertMissing($oldRejected->storage_path);
    Storage::disk('verification')->assertMissing($expired->storage_path);
    Storage::disk('verification')->assertExists($freshRejected->storage_path);
    Storage::disk('verification')->assertExists($approved->storage_path);

    $oldRejected->refresh();
    expect($oldRejected->purged_at)->not->toBeNull()
        ->and($oldRejected->sha256)->toHaveLength(64)   // the same paper re-uploaded is still recognised
        ->and($oldRejected->reviewed_at)->not->toBeNull() // who decided what, when
        ->and(app(ApplyRetention::class)->handle()['verification_documents'])->toBe(0);
});

it('is on the nightly schedule and reports every rule', function () {
    expect(Artisan::call('schedule:list'))->toBe(0)
        ->and(Artisan::output())->toContain('data:retain');

    Artisan::call('data:retain');
    $out = Artisan::output();
    foreach (['otp_challenges', 'idempotency_keys', 'refresh_tokens', 'work_session_geo', 'verification_documents'] as $rule) {
        expect($out)->toContain($rule);
    }
});

it('destroys workspace media once the engagement is long finished, keeping the row', function () {
    Storage::fake('local');
    config()->set('retention.engagement_media_days', 365);

    // Two engagements: one finished long ago, one finished yesterday.
    $old = Engagement::factory()->create(['completed_at' => now()->subDays(400)]);
    $recent = Engagement::factory()->create(['completed_at' => now()->subDay()]);

    $oldMedia = workspaceMedia($old, 'old.ogg');
    $recentMedia = workspaceMedia($recent, 'recent.ogg');

    app(ApplyRetention::class)->handle();

    // Gone, with the row surviving to say so — a thread shows something was there rather than
    // losing the reference.
    Storage::disk('local')->assertMissing('old.ogg');
    expect($oldMedia->refresh()->purged_at)->not->toBeNull();

    // Still in its purpose window.
    Storage::disk('local')->assertExists('recent.ogg');
    expect($recentMedia->refresh()->purged_at)->toBeNull();
});

it('empties old message bodies but never the rows, and leaves server narration alone', function () {
    config()->set('retention.message_bodies_days', 365);

    $old = Engagement::factory()->create(['completed_at' => now()->subDays(400)]);
    $conversation = Conversation::factory()->create(['job_id' => $old->job_id]);
    $sender = User::factory()->create();

    $written = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_user_id' => $sender->getKey(),
        'body' => 'call me on 699000111',
    ]);
    // Server narration: no free text, and its payload is rendered in the reader's language.
    $narrated = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_user_id' => null,
        'body' => null,
        'kind' => 'quote_accepted',
    ]);

    app(ApplyRetention::class)->handle();

    expect($written->refresh()->body)->toBeNull()
        // The row, its kind, its sender and its place in the thread all stay: redacted, not
        // truncated, so a dispute can still see that something was said and by whom.
        ->and(Message::query()->whereKey($written->getKey())->exists())->toBeTrue()
        ->and($written->sender_user_id)->toBe($sender->getKey())
        ->and(Message::query()->whereKey($narrated->getKey())->exists())->toBeTrue();
});

it('keeps workspace content for ever when the period is zero', function () {
    Storage::fake('local');
    config()->set('retention.engagement_media_days', 0);
    config()->set('retention.message_bodies_days', 0);

    $old = Engagement::factory()->create(['completed_at' => now()->subDays(5000)]);
    $media = workspaceMedia($old, 'kept.ogg');

    $report = app(ApplyRetention::class)->handle();

    // 0 means "kept, and said so in the register" — not "kept by accident".
    expect($report['engagement_media'])->toBe(0)
        ->and($report['message_bodies'])->toBe(0)
        ->and($media->refresh()->purged_at)->toBeNull();
    Storage::disk('local')->assertExists('kept.ogg');
});

it('counts without destroying on a dry run', function () {
    // The workspace periods are a starting point, not a finding. Nobody should have to learn what
    // a number means by watching it delete a year of someone's threads.
    Storage::fake('local');
    config()->set('retention.engagement_media_days', 365);

    $old = Engagement::factory()->create(['completed_at' => now()->subDays(400)]);
    $media = workspaceMedia($old, 'dry.ogg');

    $report = app(ApplyRetention::class)->handle(dryRun: true);

    expect($report['engagement_media'])->toBe(1)
        ->and($media->refresh()->purged_at)->toBeNull();
    Storage::disk('local')->assertExists('dry.ogg');
});

/** A voice note hanging off a message in this engagement's conversation. */
function workspaceMedia(Engagement $engagement, string $path): Media
{
    $conversation = Conversation::factory()->create(['job_id' => $engagement->job_id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id]);
    Storage::disk('local')->put($path, 'AUDIO');

    return Media::factory()->create([
        'attachable_type' => 'message',
        'attachable_id' => $message->getKey(),
        'kind' => 'attachment',
        'storage_path' => $path,
    ]);
}
