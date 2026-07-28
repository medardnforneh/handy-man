<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Media\StoreMedia;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobReport;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P5-04 acceptance (doc 02/06): on-site job reports + before/after media. The marquee guarantee is
 * that the stored file is EXIF-stripped and the geo it carried is recorded in the DB column instead
 * — serving a customer's photo to a provider must never leak embedded GPS.
 */

/**
 * A minimal valid JPEG with an EXIF APP1 segment injected right after the SOI marker, carrying a
 * recognisable payload we can assert is gone from the re-encoded file.
 */
function jpegWithExif(): string
{
    $img = imagecreatetruecolor(8, 8);
    imagefill($img, 0, 0, imagecolorallocate($img, 10, 120, 200));
    ob_start();
    imagejpeg($img, null, 92);
    $jpeg = (string) ob_get_clean();
    imagedestroy($img);

    $payload = "Exif\x00\x00SECRET_GPS_5.9631_10.1591";
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

function fakeUpload(string $bytes, string $name = 'photo.jpg'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

/**
 * @return array{customer: User, provider: User, engagement: Engagement, assignment: Assignment}
 */
function reportEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->status(JobStatus::Engaged)->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
    ]);
    $assignment = Assignment::factory()->create([
        'engagement_id' => $engagement->id,
        'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id,
        'role' => 'lead',
    ]);

    return compact('customer', 'provider', 'engagement', 'assignment');
}

it('strips EXIF from the stored file and records geo in the DB instead', function () {
    Storage::fake('local');
    ['assignment' => $assignment, 'provider' => $provider] = reportEngagement();

    $original = jpegWithExif();
    expect($original)->toContain('SECRET_GPS'); // sanity: the source really carries it

    $media = app(StoreMedia::class)->handle(
        file: fakeUpload($original),
        ownerPartyId: $provider->party_id,
        attachableType: 'job_report',
        attachableId: $assignment->id,
        kind: 'before',
        latitude: 5.9631,
        longitude: 10.1591,
    );

    $stored = Storage::disk('local')->get($media->storage_path);

    expect($stored)->not->toContain('SECRET_GPS');           // metadata gone from the file
    expect($stored)->not->toContain('Exif');
    expect(hash('sha256', (string) $stored))->toBe($media->sha256); // hash describes the clean file
    expect($media->bytes)->toBe(strlen((string) $stored));
    expect($media->captured_point?->latitude)->toBe(5.9631);  // geo recorded server-side
    expect($media->captured_point?->longitude)->toBe(10.1591);
});

it('lets an assigned worker submit a report with a before photo', function () {
    Storage::fake('local');
    ['provider' => $provider, 'engagement' => $engagement] = reportEngagement();

    Sanctum::actingAs($provider);
    $response = $this->post("/api/v1/engagements/{$engagement->id}/report", [
        'summary' => 'Replaced the burst pipe under the sink; tested for leaks.',
        'extra_charges_minor' => 150000,
        'materials' => [['label' => 'PVC elbow', 'qty' => 2, 'unit_cost_minor' => 50000]],
        'photos' => [
            ['file' => fakeUpload(jpegWithExif()), 'kind' => 'before', 'latitude' => 5.96, 'longitude' => 10.15],
        ],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated()
        ->assertJsonPath('data.extra_charges_minor', 150000)
        ->assertJsonCount(1, 'data.media');

    $report = JobReport::query()->firstOrFail();
    expect($report->submitted_at)->not->toBeNull();
    expect(Media::query()->where('attachable_id', $report->id)->where('attachable_type', 'job_report')->count())->toBe(1);
});

it('stores materials with their numbers typed, not as the wire strings', function () {
    ['provider' => $provider, 'engagement' => $engagement] = reportEngagement();

    // A multipart request carries every field as a string — the real client's shape. The stored
    // jsonb must still be arithmetic-ready, not a transcript of the wire format.
    Sanctum::actingAs($provider);
    $this->post("/api/v1/engagements/{$engagement->id}/report", [
        'summary' => 'Swapped the capacitor and recharged the gas.',
        'extra_charges_minor' => '5000',
        'materials' => [['label' => 'Capacitor 45uF', 'qty' => '1.5', 'unit_cost_minor' => '12500']],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $materials = JobReport::query()->firstOrFail()->materials;
    expect($materials[0]['qty'])->toBe(1.5)
        ->and($materials[0]['unit_cost_minor'])->toBe(12500)
        ->and($materials[0]['label'])->toBe('Capacitor 45uF');
});

it('forbids a non-assigned user from submitting a report', function () {
    ['engagement' => $engagement] = reportEngagement();

    Sanctum::actingAs(User::factory()->create());
    $this->post("/api/v1/engagements/{$engagement->id}/report", [
        'summary' => 'Not my job.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('rejects an invalid photo kind (422)', function () {
    Storage::fake('local');
    ['provider' => $provider, 'engagement' => $engagement] = reportEngagement();

    Sanctum::actingAs($provider);
    $this->post("/api/v1/engagements/{$engagement->id}/report", [
        'summary' => 'Done.',
        'photos' => [['file' => fakeUpload(jpegWithExif()), 'kind' => 'selfie']],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});
