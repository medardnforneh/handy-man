<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PaymentEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A received gateway webhook (doc 03). UNIQUE (gateway, external_ref, event_type) makes replays
 * harmless: the first insert wins, a duplicate conflicts and is discarded.
 *
 * @property string $id
 * @property string $gateway
 * @property string $external_ref
 * @property string $event_type
 * @property bool $signature_valid
 * @property array<string, mixed> $payload
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
final class PaymentEvent extends Model
{
    /** @use HasFactory<PaymentEventFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'gateway', 'external_ref', 'event_type', 'signature_valid', 'payload', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
