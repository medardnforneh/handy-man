<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reference;

use App\Domain\Reference\Actions\CreateNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reference\StoreNoteRequest;
use App\Http\Resources\Api\V1\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reference controller (P0-05). Controllers are THIN: they validate (via FormRequest), authorize
 * (via Policy), call an Action, and return a Resource. No business logic, no transactions, no
 * fan-out here.
 */
final class NoteController extends Controller
{
    public function store(StoreNoteRequest $request, CreateNote $createNote): JsonResponse
    {
        $note = $createNote->handle(
            author: $request->user(),
            body: $request->string('body')->toString(),
        );

        return NoteResource::make($note)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Note $note): NoteResource
    {
        $this->authorize('view', $note);

        return NoteResource::make($note);
    }
}
