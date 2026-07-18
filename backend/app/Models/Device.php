<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A registered device (doc 02). The id IS the client-generated X-Device-Id, so registration is an
 * upsert. Holds the FCM push token and the app version (P0-08 force-update).
 *
 * @property string $id
 * @property string $user_id
 * @property string $platform
 * @property string|null $push_token
 * @property string $app_version
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 */
final class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'platform', 'push_token', 'app_version', 'last_seen_at', 'revoked_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
