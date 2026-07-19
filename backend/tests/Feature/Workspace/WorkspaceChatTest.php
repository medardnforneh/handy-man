<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Domain\Quotations\QuoteNotAcceptable;
use App\Domain\Workspace\MessageKind;
use App\Domain\Workspace\Narrator;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\Message;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P4-01/02 acceptance (doc 06, rule #11): the chat is the state machine. Structured messages are
 * narrated by the server inside the transition's transaction (a rollback narrates nothing); a client
 * may only post free-form text, and posting a structured kind is rejected. Only participants read/post.
 */

/**
 * @return array{customer: User, provider: User, job: Job, quote: Quotation, conversation: Conversation}
 */
function engagedConversation(): array
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
    $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

    return [
        'customer' => $customer, 'provider' => $provider, 'job' => $job, 'quote' => $quote,
        'conversation' => Conversation::query()->where('job_id', $job->id)->firstOrFail(),
    ];
}

function messagePost(User $user, Job $job, array $body)
{
    Sanctum::actingAs($user);

    return test()->postJson("/api/v1/jobs/{$job->id}/messages", $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('narrates quote_accepted into the thread when a quote is accepted', function () {
    ['conversation' => $conversation] = engagedConversation();

    expect(Message::query()->where('conversation_id', $conversation->id)->where('kind', 'quote_accepted')->count())->toBe(1);
});

it('lets a participant post a free-form text message', function () {
    ['customer' => $customer, 'job' => $job] = engagedConversation();

    messagePost($customer, $job, ['body' => 'Bonjour, on commence quand ?'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'text')
        ->assertJsonPath('data.sender_user_id', $customer->id);
});

it('rejects a client posting a structured message kind (P4-02)', function () {
    ['customer' => $customer, 'job' => $job] = engagedConversation();

    messagePost($customer, $job, ['body' => 'fake', 'kind' => 'quote_accepted'])
        ->assertStatus(422);
});

it('forbids a non-participant from posting or reading', function () {
    ['job' => $job] = engagedConversation();
    $stranger = User::factory()->create();

    messagePost($stranger, $job, ['body' => 'let me in'])->assertForbidden();

    Sanctum::actingAs($stranger);
    $this->getJson("/api/v1/jobs/{$job->id}/messages")->assertForbidden();
});

it('narrates nothing when the transition transaction rolls back', function () {
    $conversation = Conversation::factory()->create();

    try {
        DB::transaction(function () use ($conversation): void {
            app(Narrator::class)->narrate($conversation, MessageKind::Started);
            throw new RuntimeException('transition failed after narrating');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Message::query()->where('kind', 'started')->count())->toBe(0);
});

it('adds nothing to the thread when an accept loses the race (rolled-back narration)', function () {
    ['customer' => $customer, 'quote' => $quote] = engagedConversation();

    // The quote is already accepted; a second accept must not narrate again.
    try {
        app(AcceptQuotation::class)->handle($customer, $quote->fresh());
    } catch (QuoteNotAcceptable) {
        // expected
    }

    expect(Message::query()->where('kind', 'quote_accepted')->count())->toBe(1);
});

it('flags detected contact details but does not block the message (P4-09)', function () {
    ['customer' => $customer, 'job' => $job] = engagedConversation();

    messagePost($customer, $job, ['body' => 'Call me on +237 650 00 00 00'])
        ->assertCreated()
        ->assertJsonPath('data.contact_flag', 'phone');
});

it('lists the thread for a participant in order', function () {
    ['customer' => $customer, 'job' => $job] = engagedConversation();
    messagePost($customer, $job, ['body' => 'Hello'])->assertCreated();

    Sanctum::actingAs($customer);
    $this->getJson("/api/v1/jobs/{$job->id}/messages")
        ->assertOk()
        ->assertJsonCount(2, 'data') // narrated quote_accepted + the posted text
        ->assertJsonPath('data.0.kind', 'quote_accepted')
        ->assertJsonPath('data.1.body', 'Hello');
});
