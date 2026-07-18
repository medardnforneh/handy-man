<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored idempotency record. See the Idempotency middleware for the claim/replay protocol.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property int|null $user_id
 * @property string $request_method
 * @property string $request_path
 * @property string $request_hash
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_headers
 * @property string|null $response_body
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $completed_at
 * @property Carbon $expires_at
 */
final class IdempotencyKey extends Model
{
    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'response_status' => 'integer',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
