<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Models\Conversation;
use App\Models\Message;

/**
 * One row of the messages tab: a conversation, its newest message, and what it means to THIS user.
 *
 * A read model, deliberately — the list needs a shape the models don't have (the "other side" of a
 * conversation depends on who is asking), and computing that in a resource would put a per-viewer
 * decision in the serialisation layer.
 */
final readonly class ConversationSummary
{
    public function __construct(
        public Conversation $conversation,
        public ?Message $lastMessage,
        public int $unreadCount,
        public ?string $counterpartName,
    ) {}

    /**
     * Sort by last activity, newest first. A conversation with no messages yet falls back to the
     * conversation's own creation time so a freshly-formed engagement still appears, at the bottom,
     * rather than vanishing from the list.
     */
    public function sortKey(): string
    {
        return ($this->lastMessage === null ? $this->conversation->created_at : $this->lastMessage->created_at)
            ->toIso8601String();
    }
}
