<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Safety\Actions\BlockParty;
use App\Domain\Safety\Actions\ReportParty;
use App\Domain\Safety\Actions\UnblockParty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BlockPartyRequest;
use App\Http\Requests\Api\V1\ReportPartyRequest;
use App\Http\Resources\Api\V1\BlockResource;
use App\Http\Resources\Api\V1\ReportResource;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reports + blocks (build plan P6-07). A user files a report about, or blocks, another party — acting
 * as their own party. Blocks are honoured in search, ranking and offers (enforced in those Actions).
 */
final class SafetyController extends Controller
{
    public function blocks(Request $request): JsonResponse
    {
        $blocks = Block::query()->where('party_id', $this->user($request)->party_id)->get();

        return BlockResource::collection($blocks)->response();
    }

    public function block(BlockPartyRequest $request, BlockParty $action): JsonResponse
    {
        $block = $action->handle($this->user($request)->party_id, $request->string('blocked_party_id')->toString());

        return BlockResource::make($block)->response()->setStatusCode(201);
    }

    public function unblock(Request $request, string $party, UnblockParty $action): JsonResponse
    {
        $action->handle($this->user($request)->party_id, $party);

        return response()->json(['data' => ['status' => 'unblocked']]);
    }

    public function report(ReportPartyRequest $request, ReportParty $action): JsonResponse
    {
        $report = $action->handle(
            reporterPartyId: $this->user($request)->party_id,
            subjectPartyId: $request->string('subject_party_id')->toString(),
            category: $request->string('category')->toString(),
            body: $request->string('body')->toString(),
            jobId: $request->input('job_id'),
        );

        return ReportResource::make($report)->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
