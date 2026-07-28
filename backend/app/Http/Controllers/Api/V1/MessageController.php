<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Actions\PostMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PostMessageRequest;
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

        return MessageResource::collection($conversation->messages()->get())
            ->additional(['meta' => [
                'conversation_id' => $conversation->id,
                'engagement_id' => $engagementId,
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
