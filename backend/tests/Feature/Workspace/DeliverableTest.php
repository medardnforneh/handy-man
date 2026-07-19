<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Domain\Workspace\DeliverableStatus;
use App\Models\Deliverable;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Message;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P4-08 acceptance (doc 06): deliverables submit/accept/reject (remote path). Submission is narrated
 * into the workspace thread by the server; the provider submits, the customer reviews.
 */

/**
 * @return array{customer: User, provider: User, engagement: Engagement}
 */
function deliverableEngagement(): array
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

    return ['customer' => $customer, 'provider' => $provider, 'engagement' => app(AcceptQuotation::class)->handle($customer, $quote)];
}

function submitDeliverable(User $provider, string $engagementId, array $body = ['title' => 'Final design'])
{
    Sanctum::actingAs($provider);

    return test()->postJson("/api/v1/engagements/{$engagementId}/deliverables", $body, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('lets the provider submit a deliverable and narrates it into the thread', function () {
    ['provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();

    submitDeliverable($provider, $engagement->id, ['title' => 'Logo pack', 'media_url' => 'deliverables/logo.zip'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.title', 'Logo pack');

    expect(Message::where('kind', 'deliverable_submitted')->count())->toBe(1);
});

it('forbids a non-provider from submitting', function () {
    ['engagement' => $engagement] = deliverableEngagement();

    submitDeliverable(User::factory()->create(), $engagement->id)->assertForbidden();
});

it('lets the customer accept a deliverable', function () {
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();
    $id = submitDeliverable($provider, $engagement->id)->json('data.id');

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/deliverables/{$id}/review", ['decision' => 'accept'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(Deliverable::findOrFail($id)->reviewed_at)->not->toBeNull();
});

it('lets the customer reject a deliverable with a reason', function () {
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();
    $id = submitDeliverable($provider, $engagement->id)->json('data.id');

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/deliverables/{$id}/review",
        ['decision' => 'reject', 'reject_reason' => 'Wrong colours'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.reject_reason', 'Wrong colours');
});

it('requires a reason when rejecting (422)', function () {
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();
    $id = submitDeliverable($provider, $engagement->id)->json('data.id');

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/deliverables/{$id}/review", ['decision' => 'reject'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('forbids a non-customer from reviewing', function () {
    ['provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();
    $id = submitDeliverable($provider, $engagement->id)->json('data.id');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/deliverables/{$id}/review", ['decision' => 'accept'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('rejects reviewing an already-reviewed deliverable (409)', function () {
    ['customer' => $customer, 'provider' => $provider, 'engagement' => $engagement] = deliverableEngagement();
    $id = submitDeliverable($provider, $engagement->id)->json('data.id');

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/deliverables/{$id}/review", ['decision' => 'accept'], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->postJson("/api/v1/deliverables/{$id}/review", ['decision' => 'accept'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);

    expect(Deliverable::findOrFail($id)->status)->toBe(DeliverableStatus::Accepted);
});
