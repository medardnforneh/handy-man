<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The messages tab: an index over the conversations a user participates in.
 *
 * The property that matters is that membership — and nothing else — decides what a user can see
 * here, matching the gate on the thread itself. The rest is about telling the truth on a list row:
 * an unread count that can go down, and a preview that never hard-codes one language.
 */

/**
 * @return array{customer: User, provider: User, job: Job, conversation: Conversation}
 */
function engagedPair(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 500_000, 'deposit_minor' => 0, 'valid_until' => now()->addDays(3),
    ]);
    app(AcceptQuotation::class)->handle($customer, $quote);

    return [
        'customer' => $customer, 'provider' => $provider, 'job' => $job,
        'conversation' => Conversation::query()->where('job_id', $job->id)->firstOrFail(),
    ];
}

function listConversations(User $user)
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/v1/conversations');
}

it('lists a conversation the user participates in, keyed by the job the workspace routes on', function () {
    ['customer' => $customer, 'job' => $job, 'conversation' => $conversation] = engagedPair();

    listConversations($customer)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.job_id', $job->id)
        ->assertJsonPath('data.0.reference', $job->reference);
});

it('never shows a conversation to someone who is not a participant', function () {
    engagedPair();
    $stranger = User::factory()->create();

    listConversations($stranger)->assertOk()->assertJsonCount(0, 'data');
});

it('names the other side from the asking user point of view', function () {
    ['customer' => $customer, 'provider' => $provider] = engagedPair();

    $customerView = listConversations($customer)->json('data.0.counterpart_name');
    $providerView = listConversations($provider)->json('data.0.counterpart_name');

    // Each sees the OTHER party, never themselves — the same row means two different things.
    expect($customerView)->toBe($provider->party->display_name)
        ->and($providerView)->toBe($customer->party->display_name);
});

it('counts unread messages from the other side and clears them when the thread is read', function () {
    ['customer' => $customer, 'provider' => $provider, 'job' => $job, 'conversation' => $conversation] = engagedPair();

    Sanctum::actingAs($provider);
    foreach (['first', 'second'] as $body) {
        test()->postJson("/api/v1/jobs/{$job->id}/messages", ['body' => $body], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated();
    }

    // Exactly the two the provider sent. The engagement's own `quote_accepted` narration is
    // attributed to the customer who accepted, so it is not unread FOR THEM — which is the point of
    // counting by sender rather than by "messages since I last looked".
    $before = listConversations($customer)->json('data.0.unread_count');
    expect($before)->toBe(2);

    Sanctum::actingAs($customer);
    test()->postJson("/api/v1/conversations/{$conversation->id}/read", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk();

    expect(listConversations($customer)->json('data.0.unread_count'))->toBe(0);
});

it('does not count the user own messages as unread', function () {
    ['customer' => $customer, 'job' => $job, 'conversation' => $conversation] = engagedPair();

    Sanctum::actingAs($customer);
    test()->postJson("/api/v1/conversations/{$conversation->id}/read", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->postJson("/api/v1/jobs/{$job->id}/messages", ['body' => 'mine'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    expect(listConversations($customer)->json('data.0.unread_count'))->toBe(0);
});

it('previews free-form text but sends only the kind for server-narrated messages', function () {
    ['customer' => $customer, 'provider' => $provider, 'job' => $job] = engagedPair();

    // The newest message is the engagement's own `quote_accepted` narration.
    $narrated = listConversations($customer)->json('data.0.last_message');
    expect($narrated['kind'])->toBe('quote_accepted')
        // Prose here would hard-code one language into the API; the client renders from `kind`.
        ->and($narrated['preview'])->toBeNull();

    Sanctum::actingAs($provider);
    test()->postJson("/api/v1/jobs/{$job->id}/messages", ['body' => 'Je passe demain'], ['Idempotency-Key' => (string) Str::uuid()]);

    $text = listConversations($customer)->json('data.0.last_message');
    expect($text['kind'])->toBe('text')
        ->and($text['preview'])->toBe('Je passe demain')
        ->and($text['mine'])->toBeFalse();
});

it('refuses to mark a conversation read for a non-participant', function () {
    ['conversation' => $conversation] = engagedPair();
    $stranger = User::factory()->create();

    Sanctum::actingAs($stranger);
    test()->postJson("/api/v1/conversations/{$conversation->id}/read", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('never moves the read marker backwards', function () {
    ['customer' => $customer, 'conversation' => $conversation] = engagedPair();

    Sanctum::actingAs($customer);
    $first = test()->postJson("/api/v1/conversations/{$conversation->id}/read", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->json('data.last_read_at');

    // A second device reporting a stale read must not resurrect messages already seen.
    ConversationParticipant::query()
        ->where('conversation_id', $conversation->id)
        ->where('user_id', $customer->id)
        ->update(['last_read_at' => now()->addHour()]);

    $second = test()->postJson("/api/v1/conversations/{$conversation->id}/read", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->json('data.last_read_at');

    expect($second)->toBeGreaterThan($first);
});
