<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\FollowUps\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FollowUpResource;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Follow-ups for the target user (build plan P7-07). Every follow-up has exactly one response action;
 * recording the tap (`response_action`) is how the follow-up's effectiveness is measured — and how
 * the ones that don't earn their place get killed.
 */
final class FollowUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $followUps = FollowUp::query()
            ->where('target_user_id', $this->user($request)->id)
            ->whereIn('status', [FollowUpStatus::Sent->value, FollowUpStatus::Scheduled->value, FollowUpStatus::Responded->value])
            ->latest('scheduled_for')
            ->limit(50)
            ->get();

        return FollowUpResource::collection($followUps)->response();
    }

    public function respond(Request $request, FollowUp $followUp): JsonResponse
    {
        abort_unless($followUp->target_user_id === $this->user($request)->id, 403);

        $validated = $request->validate([
            'response_action' => ['required', 'string', 'max:100', Rule::in([
                'quote_accepted', 'review_submitted', 'warranty_claimed', 'rebooked', 'approved', 'dismissed', 'opened',
            ])],
        ]);

        $followUp->update([
            'status' => FollowUpStatus::Responded->value,
            'responded_at' => now(),
            'response_action' => $validated['response_action'],
        ]);

        return FollowUpResource::make($followUp)->response();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
