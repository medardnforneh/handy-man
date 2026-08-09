<?php

declare(strict_types=1);

use App\Models\Engagement;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P7-08 acceptance (doc 07): the provider CRM. Customer book, manual re-engagement on the same
 * budget/consent gates, and do-not-contact honoured absolutely.
 */

/**
 * @return array{provider: User, customer: User}
 */
function providerAndCustomer(): array
{
    $provider = User::factory()->create();
    $customer = User::factory()->create();
    $job = Job::factory()->create(['customer_party_id' => $customer->party_id, 'created_by_user_id' => $customer->id]);
    Engagement::factory()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'agreed_amount_minor' => 500_000, 'completed_at' => now(),
    ]);

    return compact('provider', 'customer');
}

it('lists the provider\'s customers with history and lifetime value', function () {
    ['provider' => $provider, 'customer' => $customer] = providerAndCustomer();

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/provider/customers')
        ->assertOk()
        ->assertJsonPath('data.0.customer_party_id', $customer->party_id)
        ->assertJsonPath('data.0.job_count', 1)
        ->assertJsonPath('data.0.completed_count', 1)
        ->assertJsonPath('data.0.lifetime_value_minor', 500_000)
        ->assertJsonPath('data.0.do_not_contact', false);
});

it('returns last_engaged_at as ISO-8601, not Postgres\'s own timestamp format', function () {
    // The list is an aggregate over a raw query, so no cast reaches it and Postgres would otherwise
    // hand back "2026-07-28 05:15:57+00". V8 parses that; iOS JSC and older Android WebViews return
    // Invalid Date — which is exactly the client this product ships to.
    ['provider' => $provider] = providerAndCustomer();

    Sanctum::actingAs($provider);
    $value = $this->getJson('/api/v1/provider/customers')->assertOk()->json('data.0.last_engaged_at');

    expect($value)->toBeString()
        ->and($value)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('schedules a manual re-engagement follow-up attributed to the provider', function () {
    ['provider' => $provider, 'customer' => $customer] = providerAndCustomer();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/provider/customers/{$customer->party_id}/follow-up", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'reengagement');

    $followUp = FollowUp::query()->firstOrFail();
    expect($followUp->created_by_user_id)->toBe($provider->id)
        ->and($followUp->target_user_id)->toBe($customer->id);
});

it('refuses a manual follow-up to a do-not-contact customer (P7-08)', function () {
    ['provider' => $provider, 'customer' => $customer] = providerAndCustomer();

    Sanctum::actingAs($provider);
    $this->postJson("/api/v1/provider/customers/{$customer->party_id}/do-not-contact", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $this->postJson("/api/v1/provider/customers/{$customer->party_id}/follow-up", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);

    expect(FollowUp::query()->count())->toBe(0);
    $this->getJson('/api/v1/provider/customers')->assertJsonPath('data.0.do_not_contact', true);
});
