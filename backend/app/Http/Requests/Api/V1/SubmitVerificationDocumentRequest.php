<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Verification\DocKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;

/**
 * A verification document upload (build plan P6-01). Multipart: the document kind + the file (image
 * or PDF). `expires_at` is optional — a licence/insurance cert can carry its own expiry.
 */
final class SubmitVerificationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', new Enum(DocKind::class)],
            'file' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function kind(): DocKind
    {
        return DocKind::from($this->string('kind')->toString());
    }

    public function documentFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->filled('expires_at') ? Carbon::parse($this->string('expires_at')->toString()) : null;
    }
}
