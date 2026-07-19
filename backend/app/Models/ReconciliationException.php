<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReconciliationExceptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A ledger/settlement discrepancy the nightly job could not (and must not) auto-correct (doc 03). A
 * human resolves it with a balanced adjustment transaction.
 *
 * @property string $id
 * @property string $kind
 * @property string $detail
 * @property int|null $amount_minor
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $status
 * @property Carbon $detected_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_by_user_id
 * @property string|null $resolution_transaction_id
 */
final class ReconciliationException extends Model
{
    /** @use HasFactory<ReconciliationExceptionFactory> */
    use HasFactory, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kind', 'detail', 'amount_minor', 'reference_type', 'reference_id', 'status',
        'detected_at', 'resolved_at', 'resolved_by_user_id', 'resolution_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
