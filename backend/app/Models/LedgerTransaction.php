<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\TxnKind;
use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A balanced ledger transaction (doc 03). Append-only: never updated or deleted (DB trigger). Its
 * entries always sum to zero (deferred DB constraint).
 *
 * @property string $id
 * @property TxnKind $kind
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property Carbon $occurred_at
 * @property string|null $memo
 * @property string|null $created_by_user_id
 */
final class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kind', 'reference_type', 'reference_id', 'occurred_at', 'memo', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TxnKind::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }
}
