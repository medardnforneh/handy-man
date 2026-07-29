<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A voice note upload (build plan P4-05). Multipart: the audio blob plus its measured duration.
 *
 * The mime allow-list is deliberately narrow and matches what phone browsers and Capacitor actually
 * record — webm/ogg (Chrome/Android), mp4/m4a and mpeg (Safari/iOS). The size cap is a guard, not a
 * product limit: 10 MB is minutes of speech at any sane bitrate.
 */
final class PostVoiceMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // participation is checked in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // NOTE these are the mimes the server SNIFFS, which are not always the spec names a
            // browser puts on the Blob: a real WAV detects as `audio/x-wav`, and an m4a as
            // `audio/x-m4a`. Listing only the canonical names rejected genuine recordings.
            'audio' => [
                'required', 'file', 'max:10240',
                'mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/mp4,audio/x-m4a,'
                    .'audio/mpeg,audio/aac,audio/x-hx-aac-adts,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave',
            ],
            'duration_ms' => ['nullable', 'integer', 'min:1', 'max:600000'],
        ];
    }

    public function audio(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('audio');

        return $file;
    }

    public function durationMs(): ?int
    {
        return $this->filled('duration_ms') ? (int) $this->input('duration_ms') : null;
    }
}
