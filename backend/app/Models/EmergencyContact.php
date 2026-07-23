<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmergencyContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's emergency contact (doc 02/04) — texted when the user raises a panic alert.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $phone_e164
 * @property Carbon $created_at
 */
final class EmergencyContact extends Model
{
    /** @use HasFactory<EmergencyContactFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'name', 'phone_e164', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
