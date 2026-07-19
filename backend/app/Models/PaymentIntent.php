<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use Database\Factories\PaymentIntentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A collection attempt (doc 03). `idempotency_key` is unique — re-initiating with the same key
 * returns the same intent, never a second charge. Resolved exactly once (webhook or poll, whichever
 * arrives first) into a terminal status with a linked ledger transaction.
 *
 * @property string $id
 * @property string $party_id
 * @property string|null $engagement_id
 * @property PaymentPurpose $purpose
 * @property string $gateway
 * @property int $amount_minor
 * @property string $currency
 * @property string $msisdn
 * @property PaymentStatus $status
 * @property string|null $external_ref
 * @property string $idempotency_key
 * @property string|null $ledger_transaction_id
 * @property Carbon $initiated_at
 * @property Carbon $expires_at
 * @property Carbon|null $resolved_at
 * @property string|null $failure_code
 * @property array<string, mixed>|null $raw
 */
final class PaymentIntent extends Model
{
    /** @use HasFactory<PaymentIntentFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'engagement_id', 'purpose', 'gateway', 'amount_minor', 'currency', 'msisdn',
        'status', 'external_ref', 'idempotency_key', 'ledger_transaction_id', 'initiated_at',
        'expires_at', 'resolved_at', 'failure_code', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'initiated_at' => 'datetime',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function isResolved(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
