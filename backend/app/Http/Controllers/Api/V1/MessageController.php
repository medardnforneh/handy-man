<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Actions\PostMessage;
use App\Domain\Workspace\Actions\PostVoiceMessage;
use App\Http\Controllers\Controller;
use App\Support\Cursor;
use App\Http\Requests\Api\V1\PostMessageRequest;
use App\Http\Requests\Api\V1\PostVoiceMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The workspace conversation (build plan P4-01/02). Participants read and post free-form messages;
 * structured messages are narrated by the server and never accepted from a client.
 */
final class MessageController extends Controller
{
    public function index(Request $request, Job $job): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->participantConversation($job, $user);

        // The thread is keyed by the job, but the live channel is keyed by the ENGAGEMENT
        // (`engagement.{id}`, P4-03/04) — so hand the client the id it needs to subscribe rather
        // than making it hunt for one it may not be entitled to read elsewhere.
        $engagementId = Engagement::query()->where('job_id', $job->id)->value('id');

        // The thread, oldest-last, capped — and ORDERED, which it was not. There was no ORDER BY
        // at all here, so the order of a chat was whatever Postgres happened to return; and there
        // was no limit either, so every open re-fetched the entire history with its media over a
        // mobile network. A long engagement's thread was the heaviest response the API served.
        //
        // The window is the NEWEST `limit` messages, handed back ASCENDING — which is the order the
        // app appends into and renders (rule #4: it must keep reading `data` exactly as before).
        // So the query takes them descending and the page is reversed.
        $limit = Cursor::limit($request->query('limit'), 100);

        $window = $conversation->messages()->with('media')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // `before` pages BACKWARDS through the history — older than what the client already holds.
        Cursor::applyBefore($window, 'messages.created_at', $request->query('before'));

        $found = $window->limit($limit + 1)->get();
        $hasMore = $found->count() > $limit;
        $page = $found->take($limit);
        $oldest = $page->last(); // still the descending order here, so `last` is the oldest

        return MessageResource::collection($page->reverse()->values())
            ->additional(['meta' => [
                'conversation_id' => $conversation->id,
                'engagement_id' => $engagementId,
                // Named for what it does: fetch the messages BEFORE this page. A chat pages into
                // its past, so calling it `next_cursor` would read backwards.
                'has_older' => $hasMore,
                'older_cursor' => $hasMore && $oldest !== null
                    ? Cursor::encode($oldest->created_at->toIso8601String(), $oldest->id)
                    : null,
            ]]);
    }

    public function store(PostMessageRequest $request, Job $job, PostMessage $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->participantConversation($job, $user);

        $message = $action->handle(
            sender: $user,
            conversation: $conversation,
            body: $request->string('body')->toString(),
            replyToId: $request->input('reply_to_id'),
        );

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    /**
     * A voice note (P4-05) — the audio rides as attached media, the message is kind `voice`. Speaking
     * a problem is far easier than typing it in a second language, so this is a first-class entry in
     * the thread rather than an attachment bolted onto a text message.
     */
    public function storeVoice(PostVoiceMessageRequest $request, Job $job, PostVoiceMessage $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = $this->participantConversation($job, $user);

        $message = $action->handle($user, $conversation, $request->audio(), $request->durationMs());

        return MessageResource::make($message->load('media'))->response()->setStatusCode(201);
    }

    /**
     * Resolve the job's conversation and assert the user is a participant.
     */
    private function participantConversation(Job $job, User $user): Conversation
    {
        $conversation = Conversation::query()->where('job_id', $job->id)->first();
        abort_if($conversation === null, 404, 'This job has no conversation yet.');
        abort_unless($conversation->hasParticipant($user->id), 403);

        return $conversation;
    }
}
