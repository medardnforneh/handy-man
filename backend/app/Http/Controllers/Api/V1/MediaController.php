<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Media\MediaAccess;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored media file to someone entitled to it (build plan P4-05/P5-04).
 *
 * Until now media existed but was unreachable — `storage_path` was returned and nothing could fetch
 * the bytes, so report photos and voice notes had no way to be shown. Entitlement is decided by
 * what the file hangs off ({@see MediaAccess}), never by holding the id.
 *
 * Streamed rather than read into memory: a voice note is small, but a report photo need not be.
 */
final class MediaController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(MediaAccess::canView($user, $media), 403);

        $disk = Storage::disk((string) config('filesystems.default'));
        abort_unless($disk->exists($media->storage_path), 404);

        return $disk->response(
            $media->storage_path,
            null,
            [
                // The bytes are already stripped of metadata at write time (P5-04); serving them
                // inline is safe, and `nosniff` keeps a browser from re-interpreting the type.
                'Content-Type' => $disk->mimeType($media->storage_path) ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }
}
