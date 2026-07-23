<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes {@see ActivityLog} entries (build plan P6-02). The one place the app records sensitive
 * human actions — a verification-document view above all (doc 04: reads must be logged, not just
 * writes). Keep the action names stable + dotted (`subject.verb`); they are queried in the audit UI.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, ?Model $subject = null, ?string $actorUserId = null, array $context = [], ?string $ip = null): ActivityLog
    {
        return ActivityLog::query()->create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $context,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
