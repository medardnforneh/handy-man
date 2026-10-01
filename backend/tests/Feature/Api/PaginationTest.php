<?php

declare(strict_types=1);

use App\Domain\Quotations\SiteVisitStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Job;
use App\Models\Message;
use App\Models\Payout;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Cursor;
use Laravel\Sanctum\Sanctum;

/**
 * Cursor pagination on the three lists that had none.
 *
 * Doc 05 requires "cursor, not offset" and the API had neither. `GET /jobs` returned every job a
 * customer had ever created with four relations eager-loaded per row; `GET /jobs/{job}/messages`
 * returned a whole thread with its media, in no defined order, on every open; `GET /conversations`
 * returned every thread the user had ever been in — and read every message of all of them to
 * build the previews.
 *
 * Both parameters are additive (rule #4), so the no-parameter call is tested as carefully as the
 * paginated one: an old build must keep reading `data` exactly where it always did.
 */
it('pages the customer jobs list, newest first, without repeating a row', function () {
    $user = User::factory()->create();
    foreach (range(1, 7) as $i) {
        Job::factory()->create([
            'customer_party_id' => $user->party_id,
            'created_at' => now()->subMinutes(10 - $i), // 1 oldest … 7 newest
        ]);
    }

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/jobs?limit=3')->assertOk();
    $first->assertJsonCount(3, 'data')->assertJsonPath('meta.has_more', true);

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->toBeString();

    $second = $this->getJson('/api/v1/jobs?limit=3&before='.urlencode($cursor))->assertOk();
    $second->assertJsonCount(3, 'data')->assertJsonPath('meta.has_more', true);

    $last = $this->getJson('/api/v1/jobs?limit=3&before='.urlencode($second->json('meta.next_cursor')))->assertOk();
    $last->assertJsonCount(1, 'data')->assertJsonPath('meta.has_more', false);
    expect($last->json('meta.next_cursor'))->toBeNull();

    // Seven distinct jobs across three pages: a keyset cursor names a position, so nothing is
    // shown twice and nothing is skipped — which is the whole reason doc 05 forbids offsets.
    $ids = array_merge($first->json('data.*.id'), $second->json('data.*.id'), $last->json('data.*.id'));
    expect($ids)->toHaveCount(7)
        ->and(array_unique($ids))->toHaveCount(7);
});

it('still answers a request that asks for no page at all', function () {
    $user = User::factory()->create();
    Job::factory()->count(3)->create(['customer_party_id' => $user->party_id]);

    Sanctum::actingAs($user);

    // What every shipped build sends. `data` in the same place, same shape.
    $this->getJson('/api/v1/jobs')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonStructure(['data' => [['id', 'status']]]);
});

it('treats a nonsense limit as the default rather than an error', function () {
    // Additive-only cuts both ways: a newer client must not be able to break itself, and a bad
    // value must not turn a working screen into a 422 mid-scroll.
    $user = User::factory()->create();
    Job::factory()->count(2)->create(['customer_party_id' => $user->party_id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/jobs?limit=banana')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/jobs?limit=-5')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/jobs?before=not-a-real-cursor')->assertOk()->assertJsonCount(2, 'data');
});

it('clamps a greedy limit to the maximum', function () {
    $user = User::factory()->create();
    Job::factory()->count(3)->create(['customer_party_id' => $user->party_id]);

    Sanctum::actingAs($user);

    // The point is that the server decides, not the caller.
    $this->getJson('/api/v1/jobs?limit=100000')->assertOk()->assertJsonCount(3, 'data');
    expect(Cursor::limit(100000, 25))->toBe(Cursor::MAX_LIMIT);
});

it('returns the newest page of a thread, oldest message first, and pages into the past', function () {
    // The order matters more than the count: the app appends into this array and renders it, so a
    // page that arrived newest-first would render the conversation backwards. There was no ORDER BY
    // on this query at all before.
    [$user, $job, $conversation] = threadFixture();

    foreach (range(1, 5) as $i) {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $user->getKey(),
            'body' => "message {$i}",
            'created_at' => now()->subMinutes(10 - $i), // 1 oldest … 5 newest
        ]);
    }

    Sanctum::actingAs($user);

    $newest = $this->getJson("/api/v1/jobs/{$job->id}/messages?limit=2")->assertOk();

    // The NEWEST two, in ascending order.
    expect($newest->json('data.*.body'))->toBe(['message 4', 'message 5'])
        ->and($newest->json('meta.has_older'))->toBeTrue()
        ->and($newest->json('meta.conversation_id'))->toBe($conversation->id);

    $older = $this->getJson("/api/v1/jobs/{$job->id}/messages?limit=2&before=".urlencode($newest->json('meta.older_cursor')))
        ->assertOk();

    expect($older->json('data.*.body'))->toBe(['message 2', 'message 3'])
        ->and($older->json('meta.has_older'))->toBeTrue();

    $oldest = $this->getJson("/api/v1/jobs/{$job->id}/messages?limit=2&before=".urlencode($older->json('meta.older_cursor')))
        ->assertOk();

    expect($oldest->json('data.*.body'))->toBe(['message 1'])
        ->and($oldest->json('meta.has_older'))->toBeFalse()
        ->and($oldest->json('meta.older_cursor'))->toBeNull();
});

it('pages the conversations index and previews each thread without reading its history', function () {
    $user = User::factory()->create();

    foreach (range(1, 4) as $i) {
        $job = Job::factory()->create();
        $conversation = Conversation::factory()->create(['job_id' => $job->id]);
        ConversationParticipant::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->getKey(),
            'party_id' => $user->party_id,
        ]);

        // Several messages each: the preview must be the NEWEST one, and getting there must not
        // mean loading the rest.
        foreach (range(1, 3) as $n) {
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'body' => "thread {$i} message {$n}",
                'created_at' => now()->subMinutes((10 - $i) * 10 + (3 - $n)),
            ]);
        }
    }

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/conversations?limit=2')->assertOk();
    $first->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true);

    $second = $this->getJson('/api/v1/conversations?limit=2&before='.urlencode($first->json('meta.next_cursor')))
        ->assertOk();
    $second->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', false);

    // Four distinct conversations, most recently active first, none repeated.
    $ids = array_merge($first->json('data.*.id'), $second->json('data.*.id'));
    expect(array_unique($ids))->toHaveCount(4);
});

/**
 * A job with a conversation the user participates in.
 *
 * @return array{0: User, 1: Job, 2: Conversation}
 */
function threadFixture(): array
{
    $user = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $user->party_id]);
    $conversation = Conversation::factory()->create(['job_id' => $job->id]);
    ConversationParticipant::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->getKey(),
        'party_id' => $user->party_id,
    ]);

    return [$user, $job, $conversation];
}

it('pages site visits across the scheduled/completed boundary without skipping one', function () {
    // The reason this one carries the bucket in its cursor. The list is scheduled-before-completed,
    // then by date, so a two-part cursor would compare something the ordering does not — and the
    // page boundary between the last scheduled visit and the first completed one is exactly where
    // it would land wrong, silently dropping a row.
    $provider = User::factory()->create();

    foreach (range(1, 2) as $i) {
        SiteVisit::factory()->create([
            'provider_party_id' => $provider->party_id,
            'status' => SiteVisitStatus::Scheduled->value,
            'scheduled_for' => now()->addDays($i),
        ]);
    }
    foreach (range(1, 2) as $i) {
        SiteVisit::factory()->create([
            'provider_party_id' => $provider->party_id,
            'status' => SiteVisitStatus::Completed->value,
            'scheduled_for' => now()->subDays($i),
            'completed_at' => now()->subDays($i),
        ]);
    }

    Sanctum::actingAs($provider);

    // A page size of 3 puts the boundary INSIDE a page break: two scheduled, then one completed.
    $first = $this->getJson('/api/v1/provider/site-visits?limit=3')->assertOk();
    $first->assertJsonCount(3, 'data')->assertJsonPath('meta.has_more', true);

    $second = $this->getJson('/api/v1/provider/site-visits?limit=3&before='.urlencode($first->json('meta.next_cursor')))
        ->assertOk();
    $second->assertJsonCount(1, 'data')->assertJsonPath('meta.has_more', false);

    // All four, each once. Scheduled first, and the completed ones after them.
    $ids = array_merge($first->json('data.*.id'), $second->json('data.*.id'));
    expect(array_unique($ids))->toHaveCount(4);

    $statuses = array_merge($first->json('data.*.status'), $second->json('data.*.status'));
    expect($statuses)->toBe(['scheduled', 'scheduled', 'completed', 'completed']);
});

it('pages the payout history from the cursor the summary hands over', function () {
    // The summary embeds the most recent page and mints the cursor for the rest — the client must
    // never build one, because the encoding is the server's to change.
    $provider = User::factory()->create();

    foreach (range(1, 4) as $i) {
        Payout::factory()->create([
            'party_id' => $provider->party_id,
            'requested_at' => now()->subDays($i),
        ]);
    }

    Sanctum::actingAs($provider);

    $page = $this->getJson('/api/v1/provider/payouts?limit=3')->assertOk();
    $page->assertJsonCount(3, 'data')->assertJsonPath('meta.has_more', true);

    $rest = $this->getJson('/api/v1/provider/payouts?limit=3&before='.urlencode($page->json('meta.next_cursor')))
        ->assertOk();
    $rest->assertJsonCount(1, 'data')->assertJsonPath('meta.has_more', false);

    $ids = array_merge($page->json('data.*.id'), $rest->json('data.*.id'));
    expect(array_unique($ids))->toHaveCount(4);
});

it('tells the earnings screen where its embedded history stops', function () {
    $provider = User::factory()->create();
    Payout::factory()->count(2)->create(['party_id' => $provider->party_id]);

    Sanctum::actingAs($provider);

    // Two payouts is well inside the embedded page, so there is nothing behind it and the screen's
    // "show older" affordance must be absent rather than present and inert.
    $this->getJson('/api/v1/provider/earnings')
        ->assertOk()
        ->assertJsonCount(2, 'data.payouts')
        ->assertJsonPath('data.payouts_next_cursor', null);
});
