<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\PaymentStatus;
use Database\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A disbursement to a provider (doc 03). The ledger posting happens on gateway confirmation; a
 * confirmed-then-failed payout is corrected by a new reversal transaction, never a delete.
 *
 * @property string $id
 * @property string $party_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $msisdn
 * @property string $gateway
 * @property PaymentStatus $status
 * @property string|null $external_ref
 * @property string $idempotency_key
 * @property string|null $ledger_transaction_id
 * @property string|null $reversal_transaction_id
 * @property Carbon $requested_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $reversed_at
 * @property string|null $failure_code
 * @property array<string, mixed>|null $raw
 */
final class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'amount_minor', 'currency', 'msisdn', 'gateway', 'status', 'external_ref',
        'idempotency_key', 'ledger_transaction_id', 'reversal_transaction_id', 'requested_at',
        'resolved_at', 'reversed_at', 'failure_code', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reversed_at' => 'datetime',
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
}
