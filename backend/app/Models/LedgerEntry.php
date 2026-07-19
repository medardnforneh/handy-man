<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\EntryDirection;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side of a double-entry posting (doc 03). Amount is strictly positive; `direction` carries the
 * sign. Append-only: never updated or deleted (DB trigger + REVOKE).
 *
 * @property string $id
 * @property string $transaction_id
 * @property string $account_id
 * @property EntryDirection $direction
 * @property int $amount_minor
 */
final class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['transaction_id', 'account_id', 'direction', 'amount_minor'];

    protected function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'amount_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
