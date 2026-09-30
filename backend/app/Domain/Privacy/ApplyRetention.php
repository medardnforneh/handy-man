<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use App\Domain\Verification\DocStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\Conversation;
use App\Models\IdempotencyKey;
use App\Models\JobReport;
use App\Models\Media;
use App\Models\Message;
use App\Models\OtpChallenge;
use App\Models\RefreshToken;
use App\Models\VerificationDocument;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The retention schedule, applied (doc 04; `config/retention.php` is the schedule itself).
 *
 * Erasure (P1-10) answers "delete this human"; this answers the quieter obligation — personal
 * data must not outlive its purpose even for people who never ask. Until this existed nothing
 * did: every OTP row, every refresh token, every check-in coordinate and every rejected ID scan
 * stayed for ever, and the processing register would have had to say so.
 *
 * Each rule is one bounded statement so a slow night cannot lap the next; every count is
 * reported so the nightly log is the evidence that the schedule runs.
 */
final class ApplyRetention
{
    public function __construct(
        private readonly VerificationStorage $verification,
    ) {}

    /**
     * @param  bool  $dryRun  count what each rule WOULD destroy and destroy nothing. The workspace
     *                        rules below are the reason this exists: their periods are a starting
     *                        point rather than a finding, and nobody should have to discover what a
     *                        number means by watching it delete a year of somebody's threads.
     * @return array<string, int> rule → rows affected (or rows that would be)
     */
    public function handle(bool $dryRun = false): array
    {
        $days = fn (string $key): int => max(0, (int) config("retention.{$key}"));

        $report = [
            'otp_challenges' => $this->destroy(
                OtpChallenge::query()->where('expires_at', '<', now()->subDays($days('otp_challenges_days'))),
                $dryRun,
            ),

            'idempotency_keys' => $this->destroy(
                IdempotencyKey::query()->where('expires_at', '<', now()->subDays($days('idempotency_keys_days'))),
                $dryRun,
            ),

            'refresh_tokens' => $this->destroy(
                RefreshToken::query()->where(function ($q) use ($days): void {
                    $q->where('revoked_at', '<', now()->subDays($days('refresh_tokens_days')))
                        ->orWhere('expires_at', '<', now()->subDays($days('refresh_tokens_days')));
                }),
                $dryRun,
            ),

            // The coordinates only; the session row — that it happened, when, for how long — stays.
            'work_session_geo' => $this->scrub(
                WorkSession::query()
                    ->whereNotNull('ended_at')
                    ->where('ended_at', '<', now()->subDays($days('work_session_geo_days')))
                    ->where(function ($q): void {
                        $q->whereNotNull('start_point')->orWhereNotNull('end_point');
                    }),
                [
                    'start_point' => null, 'start_accuracy_m' => null,
                    'end_point' => null, 'end_accuracy_m' => null,
                    'updated_at' => DB::raw('updated_at'), // not an edit anyone made
                ],
                $dryRun,
            ),

            // The workspace, counted from the engagement ending. See config/retention.php for why
            // media and message text are on different clocks; 0 for either means "keep for ever".
            'engagement_media' => $this->purgeEngagementMedia($days('engagement_media_days'), $dryRun),

            'message_bodies' => $this->scrubMessageBodies($days('message_bodies_days'), $dryRun),
        ];

        $purged = 0;
        VerificationDocument::query()
            ->whereNull('purged_at')
            ->where(function ($q) use ($days): void {
                $q->where(function ($q) use ($days): void {
                    $q->where('status', DocStatus::Rejected->value)
                        ->where('reviewed_at', '<', now()->subDays($days('rejected_documents_days')));
                })->orWhere(function ($q) use ($days): void {
                    $q->whereNotNull('expires_at')
                        ->where('expires_at', '<', now()->subDays($days('expired_documents_days')));
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$purged, $dryRun): void {
                foreach ($documents as $document) {
                    if (! $dryRun) {
                        $this->verification->purge($document);
                    }
                    $purged++;
                }
            });
        $report['verification_documents'] = $purged;

        return $report;
    }

    /**
     * Delete the matched rows, or on a dry run count them and leave them alone.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function destroy(Builder $query, bool $dryRun): int
    {
        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * Null the named columns on the matched rows, or count them on a dry run.
     *
     * @param  Builder<covariant Model>  $query
     * @param  array<string, mixed>  $columns
     */
    private function scrub(Builder $query, array $columns, bool $dryRun): int
    {
        return $dryRun ? $query->count() : $query->update($columns);
    }

    /**
     * Destroy the media of engagements that ended longer ago than the period.
     *
     * Voice notes and report photos: a recording of someone speaking, and the inside of someone's
     * home. Nothing in the schedule covered them, so they were kept for ever — for everyone, not
     * only for people who asked to be erased.
     *
     * Media hangs off a message or a job report rather than an engagement, so the link runs
     * backwards through the conversation and the assignment. The ROW survives with `purged_at`, the
     * same shape erasure uses, so a thread shows that something was there instead of losing the
     * reference.
     */
    private function purgeEngagementMedia(int $days, bool $dryRun): int
    {
        if ($days === 0) {
            return 0; // kept for ever, deliberately (config/retention.php)
        }

        $cutoff = now()->subDays($days);

        // The conversations and reports belonging to engagements that ended before the cutoff.
        $messageIds = Message::query()
            ->select('messages.id')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('engagements', 'engagements.job_id', '=', 'conversations.job_id')
            ->whereNotNull('engagements.completed_at')
            ->where('engagements.completed_at', '<', $cutoff);

        $reportIds = JobReport::query()
            ->select('job_reports.id')
            ->join('assignments', 'assignments.id', '=', 'job_reports.assignment_id')
            ->join('engagements', 'engagements.id', '=', 'assignments.engagement_id')
            ->whereNotNull('engagements.completed_at')
            ->where('engagements.completed_at', '<', $cutoff);

        $query = Media::query()
            ->whereNull('purged_at')
            ->where(function ($q) use ($messageIds, $reportIds): void {
                $q->where(fn ($q) => $q->where('attachable_type', 'message')->whereIn('attachable_id', $messageIds))
                    ->orWhere(fn ($q) => $q->where('attachable_type', 'job_report')->whereIn('attachable_id', $reportIds));
            });

        if ($dryRun) {
            return $query->count();
        }

        $disk = (string) config('filesystems.default');
        $purged = 0;

        $query->orderBy('id')->chunkById(100, function ($items) use ($disk, &$purged): void {
            foreach ($items as $item) {
                Storage::disk($disk)->delete($item->storage_path);
                $item->forceFill(['purged_at' => now()])->save();
                $purged++;
            }
        });

        return $purged;
    }

    /**
     * Empty the bodies of messages in engagements that ended longer ago than the period.
     *
     * Longer than the media clock on purpose: the text is small, and it is the record a dispute is
     * argued from. Only `body` goes — the row, its kind, its sender and its place in the thread all
     * stay, so the conversation reads as redacted rather than truncated. Server-narrated messages
     * (`sender_user_id` null) are left alone: they carry no free text, only a `payload` the client
     * renders in the reader's own language.
     */
    private function scrubMessageBodies(int $days, bool $dryRun): int
    {
        if ($days === 0) {
            return 0; // kept for ever, deliberately (config/retention.php)
        }

        $query = Message::query()
            ->whereNotNull('body')
            ->whereNotNull('sender_user_id')
            ->whereIn('conversation_id', Conversation::query()
                ->select('conversations.id')
                ->join('engagements', 'engagements.job_id', '=', 'conversations.job_id')
                ->whereNotNull('engagements.completed_at')
                ->where('engagements.completed_at', '<', now()->subDays($days)));

        return $this->scrub($query, [
            'body' => null,
            'updated_at' => DB::raw('updated_at'), // not an edit anyone made
        ], $dryRun);
    }
}
