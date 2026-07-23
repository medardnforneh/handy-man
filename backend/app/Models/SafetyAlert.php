<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Safety\SafetyAlertKind;
use Database\Factories\SafetyAlertFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

/**
 * A safety alert (doc 02/04). Raised by the panic button (or the check-in watchdog), it captures the
 * user's location and feeds the staff safety queue. Resolution is attributable to a named admin.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $assignment_id
 * @property SafetyAlertKind $kind
 * @property Point|null $point
 * @property string|null $note
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolved_by_user_id
 */
final class SafetyAlert extends Model
{
    /** @use HasFactory<SafetyAlertFactory> */
    use HasFactory, HasSpatial, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'assignment_id', 'kind', 'point', 'note', 'status',
        'created_at', 'resolved_at', 'resolved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SafetyAlertKind::class,
            'point' => Point::class,
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
