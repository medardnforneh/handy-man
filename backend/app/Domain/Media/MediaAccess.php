<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Broadcasting\ChannelAccess;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\JobReport;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;

/**
 * Who may fetch a stored media file (build plan P4-05/P5-04).
 *
 * Media is polymorphic, so entitlement is decided by what it hangs off — never by possession of an
 * id. Extracted from the controller so the rules are unit-testable without HTTP, the way
 * {@see ChannelAccess} is.
 *
 * Verification documents are deliberately NOT reachable here: they have their own signed,
 * short-TTL route that logs every view (P6-01/02), and routing them through a general media
 * endpoint would bypass that audit trail.
 */
final class MediaAccess
{
    public static function canView(User $user, Media $media): bool
    {
        return match ($media->attachable_type) {
            'message' => self::canViewMessageMedia($user, $media->attachable_id),
            'job_report' => self::canViewReportMedia($user, $media->attachable_id),
            default => false,
        };
    }

    /** A voice note or attachment in a thread: the conversation's participants, and only them. */
    private static function canViewMessageMedia(User $user, string $messageId): bool
    {
        $message = Message::query()->find($messageId);
        if ($message === null) {
            return false;
        }

        $conversation = Conversation::query()->find($message->conversation_id);

        return $conversation !== null && $conversation->hasParticipant((string) $user->getKey());
    }

    /**
     * On-site report photos: the assigned worker who filed it, and the customer whose job it is —
     * the same two sides the workspace thread already shows them to.
     */
    private static function canViewReportMedia(User $user, string $reportId): bool
    {
        $report = JobReport::query()->find($reportId);
        if ($report === null) {
            return false;
        }

        $assignment = Assignment::query()->with('engagement.job')->find($report->assignment_id);
        if ($assignment === null) {
            return false;
        }

        if ((string) $assignment->worker_user_id === (string) $user->getKey()) {
            return true;
        }

        return $assignment->engagement?->job?->customer_party_id === $user->party_id;
    }
}
