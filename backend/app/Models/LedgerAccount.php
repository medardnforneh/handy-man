<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\AccountKind;
use Database\Factories\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ledger account (doc 03). Platform-owned accounts have a null `party_id`; party-scoped accounts
 * (provider_payable, lead_credit_liability, …) name their party. One per (party, kind, currency).
 *
 * @property string $id
 * @property string|null $party_id
 * @property AccountKind $kind
 * @property string $currency
 */
final class LedgerAccount extends Model
{
    /** @use HasFactory<LedgerAccountFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['party_id', 'kind', 'currency'];

    protected function casts(): array
    {
        return ['kind' => AccountKind::class];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    /**
     * The current balance in minor units, computed from entries (debit +, credit −). This is the raw
     * signed balance; a credit-normal account (e.g. a liability) therefore reads negative here, and
     * the amount owed is its negation. Callers interpret via {@see AccountKind::isDebitNormal()}.
     */
    public function balanceMinor(): int
    {
        return (int) $this->entries()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE -amount_minor END), 0) AS bal")
            ->value('bal');
    }
}
