<?php

declare(strict_types=1);

use App\Domain\Access\Capabilities\AcceptPaidJob;
use App\Domain\Access\PreconditionUnmetException;
use App\Domain\Verification\Actions\ReviewVerificationDocument;
use App\Domain\Verification\DocKind;
use App\Domain\Verification\DocStatus;
use App\Domain\Verification\SignedDocumentUrl;
use App\Domain\Verification\VerificationStorage;
use App\Models\ActivityLog;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P6-02 / P6-03 acceptance (doc 04): the review queue. Every document VIEW is logged (not just
 * edits), a human approval RAISES the party's tier, and that tier feeds the accept-paid-job gate.
 */
function storedDoc(DocKind $kind = DocKind::NationalIdFront): VerificationDocument
{
    $upload = new UploadedFile(
        tap(tempnam(sys_get_temp_dir(), 'vd'), fn ($p) => file_put_contents($p, 'ID-BYTES')),
        'id.bin', 'application/octet-stream', null, true,
    );
    [$path, $sha] = app(VerificationStorage::class)->store($upload);

    return VerificationDocument::factory()->create([
        'kind' => $kind->value, 'grants_tier' => $kind->grantsTier(),
        'storage_path' => $path, 'sha256' => $sha, 'status' => DocStatus::Pending->value,
    ]);
}

it('logs every view of a document, not just edits (P6-02)', function () {
    Storage::fake('verification');
    $doc = storedDoc();
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(app(SignedDocumentUrl::class)->for($doc))->assertOk();

    $entry = ActivityLog::query()->where('action', 'verification_document.viewed')->firstOrFail();
    expect($entry->subject_id)->toBe($doc->id)
        ->and($entry->actor_user_id)->toBe($admin->id);
});

it('raises the party tier on approval and attributes it to the reviewer (P6-03)', function () {
    Storage::fake('verification');
    $provider = User::factory()->create();
    ProviderProfile::factory()->create(['party_id' => $provider->party_id, 'verification_tier' => 0]);
    $doc = storedDoc(DocKind::NationalIdFront); // grants tier 2
    $doc->update(['party_id' => $provider->party_id, 'subject_user_id' => $provider->id]);

    $admin = User::factory()->create();
    app(ReviewVerificationDocument::class)->approve($doc, $admin);

    expect($doc->fresh()->status)->toBe(DocStatus::Approved)
        ->and((int) ProviderProfile::query()->where('party_id', $provider->party_id)->value('verification_tier'))->toBe(2);

    $log = ActivityLog::query()->where('action', 'verification_document.approved')->firstOrFail();
    expect($log->actor_user_id)->toBe($admin->id);
});

it('records the reason on rejection', function () {
    Storage::fake('verification');
    $doc = storedDoc();
    $admin = User::factory()->create();

    app(ReviewVerificationDocument::class)->reject($doc, $admin, 'Blurry photo — resubmit.');

    expect($doc->fresh()->status)->toBe(DocStatus::Rejected)
        ->and($doc->fresh()->reject_reason)->toBe('Blurry photo — resubmit.');
    expect(ActivityLog::query()->where('action', 'verification_document.rejected')->exists())->toBeTrue();
});

it('gates a tier-3 on-site job on approval, but never a remote one (P6-03)', function () {
    Storage::fake('verification');
    $provider = User::factory()->create(); // phone verified → lighter (tier-1) check passes
    ProviderProfile::factory()->create(['party_id' => $provider->party_id, 'verification_tier' => 0]);
    $gate = app(AcceptPaidJob::class);

    // Remote high-risk job needs only the lighter check — allowed from the start.
    $gate->authorize($provider, ['engagement_mode' => 'remote', 'risk_tier' => 3]);

    // On-site high-risk needs tier 3 — refused until a real document is approved.
    expect(fn () => $gate->authorize($provider, ['engagement_mode' => 'onsite', 'risk_tier' => 3]))
        ->toThrow(PreconditionUnmetException::class);

    $doc = storedDoc(DocKind::TradeLicense); // grants tier 3
    $doc->update(['party_id' => $provider->party_id, 'subject_user_id' => $provider->id]);
    app(ReviewVerificationDocument::class)->approve($doc, User::factory()->create());

    // Now the same on-site job is allowed.
    expect(fn () => $gate->authorize($provider, ['engagement_mode' => 'onsite', 'risk_tier' => 3]))
        ->not->toThrow(PreconditionUnmetException::class);
});
