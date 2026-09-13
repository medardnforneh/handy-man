<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use App\Domain\Verification\DocStatus;
use App\Domain\Verification\VerificationStorage;
use App\Models\IdempotencyKey;
use App\Models\OtpChallenge;
use App\Models\RefreshToken;
use App\Models\VerificationDocument;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;

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
     * @return array<string, int> rule → rows affected
     */
    public function handle(): array
    {
        $days = fn (string $key): int => max(0, (int) config("retention.{$key}"));

        $report = [
            'otp_challenges' => OtpChallenge::query()
                ->where('expires_at', '<', now()->subDays($days('otp_challenges_days')))
                ->delete(),

            'idempotency_keys' => IdempotencyKey::query()
                ->where('expires_at', '<', now()->subDays($days('idempotency_keys_days')))
                ->delete(),

            'refresh_tokens' => RefreshToken::query()
                ->where(function ($q) use ($days): void {
                    $q->where('revoked_at', '<', now()->subDays($days('refresh_tokens_days')))
                        ->orWhere('expires_at', '<', now()->subDays($days('refresh_tokens_days')));
                })
                ->delete(),

            // The coordinates only; the session row — that it happened, when, for how long — stays.
            'work_session_geo' => WorkSession::query()
                ->whereNotNull('ended_at')
                ->where('ended_at', '<', now()->subDays($days('work_session_geo_days')))
                ->where(function ($q): void {
                    $q->whereNotNull('start_point')->orWhereNotNull('end_point');
                })
                ->update([
                    'start_point' => null, 'start_accuracy_m' => null,
                    'end_point' => null, 'end_accuracy_m' => null,
                    'updated_at' => DB::raw('updated_at'), // not an edit anyone made
                ]),
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
            ->chunkById(100, function ($documents) use (&$purged): void {
                foreach ($documents as $document) {
                    $this->verification->purge($document);
                    $purged++;
                }
            });
        $report['verification_documents'] = $purged;

        return $report;
    }
}
