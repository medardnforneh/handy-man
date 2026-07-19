<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JobPhotoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $job_id
 * @property string $path
 * @property int $position
 */
final class JobPhoto extends Model
{
    /** @use HasFactory<JobPhotoFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['job_id', 'path', 'position', 'created_at'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
