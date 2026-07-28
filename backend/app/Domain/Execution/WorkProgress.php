<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Workspace\MessageKind;
use App\Models\Assignment;
use App\Models\JobReport;
use App\Models\Message;
use App\Models\WorkSession;

/**
 * The current execution state of one worker's assignment (build plan P5-03/P5-06), read back for the
 * provider's work-detail screen so the client renders the affordances the server would actually
 * accept — check-in vs check-out, the next status signal, whether the report is already in.
 *
 * Nothing here is a stored flag: the state is *derived* from the same rows the Actions write, so a
 * client can never drift from the server. The open work session is the check-in truth; the narrated
 * timeline is the status truth (the chat IS the state machine, doc 06).
 */
final class WorkProgress
{
    /**
     * The narrated kinds that represent an execution status, newest-wins. `arrived` is included even
     * though {@see Actions\RecordStatus} refuses to post it — check-in narrates it.
     */
    private const STATUS_KINDS = [
        MessageKind::OnTheWay,
        MessageKind::Arrived,
        MessageKind::Started,
        MessageKind::Paused,
        MessageKind::Resumed,
        MessageKind::Completed,
    ];

    /** The worker's currently open (checked-in, not yet checked-out) session, if any. */
    public function openSession(Assignment $assignment): ?WorkSession
    {
        return WorkSession::query()
            ->where('assignment_id', $assignment->id)
            ->whereNull('ended_at')
            ->first();
    }

    /**
     * The latest execution status narrated for this assignment's worker, or null before any signal.
     * Scoped to the engagement's conversation and to messages this worker sent, so a two-worker
     * engagement doesn't show one worker another's progress.
     */
    public function currentStatus(Assignment $assignment, string $conversationId): ?MessageKind
    {
        $kinds = array_map(static fn (MessageKind $k): string => $k->value, self::STATUS_KINDS);

        $message = Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_user_id', $assignment->worker_user_id)
            ->whereIn('kind', $kinds)
            ->orderByDesc('created_at')
            ->first();

        return $message === null ? null : $message->kind;
    }

    /** Whether this assignment's job report has been submitted (the on-site proof of work, P5-04). */
    public function reportSubmitted(Assignment $assignment): bool
    {
        return JobReport::query()
            ->where('assignment_id', $assignment->id)
            ->whereNotNull('submitted_at')
            ->exists();
    }
}
