<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * A stored media file (doc 02). Polymorphic — attached to a job, job report, message or verification
 * document. The stored file is always EXIF-stripped; the geo it carried lives in `captured_point`
 * server-side so the clean file can be served without leaking embedded GPS.
 *
 * @property string $id
 * @property string $owner_party_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string $kind
 * @property string $storage_path
 * @property string $sha256
 * @property int $bytes
 * @property Point|null $captured_point
 * @property Carbon|null $captured_at
 */
final class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasSpatial, HasUuids;

    protected $table = 'media';

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'owner_party_id', 'attachable_type', 'attachable_id', 'kind',
        'storage_path', 'sha256', 'bytes', 'captured_point', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'captured_point' => Point::class,
            'captured_at' => 'datetime',
        ];
    }
}
