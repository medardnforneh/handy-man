<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * P0-05 acceptance: the worked vertical slice exercises every layer end to end —
 * auth → FormRequest validation → Action (transaction + outbox) → Resource, and the Policy on
 * read. Also confirms the slice honours the global API infrastructure (idempotency).
 */
it('creates a note through the full stack and announces it via the outbox', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        '/api/v1/notes',
        ['body' => 'Fix the leaking tap in the kitchen.'],
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'author_id', 'body', 'created_at', 'updated_at']])
        ->assertJsonPath('data.author_id', $user->id)
        ->assertJsonPath('data.body', 'Fix the leaking tap in the kitchen.');

    $this->assertDatabaseHas('notes', ['author_id' => $user->id]);

    // The Action published a fan-out message in the same transaction.
    expect(OutboxMessage::where('type', 'note.created')->count())->toBe(1);
});

it('rejects an unauthenticated create with problem+json 401', function () {
    $this->postJson('/api/v1/notes', ['body' => 'x'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Unauthenticated');
});

it('validates the request body with problem+json 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/notes', ['body' => ''], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Validation failed')
        ->assertJsonStructure(['errors' => ['body']]);
});

it('still requires an Idempotency-Key on the create even when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/notes', ['body' => 'x'])
        ->assertStatus(400)
        ->assertJsonPath('title', 'Idempotency-Key required');
});

it('lets the author read their own note', function () {
    $note = Note::factory()->create();

    $this->actingAs($note->author)->getJson("/api/v1/notes/{$note->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $note->id);
});

it('forbids reading another users note with problem+json 403', function () {
    $note = Note::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->getJson("/api/v1/notes/{$note->id}")
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Forbidden');
});
