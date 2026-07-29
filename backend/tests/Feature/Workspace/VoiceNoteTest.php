<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\Media;
use App\Models\Message;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P4-05 acceptance: voice notes. Speaking a problem is far easier than typing it in a second
 * language, so a voice note is a FIRST-CLASS thread entry (kind `voice`) with its audio attached as
 * media — riding the same rails text does. The other half is entitlement: media was previously
 * unreachable, and the new route must serve a file only to someone the attachable entitles.
 *
 * @return array{customer: User, provider: User, job: Job, conversation: Conversation}
 */
function voiceThread(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 500_000, 'deposit_minor' => 0, 'valid_until' => now()->addDays(3),
    ]);
    app(AcceptQuotation::class)->handle($customer, $quote);
    $conversation = Conversation::query()->where('job_id', $job->id)->firstOrFail();

    return compact('customer', 'provider', 'job', 'conversation');
}

/**
 * A stand-in recording. `UploadedFile::fake()` reports the mime we declare, which is what the
 * `mimetypes:` rule inspects — a handful of synthetic bytes would sniff as octet-stream and be
 * rejected for the wrong reason, telling us nothing about the endpoint.
 */
function fakeAudio(string $name = 'note.ogg'): UploadedFile
{
    $file = UploadedFile::fake()->create($name, 32, 'audio/ogg');
    // `fake()->create()` declares a size but writes an EMPTY file; StoreMedia (rightly) refuses a
    // zero-byte upload, so give it real bytes.
    file_put_contents($file->getRealPath(), str_repeat("\x01", 4096));

    return $file;
}

it('posts a voice note as a first-class message with its audio attached', function () {
    Storage::fake('local');
    ['customer' => $customer, 'job' => $job] = voiceThread();

    Sanctum::actingAs($customer);
    $response = $this->post("/api/v1/jobs/{$job->id}/voice-messages", [
        'audio' => fakeAudio(),
        'duration_ms' => 4200,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated()
        ->assertJsonPath('data.kind', 'voice')
        ->assertJsonPath('data.payload.duration_ms', 4200)
        ->assertJsonCount(1, 'data.media');

    $message = Message::query()->where('kind', 'voice')->firstOrFail();
    $media = Media::query()->where('attachable_id', $message->id)->firstOrFail();

    expect($media->attachable_type)->toBe('message')
        ->and($media->bytes)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($media->storage_path);
});

it('serves the audio to a participant and refuses everyone else', function () {
    Storage::fake('local');
    ['customer' => $customer, 'provider' => $provider, 'job' => $job] = voiceThread();

    Sanctum::actingAs($customer);
    $this->post("/api/v1/jobs/{$job->id}/voice-messages", ['audio' => fakeAudio()], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $media = Media::query()->firstOrFail();

    // The other participant can play it — that is the whole point of sending it.
    Sanctum::actingAs($provider);
    $this->get("/api/v1/media/{$media->id}")->assertOk();

    // A stranger holding the id cannot. Entitlement comes from the attachable, not possession.
    Sanctum::actingAs(User::factory()->create());
    $this->get("/api/v1/media/{$media->id}")->assertForbidden();
});

it('returns a fetchable URL on the thread, never the raw storage path', function () {
    Storage::fake('local');
    ['customer' => $customer, 'job' => $job] = voiceThread();

    Sanctum::actingAs($customer);
    $this->post("/api/v1/jobs/{$job->id}/voice-messages", ['audio' => fakeAudio()], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $media = Media::query()->firstOrFail();

    $body = $this->getJson("/api/v1/jobs/{$job->id}/messages")->assertOk()->json();
    $voice = collect($body['data'])->firstWhere('kind', 'voice');

    expect($voice['media'][0]['url'])->toContain("/api/v1/media/{$media->id}")
        ->and(json_encode($body))->not->toContain($media->storage_path);
});

it('rejects a non-audio upload', function () {
    Storage::fake('local');
    ['customer' => $customer, 'job' => $job] = voiceThread();

    $path = tempnam(sys_get_temp_dir(), 'txt').'.txt';
    file_put_contents($path, 'not audio');

    Sanctum::actingAs($customer);
    $this->post("/api/v1/jobs/{$job->id}/voice-messages", [
        'audio' => new UploadedFile($path, 'note.txt', 'text/plain', null, true),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

it('refuses a voice note from a non-participant', function () {
    Storage::fake('local');
    ['job' => $job] = voiceThread();

    Sanctum::actingAs(User::factory()->create());
    $this->post("/api/v1/jobs/{$job->id}/voice-messages", ['audio' => fakeAudio()], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('fails honestly on an empty recording instead of a 500', function () {
    Storage::fake('local');
    ['customer' => $customer, 'job' => $job] = voiceThread();

    // A capture that produced nothing. The DB constraint would refuse it anyway — as a 500.
    $empty = UploadedFile::fake()->create('note.ogg', 8, 'audio/ogg');

    Sanctum::actingAs($customer);
    $this->post("/api/v1/jobs/{$job->id}/voice-messages", ['audio' => $empty], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://errors.handyman.cm/empty-upload');

    expect(Message::query()->where('kind', 'voice')->count())->toBe(0);
});

it('accepts the mime types the server actually SNIFFS, not just the spec names', function () {
    Storage::fake('local');
    ['customer' => $customer, 'job' => $job] = voiceThread();

    // A real WAV detects as `audio/x-wav`, not `audio/wav`; an m4a as `audio/x-m4a`. Listing only
    // the canonical names rejected genuine recordings — found by uploading a real file, not a fake.
    foreach (['audio/x-wav', 'audio/x-m4a', 'video/webm'] as $sniffed) {
        $file = UploadedFile::fake()->create('note', 32, $sniffed);
        file_put_contents($file->getRealPath(), str_repeat("\x01", 2048));

        Sanctum::actingAs($customer);
        $this->post("/api/v1/jobs/{$job->id}/voice-messages", ['audio' => $file], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
    }

    expect(Message::query()->where('kind', 'voice')->count())->toBe(3);
});
