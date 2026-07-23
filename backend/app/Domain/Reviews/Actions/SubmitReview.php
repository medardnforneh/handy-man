<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\AlreadyReviewed;
use App\Domain\Reviews\NotEngagementParty;
use App\Domain\Reviews\PublishEngagementReviews;
use App\Domain\Reviews\ReviewVisibility;
use App\Models\Engagement;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One party submits a review of the other (build plan P6-08). The review rests `pending` — hidden
 * from everyone — until BOTH parties submit or the shared window closes. When the second party
 * submits, both reveal at once. This is the whole point: no one can read the counterparty's review
 * before committing their own, so retaliation and reciprocation can't shape the signal.
 *
 * The window is a property of the engagement: the first submission fixes `window_closes_at`, and the
 * second inherits it, so both sides genuinely share one deadline.
 */
final class SubmitReview
{
    public function __construct(private readonly PublishEngagementReviews $publisher) {}

    public function handle(
        Engagement $engagement,
        User $author,
        int $rating,
        ?string $body = null,
        ?string $privateNote = null,
        ?string $subjectWorkerUserId = null,
    ): Review {
        $subjectPartyId = $this->subjectFor($engagement, $author->party_id);
        $windowClosesAt = $this->windowFor($engagement);

        try {
            $review = DB::transaction(function () use ($engagement, $author, $subjectPartyId, $rating, $body, $privateNote, $subjectWorkerUserId, $windowClosesAt): Review {
                $review = Review::query()->create([
                    'engagement_id' => $engagement->id,
                    'author_party_id' => $author->party_id,
                    'subject_party_id' => $subjectPartyId,
                    'subject_worker_user_id' => $subjectWorkerUserId,
                    'rating' => $rating,
                    'body' => $body,
                    'private_note' => $privateNote,
                    'visibility' => ReviewVisibility::Pending->value,
                    'submitted_at' => now(),
                    'window_closes_at' => $windowClosesAt,
                ]);

                // Both parties have now submitted → reveal both at once.
                if (Review::query()->where('engagement_id', $engagement->id)->count() >= 2) {
                    $this->publisher->handle($engagement->id);
                }

                return $review;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new AlreadyReviewed;
            }
            throw $e;
        }

        return $review->refresh();
    }

    /**
     * The party being reviewed — the other side of the engagement. Throws if the author is neither.
     */
    private function subjectFor(Engagement $engagement, string $authorPartyId): string
    {
        $customerPartyId = $engagement->job()->firstOrFail()->customer_party_id;
        $providerPartyId = $engagement->provider_party_id;

        return match ($authorPartyId) {
            $customerPartyId => $providerPartyId,
            $providerPartyId => $customerPartyId,
            default => throw new NotEngagementParty,
        };
    }

    /**
     * The shared window: reuse the deadline the first submission set, else open a fresh one.
     */
    private function windowFor(Engagement $engagement): Carbon
    {
        $existing = Review::query()->where('engagement_id', $engagement->id)->value('window_closes_at');

        if ($existing !== null) {
            return Carbon::parse((string) $existing);
        }

        return now()->addDays((int) config('reviews.window_days', 14));
    }
}
