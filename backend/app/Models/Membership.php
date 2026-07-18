<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a user to an organization with an org-internal role (doc 02). These roles (owner,
 * dispatcher, finance, worker) are real RBAC within a company — distinct from the fact-gated
 * customer/provider access model (doc 10). See [[App\Domain\Access\Role]].
 *
 * @property string $id
 * @property string $user_id
 * @property string $organization_id
 * @property string $role
 * @property string $status
 */
final class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'organization_id', 'role', 'status', 'invited_by_user_id', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
