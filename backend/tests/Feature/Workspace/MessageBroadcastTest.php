<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Domain\Workspace\MessageKind;
use App\Domain\Workspace\Narrator;
use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use App\Support\OutboxRelay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P4-04 acceptance: workspace messages are broadcast live to the engagement's participants — but
 * only ever off the COMMITTED outbox, never inline from the Action that wrote them. That ordering is
 * the whole point: the Narrator writes inside the transition's transaction (rule #11), so an inline
 * broadcast would announce messages a rollback then erased.
 *
 * @return array{customer: User, provider: User, job: Job, engagementId: string}
 */
function broadcastableEngagement(): array
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

    return ['customer' => $customer, 'provider' => $provider, 'job' => $job, 'engagementId' => $engagement->id];
}

it('does not broadcast when the message is written, only when the outbox relays it', function () {
    ['customer' => $customer, 'job' => $job, 'engagementId' => $engagementId] = broadcastableEngagement();

    Event::fake([MessagePosted::class]);

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/jobs/{$job->id}/messages", ['body' => 'Bonjour !'], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    // Written and committed — but nothing announced yet. Fan-out is the relay's job.
    Event::assertNotDispatched(MessagePosted::class);

    app(OutboxRelay::class)->drain();

    Event::assertDispatched(
        MessagePosted::class,
        fn (MessagePosted $e): bool => $e->engagementId === $engagementId
            && $e->message->body === 'Bonjour !',
    );
});

it('broadcasts on the engagement’s private channel with the message payload', function () {
    ['customer' => $customer, 'job' => $job, 'engagementId' => $engagementId] = broadcastableEngagement();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/jobs/{$job->id}/messages", ['body' => 'On se voit demain ?'], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $captured = null;
    Event::listen(MessagePosted::class, function (MessagePosted $e) use (&$captured): void {
        $captured = $e;
    });
    app(OutboxRelay::class)->drain();

    expect($captured)->not->toBeNull();
    /** @var MessagePosted $captured */
    expect($captured->broadcastOn()[0]->name)->toBe('private-engagement.'.$engagementId)
        ->and($captured->broadcastAs())->toBe('message.posted');

    $payload = $captured->broadcastWith();
    expect($payload['body'])->toBe('On se voit demain ?')
        ->and($payload['kind'])->toBe('text')
        ->and($payload['sender_user_id'])->toBe($customer->id)
        ->and($payload)->toHaveKey('created_at');
});

it('announces nothing for a message a rolled-back transaction erased', function () {
    ['customer' => $customer, 'job' => $job] = broadcastableEngagement();
    $conversation = Conversation::query()->where('job_id', $job->id)->firstOrFail();

    // Forming the engagement narrates its own messages; drain those first so the only thing this
    // test can observe is the narration we are about to roll back.
    app(OutboxRelay::class)->drain();

    Event::fake([MessagePosted::class]);

    // A transition that narrates and then fails leaves neither a message nor an outbox row.
    try {
        DB::transaction(function () use ($conversation): void {
            app(Narrator::class)->narrate(
                $conversation,
                MessageKind::Arrived,
            );
            throw new RuntimeException('transition failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    app(OutboxRelay::class)->drain();

    Event::assertNotDispatched(MessagePosted::class);
});

it('hands the client the engagement id it needs to subscribe', function () {
    ['customer' => $customer, 'job' => $job, 'engagementId' => $engagementId] = broadcastableEngagement();

    Sanctum::actingAs($customer);
    $this->getJson("/api/v1/jobs/{$job->id}/messages")
        ->assertOk()
        ->assertJsonPath('meta.engagement_id', $engagementId);
});
