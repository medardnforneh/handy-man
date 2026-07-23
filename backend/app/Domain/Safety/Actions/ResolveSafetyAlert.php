<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Models\SafetyAlert;
use App\Models\User;

/**
 * A staff member resolves (or acknowledges) a safety alert (build plan P6-04). Resolution is
 * attributable to the named admin.
 */
final class ResolveSafetyAlert
{
    public function handle(SafetyAlert $alert, User $resolver, string $status = 'resolved'): SafetyAlert
    {
        $alert->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? now() : null,
            'resolved_by_user_id' => $resolver->id,
        ]);

        return $alert;
    }
}
