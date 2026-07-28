<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Jobs\AssignmentMode;
use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A job — the unit of demand (doc 02/06). Stored in `service_jobs` (Laravel owns `jobs` for its
 * queue). Geography is conditional on engagement_mode: a remote job has no address (DB CHECK).
 * Status only ever changes through App\Domain\Jobs\JobStateMachine (CLAUDE.md rule #8).
 *
 * @property string $id
 * @property string $reference
 * @property string $customer_party_id
 * @property string $created_by_user_id
 * @property string $skill_id
 * @property string|null $address_id
 * @property string $title
 * @property EngagementMode $engagement_mode
 * @property AssignmentMode $assignment_mode
 * @property JobStatus $status
 * @property string $price_model
 * @property int|null $budget_minor
 * @property string $currency
 * @property int $urgency
 * @property bool $requires_verified_provider
 * @property string|null $description
 * @property Carbon|null $published_at
 * @property Carbon|null $cancelled_at
 */
final class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory, HasUuids;

    protected $table = 'service_jobs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'reference', 'customer_party_id', 'created_by_user_id', 'skill_id', 'address_id',
        'title', 'description', 'description_language', 'engagement_mode', 'assignment_mode',
        'price_model', 'status', 'budget_minor', 'currency', 'urgency', 'requires_verified_provider',
        'published_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'engagement_mode' => EngagementMode::class,
            'assignment_mode' => AssignmentMode::class,
            'status' => JobStatus::class,
            'budget_minor' => 'integer',
            'urgency' => 'integer',
            'requires_verified_provider' => 'boolean',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return HasMany<JobPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(JobPhoto::class)->orderBy('position');
    }

    /**
     * The one engagement this job converged on, if any (unique per job; P2-06).
     *
     * @return HasOne<Engagement, $this>
     */
    public function engagement(): HasOne
    {
        return $this->hasOne(Engagement::class);
    }
}
