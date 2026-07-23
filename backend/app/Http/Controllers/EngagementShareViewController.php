<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EngagementShare;
use App\Models\WorkSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The public share-my-job page (build plan P6-05, doc 04). Resolves the opaque token to an active
 * share and renders a read-only, PII-minimised status view — provider first name, approximate
 * location, live status. An expired or revoked token is a 404. No auth: the unguessable token is the
 * grant, and it expires.
 */
final class EngagementShareViewController extends Controller
{
    public function __invoke(string $token): View
    {
        $share = EngagementShare::query()->where('token_hash', hash('sha256', $token))->first();

        abort_if($share === null || ! $share->isActive(), 404);

        $engagement = $share->engagement()->with(['job.address', 'provider'])->firstOrFail();
        $job = $engagement->job;

        $openSession = WorkSession::query()
            ->whereHas('assignment', fn (Builder $q) => $q->where('engagement_id', $engagement->id))
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        $anySession = WorkSession::query()
            ->whereHas('assignment', fn (Builder $q) => $q->where('engagement_id', $engagement->id))
            ->exists();

        $status = $openSession !== null ? 'on_site' : ($anySession ? 'completed' : 'scheduled');

        return view('public.engagement-share', [
            'firstName' => Str::of($engagement->provider->display_name)->trim()->explode(' ')->first(),
            'address' => $job->address,
            'status' => $status,
            'startedAt' => $openSession?->started_at,
            'expiresAt' => $share->expires_at,
        ]);
    }
}
