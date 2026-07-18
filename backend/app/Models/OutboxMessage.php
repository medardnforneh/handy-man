<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A row in the transactional outbox. Written in the producer's transaction; drained by the relay.
 *
 * @property int $id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string|null $partition_key
 * @property Carbon $created_at
 * @property Carbon $available_at
 * @property Carbon|null $processed_at
 * @property int $attempts
 * @property string|null $last_error
 */
final class OutboxMessage extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @param  Builder<OutboxMessage>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('processed_at');
    }

    /**
     * @param  Builder<OutboxMessage>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('available_at', '<=', now());
    }
}
