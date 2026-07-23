<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Safety\Actions\CreateEngagementShare;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\EngagementShare;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Share-my-job links (build plan P6-05). A participant (the customer or an assigned worker) mints a
 * signed, expiring, revocable link to share the engagement's live status with a family member.
 */
final class EngagementShareController extends Controller
{
    public function store(Request $request, Engagement $engagement, CreateEngagementShare $action): JsonResponse
    {
        $this->assertParticipant($engagement, $this->user($request));

        [$share, $rawToken] = $action->handle($engagement, $this->user($request));

        return response()->json([
            'data' => [
                'id' => $share->id,
                'url' => route('engagement.share', ['token' => $rawToken]),
                'expires_at' => $share->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request, EngagementShare $share): JsonResponse
    {
        $engagement = $share->engagement()->firstOrFail();
        $this->assertParticipant($engagement, $this->user($request));

        $share->update(['revoked_at' => now()]);

        return response()->json(['data' => ['status' => 'revoked']]);
    }

    /**
     * The caller must be the customer or an active assigned worker on the engagement.
     */
    private function assertParticipant(Engagement $engagement, User $user): void
    {
        $isCustomer = $engagement->job()->firstOrFail()->customer_party_id === $user->party_id;

        $isWorker = Assignment::query()
            ->where('engagement_id', $engagement->id)
            ->where('worker_user_id', $user->id)
            ->whereNull('removed_at')
            ->exists();

        abort_unless($isCustomer || $isWorker, 403, 'You are not a participant of this engagement.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
