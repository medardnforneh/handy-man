<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\EngagementShare;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-05 acceptance (doc 04): a signed, expiring, revocable share-my-job link showing the provider's
 * first name, approximate location and live status. Only for on-site work.
 */

/**
 * @param  'onsite'|'remote'  $mode
 * @return array{customer: User, provider: User, engagement: Engagement}
 */
function shareEngagement(string $mode = 'onsite'): array
{
    $customer = User::factory()->create();
    $factory = Job::factory()->status(JobStatus::Engaged);
    if ($mode === 'remote') {
        $factory = $factory->remote();
    }
    $job = $factory->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    $provider = User::factory()->create();
    $engagement = Engagement::factory()->create(['job_id' => $job->id, 'provider_party_id' => $provider->party_id]);
    Assignment::factory()->create([
        'engagement_id' => $engagement->id, 'worker_user_id' => $provider->id,
        'assigned_by_user_id' => $provider->id, 'role' => 'lead',
    ]);

    return compact('customer', 'provider', 'engagement');
}

it('lets a participant mint a share link that renders the live status', function () {
    ['customer' => $customer, 'engagement' => $engagement] = shareEngagement();
    Sanctum::actingAs($customer);

    $url = $this->postJson("/api/v1/engagements/{$engagement->id}/share", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->json('data.url');

    // The public page renders read-only, no auth needed.
    $this->get($url)->assertOk()->assertSee(__('share.heading'));
});

it('404s an expired or revoked link', function () {
    ['engagement' => $engagement, 'customer' => $customer] = shareEngagement();

    $expired = EngagementShare::factory()->expired()->create([
        'engagement_id' => $engagement->id, 'created_by_user_id' => $customer->id, 'token_hash' => hash('sha256', 'rawA'),
    ]);
    $revoked = EngagementShare::factory()->revoked()->create([
        'engagement_id' => $engagement->id, 'created_by_user_id' => $customer->id, 'token_hash' => hash('sha256', 'rawB'),
    ]);

    $this->get(route('engagement.share', ['token' => 'rawA']))->assertNotFound();
    $this->get(route('engagement.share', ['token' => 'rawB']))->assertNotFound();
    expect($expired->isActive())->toBeFalse()->and($revoked->isActive())->toBeFalse();
});

it('refuses share-my-job on a remote engagement (422)', function () {
    ['customer' => $customer, 'engagement' => $engagement] = shareEngagement('remote');
    Sanctum::actingAs($customer);

    $this->postJson("/api/v1/engagements/{$engagement->id}/share", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);
});

it('forbids a non-participant from sharing', function () {
    ['engagement' => $engagement] = shareEngagement();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/engagements/{$engagement->id}/share", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('revokes a share link, then the page 404s', function () {
    ['customer' => $customer, 'engagement' => $engagement] = shareEngagement();
    Sanctum::actingAs($customer);

    $created = $this->postJson("/api/v1/engagements/{$engagement->id}/share", [], ['Idempotency-Key' => (string) Str::uuid()])->json('data');
    $this->get($created['url'])->assertOk();

    $this->deleteJson("/api/v1/engagement-shares/{$created['id']}", [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
    $this->get($created['url'])->assertNotFound();
});
