<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Support\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * One PAGE of the user's conversations, most recently active first.
     *
     * Ordering and paging happen in SQL now, and they have to: this used to load every membership,
     * then every MESSAGE of every one of those conversations (`lastMessagePerConversation` pulled
     * whole histories into memory to keyBy them and let later rows win), then sort the lot in PHP.
     * For a provider a year in, opening the messages tab read their entire chat history.
     *
     * "Last activity" is the newest surviving message, falling back to the conversation's own
     * creation time so a freshly-formed engagement appears at the bottom rather than vanishing —
     * the same rule {@see ConversationSummary::sortKey()} applies, moved to where it can be indexed
     * and paged.
     *
     * @return list<array{summary: ConversationSummary, conversation_id: string, last_activity_at: string}>
     */
    public function forUser(User $user, int $limit = 50, ?string $before = null): array
    {
        // The page, decided in one query: which conversations, in what order, and where to resume.
        $lastMessage = DB::table('messages')
            ->selectRaw('conversation_id, max(created_at) as last_at')
            ->whereNull('deleted_at')
            ->groupBy('conversation_id');

        $page = DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->leftJoinSub($lastMessage, 'lm', 'lm.conversation_id', '=', 'c.id')
            ->where('cp.user_id', $user->getKey())
            ->selectRaw('c.id as conversation_id, coalesce(lm.last_at, c.created_at) as last_activity_at');

        // The cursor compares the same expression the ordering uses, so a page boundary cannot
        // fall between two rows that sort equal.
        Cursor::applyBefore($page, 'coalesce(lm.last_at, c.created_at)', $before, 'c.id');

        $rows = $page
            ->orderByDesc('last_activity_at')
            ->orderByDesc('c.id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $conversationIds = $rows->pluck('conversation_id')->all();

        /** @var Collection<int, ConversationParticipant> $memberships */
        $memberships = ConversationParticipant::query()
            ->where('user_id', $user->getKey())
            ->whereIn('conversation_id', $conversationIds)
            ->get()
            ->keyBy('conversation_id');

        /** @var Collection<string, Conversation> $conversations */
        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with(['job.customer', 'job.engagement.provider'])
            ->get()
            ->keyBy('id');

        $lastMessages = $this->lastMessagePerConversation($conversationIds);
        $unread = $this->unreadCounts($memberships->values(), $user);

        $out = [];
        foreach ($rows as $row) {
            $conversation = $conversations->get($row->conversation_id);

            if ($conversation === null || ! $memberships->has($row->conversation_id)) {
                continue;
            }

            $out[] = [
                'conversation_id' => (string) $row->conversation_id,
                'last_activity_at' => (string) $row->last_activity_at,
                'summary' => new ConversationSummary(
                    conversation: $conversation,
                    lastMessage: $lastMessages->get($row->conversation_id),
                    unreadCount: $unread[$row->conversation_id] ?? 0,
                    counterpartName: $this->counterpartName($conversation, $user),
                ),
            ];
        }

        return $out;
    }

    /**
     * The newest surviving message of each conversation on this page, keyed by conversation id.
     *
     * `DISTINCT ON` is exactly one row per conversation, which is what this needs. It used to fetch
     * every message of every conversation and keyBy them so later rows overwrote earlier ones —
     * correct, and it read an entire chat history to produce one line of preview text each.
     *
     * @param  array<int, string>  $conversationIds
     * @return Collection<string, Message>
     */
    private function lastMessagePerConversation(array $conversationIds): Collection
    {
        return Message::query()
            ->selectRaw('distinct on (conversation_id) messages.*')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->orderByRaw('conversation_id, created_at desc, id desc')
            ->get()
            ->keyBy('conversation_id');
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
