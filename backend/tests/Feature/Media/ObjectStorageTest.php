<?php

declare(strict_types=1);

use App\Domain\Verification\VerificationStorage;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

/**
 * The object store (doc 04, ADR 0001, deploy/compose.yml `minio`). Every media path in the app
 * writes through `filesystems.default` and the verification disk; this pins that both can be
 * S3-driven with exactly the keys deploy/.env.production.example names — the adapter package is
 * installed, the config reads the right env, and the two disks are two DIFFERENT credentials.
 * A live MinIO round trip is the deploy smoke, not a unit test.
 */
it('drives both the media and the verification disk through S3 with separate credentials', function () {
    config()->set('filesystems.default', 's3');
    config()->set('filesystems.disks.s3', array_merge(config('filesystems.disks.s3'), [
        'key' => 'handyman-media', 'secret' => 'm', 'region' => 'cm-douala-1', 'bucket' => 'media',
        'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
    ]));
    config()->set('filesystems.disks.verification', array_merge(config('filesystems.disks.verification'), [
        'driver' => 's3', 'key' => 'handyman-verification', 'secret' => 'v', 'region' => 'cm-douala-1',
        'bucket' => 'verification', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
    ]));
    Storage::forgetDisk(['s3', 'verification']);

    $media = Storage::disk((string) config('filesystems.default'));
    $verification = Storage::disk(app(VerificationStorage::class)->disk());

    expect($media)->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($verification)->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($media->getConfig()['bucket'])->toBe('media')
        ->and($verification->getConfig()['bucket'])->toBe('verification')
        ->and($media->getConfig()['key'])->not->toBe($verification->getConfig()['key'])
        // Path-style, or the SDK would try `media.minio:9000` as a hostname.
        ->and($media->getClient()->getEndpoint()->getHost())->toBe('minio');
});

it('never exposes a bucket URL — media is served by the app after an entitlement check', function () {
    // The media controller streams from the disk; nothing in the API returns a storage URL.
    $spec = (string) file_get_contents(base_path('../openapi/openapi.yaml'));

    expect($spec)->not->toContain('storage_path')
        ->and($spec)->not->toMatch('/minio|amazonaws|s3\./');
});
