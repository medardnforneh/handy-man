<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Verification\DocKind;
use App\Domain\Verification\DocStatus;
use Database\Factories\VerificationDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A verification document (doc 02/04). Stored encrypted in a bucket separate from public media and
 * served only through a signed short-TTL URL. An approved document raises the party's verification
 * tier (P6-03).
 *
 * @property string $id
 * @property string $party_id
 * @property string|null $subject_user_id
 * @property DocKind $kind
 * @property string $storage_path
 * @property string $sha256
 * @property int $grants_tier
 * @property DocStatus $status
 * @property string|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $reject_reason
 * @property Carbon|null $expires_at
 */
final class VerificationDocument extends Model
{
    /** @use HasFactory<VerificationDocumentFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'subject_user_id', 'kind', 'storage_path', 'sha256', 'grants_tier',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'reject_reason', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DocKind::class,
            'status' => DocStatus::class,
            'grants_tier' => 'integer',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
