<?php

declare(strict_types=1);

use App\Domain\Verification\DocKind;
use App\Domain\Verification\SignedDocumentUrl;
use App\Domain\Verification\VerificationStorage;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-01 acceptance (doc 02/04): verification documents are encrypted at rest in a bucket separate
 * from public media, and served only through a signed short-TTL URL that expires. The kind of the
 * document fixes the tier it works toward, so tier can't be self-assigned.
 */
function smallJpeg(string $marker = 'PLAINTEXT_ID'): UploadedFile
{
    $img = imagecreatetruecolor(8, 8);
    imagestring($img, 1, 0, 0, $marker, imagecolorallocate($img, 255, 255, 255));
    ob_start();
    imagejpeg($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    $path = tempnam(sys_get_temp_dir(), 'vd').'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'id.jpg', 'image/jpeg', null, true);
}

it('encrypts the document at rest and records the plaintext hash', function () {
    Storage::fake('verification');
    $user = User::factory()->create();
    $upload = smallJpeg();
    $plaintext = (string) file_get_contents($upload->getRealPath());

    Sanctum::actingAs($user);
    $response = $this->post('/api/v1/verification-documents', [
        'kind' => 'national_id_front',
        'file' => $upload,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.grants_tier', 2)
        ->assertJsonMissingPath('data.storage_path'); // never leak the path

    $doc = VerificationDocument::query()->firstOrFail();
    $onDisk = (string) Storage::disk('verification')->get($doc->storage_path);

    expect($onDisk)->not->toBe($plaintext)                        // encrypted at rest
        ->and($doc->sha256)->toBe(hash('sha256', $plaintext))     // hash of the plaintext
        ->and($doc->party_id)->toBe($user->party_id)
        // ...and encrypted with the OWNING PARTY's key, not the application key. That is what
        // makes P1-10's crypto-shred real: erasure can destroy this key, and cannot destroy
        // APP_KEY without locking the platform out of its own data. This assertion used to be
        // `Crypt::decryptString($onDisk)`, which passed while the party key protected nothing.
        ->and($doc->encryption_scheme)->toBe(VerificationStorage::SCHEME_PARTY_KEY);

    // It round-trips through the storage service, which is the only thing that should know how.
    expect(app(VerificationStorage::class)->read($doc))->toBe($plaintext);

    // And the application key cannot read it, which is the whole point.
    expect(fn () => Crypt::decryptString($onDisk))->toThrow(DecryptException::class);
});

it('serves the document through a signed URL within its TTL', function () {
    Storage::fake('verification');
    $owner = User::factory()->create();
    [$path, $sha, $scheme] = app(VerificationStorage::class)->store(smallJpeg('SECRET_DOC'), $owner->party);
    $doc = VerificationDocument::factory()->create([
        'party_id' => $owner->party_id, 'storage_path' => $path, 'sha256' => $sha, 'encryption_scheme' => $scheme,
    ]);

    $url = app(SignedDocumentUrl::class)->for($doc);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

it('rejects the signed URL once it has expired', function () {
    Storage::fake('verification');
    $owner = User::factory()->create();
    [$path, $sha, $scheme] = app(VerificationStorage::class)->store(smallJpeg(), $owner->party);
    $doc = VerificationDocument::factory()->create([
        'party_id' => $owner->party_id, 'storage_path' => $path, 'sha256' => $sha, 'encryption_scheme' => $scheme,
    ]);

    $url = app(SignedDocumentUrl::class)->for($doc); // 60s TTL

    $this->travel(SignedDocumentUrl::TTL_SECONDS + 1)->seconds();

    $this->get($url)->assertStatus(403);
});

it('rejects a tampered signed URL', function () {
    Storage::fake('verification');
    $doc = VerificationDocument::factory()->create();

    $url = app(SignedDocumentUrl::class)->for($doc);
    $tampered = $url.'x'; // mutate the signature

    $this->get($tampered)->assertStatus(403);
});

it('fixes the granted tier from the document kind', function () {
    expect(DocKind::NationalIdFront->grantsTier())->toBe(2)
        ->and(DocKind::TradeLicense->grantsTier())->toBe(3)
        ->and(DocKind::Rccm->grantsTier())->toBe(3);
});
