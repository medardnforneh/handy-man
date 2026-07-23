<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Domain\Jobs\EngagementModePolicy;
use App\Domain\Safety\ShareNotSupported;
use App\Models\Engagement;
use App\Models\EngagementShare;
use App\Models\User;

/**
 * Creates a share-my-job link (build plan P6-05). Only for physical work (share-my-job is a
 * presence-safety feature — the mode policy is the single authority). Returns the persisted share and
 * the raw opaque token; only the hash is stored, so the URL is the sole bearer of the secret.
 */
final class CreateEngagementShare
{
    public function __construct(private readonly EngagementModePolicy $modePolicy) {}

    /**
     * @return array{0: EngagementShare, 1: string}
     */
    public function handle(Engagement $engagement, User $creator): array
    {
        $job = $engagement->job()->firstOrFail();

        if (! $this->modePolicy->supportsShareJob($job->engagement_mode)) {
            throw new ShareNotSupported;
        }

        $rawToken = bin2hex(random_bytes(32)); // 256-bit opaque token

        $share = EngagementShare::query()->create([
            'engagement_id' => $engagement->id,
            'token_hash' => hash('sha256', $rawToken),
            'created_by_user_id' => $creator->id,
            'expires_at' => now()->addHours((int) config('safety.share_ttl_hours', 8)),
            'created_at' => now(),
        ]);

        return [$share, $rawToken];
    }
}
