<?php

declare(strict_types=1);

use App\Domain\Privacy\ApplyRetention;
use App\Domain\Verification\DocStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\IdempotencyKey;
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
    [$path, $sha] = app(VerificationStorage::class)->store($file);

    return VerificationDocument::factory()->create(array_merge(['storage_path' => $path, 'sha256' => $sha], $attributes));
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
