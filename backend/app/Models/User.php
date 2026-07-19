<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;
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
 * @property string|null $app_authentication_secret
 * @property array<int, string>|null $app_authentication_recovery_codes
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'party_id', 'phone_e164', 'email', 'password_hash', 'locale', 'comms_locale', 'status',
        'phone_verified_at', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = [
        'password_hash', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_hash' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
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

    // --- Filament admin (P1-09) ---

    /**
     * Only staff/admin may reach the admin panel — never customers or providers (doc 10 / CLAUDE.md).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(array_map(fn (Role $r) => $r->value, Role::staffRoles()));
    }

    /** The display name Filament shows — from the party, never a `name` column (we have none). */
    public function getFilamentName(): string
    {
        return $this->party->display_name;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email ?? $this->phone_e164;
    }

    /**
     * @return array<int, string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<int, string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
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
     * The provider profile is attached to the user's PARTY (a party can be a provider). Existence
     * of this is the `has_provider_profile` fact (doc 10).
     *
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class, 'party_id', 'party_id');
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
