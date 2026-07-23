<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WarrantyClaimFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A warranty claim (doc 06). Spawns a real remedy job (`remedy_job_id`) — a free fix with a real
 * assignment, not an email thread.
 *
 * @property string $id
 * @property string $warranty_id
 * @property string $claimed_by_party_id
 * @property string $description
 * @property string|null $remedy_job_id
 * @property string $status
 * @property Carbon|null $resolved_at
 */
final class WarrantyClaim extends Model
{
    /** @use HasFactory<WarrantyClaimFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'warranty_id', 'claimed_by_party_id', 'description', 'remedy_job_id', 'status', 'created_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Warranty, $this>
     */
    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }
}
