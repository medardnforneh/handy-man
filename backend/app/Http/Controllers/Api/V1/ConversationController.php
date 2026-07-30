<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\UserConversations;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationSummaryResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The messages tab: every conversation this user is a participant of, and marking one read.
 *
 * The thread itself lives under the job (`/jobs/{job}/messages`, P4-01) — this is only the index
 * over them, so a user can find a conversation without first knowing which job it belongs to.
 */
final class ConversationController extends Controller
{
    public function index(Request $request, UserConversations $conversations): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ConversationSummaryResource::collection($conversations->forUser($user));
    }

    /**
     * Mark the conversation read up to now.
     *
     * Without this the unread count would only ever grow, so it is part of the same slice rather
     * than a later addition. It is idempotent and moves only forward: re-reading an old thread must
     * never resurrect messages the user has already seen, which a blind overwrite could do if two
     * devices report out of order.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->getKey())
            ->first();

        abort_if($participant === null, 403);

        $now = now();
        if ($participant->last_read_at === null || $participant->last_read_at->lt($now)) {
            $participant->last_read_at = $now;
            $participant->save();
        }

        return response()->json(['data' => ['last_read_at' => $participant->last_read_at->toIso8601String()]]);
    }
}
