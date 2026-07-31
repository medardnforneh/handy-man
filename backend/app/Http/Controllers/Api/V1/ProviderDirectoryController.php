<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\EngagementModePolicy;
use App\Domain\Provider\PublicProviderDirectory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicProviderResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Browse providers by trade — the app's discover rail, and the API twin of the crawlable services
 * directory.
 *
 * Deliberately UNAUTHENTICATED: a customer decides whether this marketplace has anyone worth hiring
 * before they create an account, and requiring a login to look would invert that. A Bearer is
 * optional and changes exactly one thing — blocks are honoured for whoever is holding it.
 */
final class ProviderDirectoryController extends Controller
{
    public function __construct(
        private readonly EngagementModePolicy $modePolicy,
    ) {}

    public function index(Request $request, PublicProviderDirectory $directory): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'skill' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:onsite,remote'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // The sanctum guard is asked directly rather than via `auth:sanctum` middleware: this route
        // must answer an anonymous browser, so authentication is read when offered and never
        // required. An absent or expired token simply means null — no 401.
        /** @var User|null $user */
        $user = $request->user('sanctum');

        // The client speaks in engagement modes because that is the vocabulary the rest of the app
        // uses. Translating one into "does this provider travel" is a mode decision, so it goes
        // through the policy rather than being an inline comparison (P2-02): dispatch/coverage is
        // precisely the thing that requires a service area.
        $mode = EngagementMode::tryFrom((string) ($validated['mode'] ?? ''));

        $providers = $directory->browse(
            skillSlug: $validated['skill'] ?? null,
            viewerPartyId: $user?->party_id,
            travels: $mode === null ? null : $this->modePolicy->supportsDispatch($mode),
            limit: (int) ($validated['limit'] ?? 30),
        );

        return PublicProviderResource::collection($providers);
    }

    /**
     * One provider's public card, keyed by PARTY.
     *
     * Before this, the app's profile screen could only get a headline by re-reading a job's match
     * list, which meant it needed a job it might not have — and fell back to demo data when it
     * didn't. A public profile shouldn't depend on the route the viewer arrived by.
     */
    public function show(Request $request, string $party, PublicProviderDirectory $directory): PublicProviderResource
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        $profile = $directory->show($party, $user?->party_id);
        abort_if($profile === null, 404, 'No such provider.');

        return PublicProviderResource::make($profile);
    }
}
