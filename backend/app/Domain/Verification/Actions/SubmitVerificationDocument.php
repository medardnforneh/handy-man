<?php

declare(strict_types=1);

namespace App\Domain\Verification\Actions;

use App\Domain\Verification\DocKind;
use App\Domain\Verification\DocStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * A user submits a verification document for review (build plan P6-01). The file is encrypted and
 * stored in the verification bucket; the row rests `pending` until a human admin approves it (P6-02),
 * at which point it can raise the party's tier (P6-03). The document's kind fixes the tier it works
 * toward, so a customer can't self-assign tier 3 by mislabelling a selfie.
 */
final class SubmitVerificationDocument
{
    public function __construct(private readonly VerificationStorage $storage) {}

    public function handle(User $user, DocKind $kind, UploadedFile $file, ?Carbon $expiresAt = null): VerificationDocument
    {
        [$path, $sha256] = $this->storage->store($file);

        return VerificationDocument::query()->create([
            'party_id' => $user->party_id,
            'subject_user_id' => $user->id,
            'kind' => $kind->value,
            'storage_path' => $path,
            'sha256' => $sha256,
            'grants_tier' => $kind->grantsTier(),
            'status' => DocStatus::Pending->value,
            'expires_at' => $expiresAt,
        ]);
    }
}
