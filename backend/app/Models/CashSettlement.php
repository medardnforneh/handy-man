<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CashSettlementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cash-settled amount recorded by a provider (doc 03/05). The platform books its commission as
 * revenue and as a `provider_receivable` debt; this row is the audit record of the cash movement.
 *
 * @property string $id
 * @property string $engagement_id
 * @property string|null $milestone_id
 * @property string $party_id
 * @property string $recorded_by_user_id
 * @property int $amount_minor
 * @property int $commission_minor
 * @property string $currency
 * @property string|null $ledger_transaction_id
 * @property Carbon $recorded_at
 */
final class CashSettlement extends Model
{
    /** @use HasFactory<CashSettlementFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'engagement_id', 'milestone_id', 'party_id', 'recorded_by_user_id', 'amount_minor',
        'commission_minor', 'currency', 'ledger_transaction_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'commission_minor' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
