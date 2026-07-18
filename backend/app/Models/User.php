<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * An individual identity (doc 02). Every user IS-A party of kind 'individual' (enforced by a DB
 * constraint trigger). Phone is the primary identifier; password is optional (OTP-first).
 *
 * @property string $id
 * @property string $party_id
 * @property string $phone_e164
 * @property string|null $email
 * @property string|null $password_hash
 * @property string $locale
 * @property string $comms_locale
 * @property string $status
 * @property Carbon|null $phone_verified_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'phone_e164', 'email', 'password_hash', 'locale', 'comms_locale', 'status',
        'phone_verified_at', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    /** Auth reads the hash from `password_hash`, not the framework-default `password` (doc 02). */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }
}
