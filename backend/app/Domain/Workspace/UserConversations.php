<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The signed-in user's conversation list — the messages tab.
 *
 * Membership is the ONLY thing that decides what appears here: a row exists for a conversation
 * exactly when `conversation_participants` says so. That keeps this consistent with the read and
 * post endpoints (which gate on the same table) rather than inventing a second, parallel notion of
 * "your chats" that could drift from who may actually open the thread.
 *
 * It answers with one row per conversation, most recently active first, carrying only what a list
 * needs: who the other side is, what the last message was, and how many are unread.
 */
final class UserConversations
{
    /**
     * @return Collection<int, ConversationSummary>
     */
    public function forUser(User $user): Collection
    {
        /** @var Collection<int, ConversationParticipant> $memberships */
        $memberships = ConversationParticipant::query()
            ->where('user_id', $user->getKey())
            ->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $conversationIds = $memberships->pluck('conversation_id')->all();

        /** @var Collection<string, Conversation> $conversations */
        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with(['job.customer', 'job.engagement.provider'])
            ->get()
            ->keyBy('id');

        // One query for the last message of every conversation, rather than one per row: the list is
        // unbounded (a busy provider accumulates conversations forever) and N+1 here would be felt
        // on exactly the connection this product cannot rely on.
        $lastMessages = $this->lastMessagePerConversation($conversationIds);
        $unread = $this->unreadCounts($memberships, $user);

        return $memberships
            ->map(function (ConversationParticipant $membership) use ($conversations, $lastMessages, $unread, $user): ?ConversationSummary {
                $conversation = $conversations->get($membership->conversation_id);
                if ($conversation === null) {
                    return null;
                }

                return new ConversationSummary(
                    conversation: $conversation,
                    lastMessage: $lastMessages->get($membership->conversation_id),
                    unreadCount: $unread[$membership->conversation_id] ?? 0,
                    counterpartName: $this->counterpartName($conversation, $user),
                );
            })
            ->filter()
            ->sortByDesc(fn (ConversationSummary $row): string => $row->sortKey())
            ->values();
    }

    /**
     * The newest message of each conversation, keyed by conversation id.
     *
     * @param  array<int, string>  $conversationIds
     * @return Collection<string, Message>
     */
    private function lastMessagePerConversation(array $conversationIds): Collection
    {
        return Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->keyBy('conversation_id'); // later rows overwrite earlier ones → the newest wins
    }

    /**
     * Unread counts per conversation: messages after this participant's `last_read_at` that they did
     * not send themselves. A participant who has never opened the thread has read nothing, so every
     * message from the other side counts — which is the honest answer for a brand-new engagement.
     *
     * @param  Collection<int, ConversationParticipant>  $memberships
     * @return array<string, int>
     */
    private function unreadCounts(Collection $memberships, User $user): array
    {
        $counts = [];
        foreach ($memberships as $membership) {
            $query = Message::query()
                ->where('conversation_id', $membership->conversation_id)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('sender_user_id')->orWhere('sender_user_id', '!=', $user->getKey()));

            if ($membership->last_read_at !== null) {
                $query->where('created_at', '>', $membership->last_read_at);
            }

            $counts[$membership->conversation_id] = $query->count();
        }

        return $counts;
    }

    /**
     * Who the user is talking to. Post-engagement the two sides already know each other — the
     * workspace header has shown the provider's name since P4-06 — so a display name here leaks
     * nothing new. A job with no engagement yet has no provider to name, and falls back to the job
     * title, which is what the customer would recognise anyway.
     */
    private function counterpartName(Conversation $conversation, User $user): ?string
    {
        $job = $conversation->job;
        if ($job === null) {
            return null;
        }

        $isCustomer = $job->customer_party_id === $user->party_id;

        return $isCustomer
            ? $job->engagement?->provider?->display_name
            : $job->customer?->display_name;
    }
}
