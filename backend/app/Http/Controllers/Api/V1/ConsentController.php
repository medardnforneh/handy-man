<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\RecordConsent;
use App\Domain\Identity\Consent\ConsentState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordConsentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consent management (build plan P1-05, doc 04). Granular, versioned, revocable. Every decision is
 * recorded with the locale the policy was shown in.
 */
final class ConsentController extends Controller
{
    public function index(Request $request, ConsentState $state): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $state->currentFor($user);

        // Report every known purpose, defaulting to not-granted.
        $data = [];
        foreach ((array) config('consent.purposes') as $purpose) {
            $data[$purpose] = $current[$purpose] ?? false;
        }

        return response()->json(['data' => $data, 'policy_version' => config('consent.policy_version')]);
    }

    public function store(RecordConsentRequest $request, RecordConsent $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $consent = $action->handle(
            user: $user,
            purpose: $request->string('purpose')->toString(),
            granted: $request->boolean('granted'),
            presentedLocale: $request->string('presented_locale')->toString(),
        );

        return response()->json([
            'purpose' => $consent->purpose,
            'granted' => $consent->granted,
            'presented_locale' => $consent->presented_locale,
            'policy_version' => $consent->policy_version,
        ], 201);
    }
}
