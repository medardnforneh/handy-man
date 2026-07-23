<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\Reviews\ReviewVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Engagement;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Double-blind reviews (build plan P6-08). A party submits a review of the other; it stays hidden
 * until both submit or the window closes. Published reviews for a subject party are public.
 */
final class ReviewController extends Controller
{
    public function store(SubmitReviewRequest $request, Engagement $engagement, SubmitReview $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // NotEngagementParty (403) / AlreadyReviewed (409) render as problem+json automatically.
        $review = $action->handle(
            $engagement,
            $user,
            (int) $request->integer('rating'),
            $request->input('body'),
            $request->input('private_note'),
            $request->input('subject_worker_user_id'),
        );

        return ReviewResource::make($review)->response()->setStatusCode(201);
    }

    public function forParty(string $party): JsonResponse
    {
        $reviews = Review::query()
            ->where('subject_party_id', $party)
            ->where('visibility', ReviewVisibility::Published->value)
            ->latest('published_at')
            ->get();

        return ReviewResource::collection($reviews)->response();
    }
}
