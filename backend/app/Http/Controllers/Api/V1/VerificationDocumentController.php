<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Verification\Actions\SubmitVerificationDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitVerificationDocumentRequest;
use App\Http\Resources\Api\V1\VerificationDocumentResource;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verification document submission + status (build plan P6-01). A user uploads their own identity /
 * licence documents for admin review; the file is encrypted at rest and only ever served through a
 * signed short-TTL URL (handled in the admin panel, P6-02). Storage paths are never returned.
 */
final class VerificationDocumentController extends Controller
{
    public function store(SubmitVerificationDocumentRequest $request, SubmitVerificationDocument $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $document = $action->handle($user, $request->kind(), $request->documentFile(), $request->expiresAt());

        return VerificationDocumentResource::make($document)->response()->setStatusCode(201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $documents = VerificationDocument::query()
            ->where('party_id', $user->party_id)
            ->latest('created_at')
            ->get();

        return VerificationDocumentResource::collection($documents)->response();
    }
}
