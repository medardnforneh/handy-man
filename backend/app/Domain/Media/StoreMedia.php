<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;
use RuntimeException;

/**
 * Stores an uploaded file as {@see Media} with its metadata stripped (build plan P5-04, doc 02/06).
 *
 * The privacy rule: **strip EXIF from the stored file, record geo in the DB instead.** A photo a
 * customer uploads can carry the GPS of their home; serving that file to a provider would leak it.
 * Raster images are re-encoded through GD, which drops every EXIF/XMP/GPS segment; the location the
 * client reports is written to `captured_point` server-side, so the clean file can be served freely.
 *
 * The stored `sha256`/`bytes` describe the CLEAN file (post-strip), so they match what's on disk.
 */
final class StoreMedia
{
    /** @var array<string, string> mime → GD re-encoder key */
    private const RASTER = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function handle(
        UploadedFile $file,
        string $ownerPartyId,
        string $attachableType,
        string $attachableId,
        string $kind,
        ?float $latitude = null,
        ?float $longitude = null,
        ?Carbon $capturedAt = null,
        string $disk = 'local',
    ): Media {
        $raw = (string) file_get_contents($file->getRealPath());
        $mime = (string) $file->getMimeType();

        [$clean, $extension] = $this->strip($raw, $mime, $file->getClientOriginalExtension());

        // A failed recording or truncated transfer arrives with no bytes. The `media_bytes_check`
        // constraint refuses it anyway — but as a 500. Fail honestly instead, for every media path.
        if ($clean === '') {
            throw new EmptyUpload;
        }

        $path = "media/{$attachableType}/".Str::uuid()->toString().($extension !== '' ? ".{$extension}" : '');
        Storage::disk($disk)->put($path, $clean);

        return Media::query()->create([
            'owner_party_id' => $ownerPartyId,
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'kind' => $kind,
            'storage_path' => $path,
            'sha256' => hash('sha256', $clean),
            'bytes' => strlen($clean),
            'captured_point' => $latitude !== null && $longitude !== null
                ? new Point($latitude, $longitude, Srid::WGS84->value)
                : null,
            'captured_at' => $capturedAt,
        ]);
    }

    /**
     * Returns [cleanBytes, extension]. Raster images are re-encoded (dropping all metadata); other
     * types pass through unchanged (there is no EXIF to strip from a PDF or an audio blob).
     *
     * @return array{0: string, 1: string}
     */
    private function strip(string $raw, string $mime, string $originalExtension): array
    {
        if (! isset(self::RASTER[$mime])) {
            return [$raw, strtolower($originalExtension)];
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            // Not decodable as claimed — refuse rather than store a mislabelled blob.
            throw new RuntimeException('The uploaded image could not be decoded.');
        }

        $format = self::RASTER[$mime];
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'png' => $this->encodePng($image),
            'webp' => imagewebp($image, null, 90),
            'gif' => imagegif($image),
        };
        $clean = (string) ob_get_clean();
        imagedestroy($image);

        return [$clean, $format === 'jpeg' ? 'jpg' : $format];
    }

    private function encodePng(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image);
    }
}
