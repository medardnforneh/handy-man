<?php

declare(strict_types=1);

namespace App\Domain\Execution\Actions;

use App\Domain\Execution\ProviderStatus;
use App\Domain\Workspace\ConversationManager;
use App\Domain\Workspace\Narrator;
use App\Models\Assignment;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * A working provider emits a structured status signal into the workspace timeline (build plan P5-06,
 * doc 06): on-the-way / started / paused / resumed / completed. The chat *is* the state machine, so
 * each signal is a server-narrated message (rule #11) — never a client-posted structured kind.
 * `arrived` is not here: it is emitted by the geo {@see CheckIn}.
 */
final class RecordStatus
{
    public function __construct(
        private readonly ConversationManager $conversations,
        private readonly Narrator $narrator,
    ) {}

    public function handle(Assignment $assignment, ProviderStatus $status): Message
    {
        return DB::transaction(function () use ($assignment, $status): Message {
            $engagement = $assignment->engagement()->firstOrFail();
            $conversation = $this->conversations->ensureForEngagement($engagement);

            return $this->narrator->narrate(
                $conversation,
                $status->messageKind(),
                senderUserId: $assignment->worker_user_id,
            );
        });
    }
}
