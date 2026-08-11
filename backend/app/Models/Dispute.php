<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dispute on an engagement (doc 04). Raised by a party, adjudicated by a human admin. Any money
 * effect is a balanced adjustment transaction linked here and attributed to the admin.
 *
 * @property string $id
 * @property string $engagement_id
 * @property string $raised_by_party_id
 * @property string $category
 * @property string $body
 * @property string $status
 * @property string|null $resolution_note
 * @property string|null $resolution_transaction_id
 * @property string|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 */
final class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'engagement_id', 'raised_by_party_id', 'category', 'body', 'status',
        'resolution_note', 'resolution_transaction_id', 'resolved_by_user_id', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Engagement, $this>
     */
    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'raised_by_party_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
