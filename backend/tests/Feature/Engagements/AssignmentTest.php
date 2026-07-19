<?php

declare(strict_types=1);

use App\Domain\Engagements\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P2-08 acceptance (doc 02/10): a company engagement is staffed by a dispatcher of THAT org, and a
 * dispatcher of org A can never assign a worker of org B. Authority is org-internal RBAC
 * (EngagementPolicy); the worker↔provider boundary is enforced both in the Action and by the DB
 * trigger `assignments_worker_boundary_check`.
 */

/**
 * A company + its engagement, with a dispatcher and a worker member.
 *
 * @return array{org: Organization, dispatcher: User, worker: User, engagement: Engagement}
 */
function companyEngagement(): array
{
    $org = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    $dispatcher = User::factory()->create();
    Membership::factory()->create(['user_id' => $dispatcher->id, 'organization_id' => $org->id, 'role' => 'dispatcher']);

    $worker = User::factory()->create();
    Membership::factory()->create(['user_id' => $worker->id, 'organization_id' => $org->id, 'role' => 'worker']);

    return ['org' => $org, 'dispatcher' => $dispatcher, 'worker' => $worker, 'engagement' => $engagement];
}

function assignPayload(User $worker, ?string $role = null): array
{
    return array_filter(['worker_user_id' => $worker->id, 'role' => $role]);
}

it('lets a dispatcher assign a same-org worker to the engagement', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'engagement' => $engagement] = companyEngagement();

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker, 'helper'),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.worker_user_id', $worker->id)
        ->assertJsonPath('data.role', 'helper')
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assigned_by_user_id', $dispatcher->id);

    expect(Assignment::where('engagement_id', $engagement->id)->count())->toBe(1)
        ->and(OutboxMessage::where('type', 'assignment.created')->count())->toBe(1);
});

it('rejects a dispatcher of org A assigning a worker of org B (422, the P2-08 boundary)', function () {
    ['dispatcher' => $dispatcherA, 'engagement' => $engagementA] = companyEngagement();

    // A worker who belongs to a DIFFERENT org.
    $orgB = Organization::factory()->create();
    $foreignWorker = User::factory()->create();
    Membership::factory()->create(['user_id' => $foreignWorker->id, 'organization_id' => $orgB->id, 'role' => 'worker']);

    Sanctum::actingAs($dispatcherA);
    $this->postJson("/api/v1/engagements/{$engagementA->id}/assignments", assignPayload($foreignWorker),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('title', 'Worker does not belong to the provider');

    expect(Assignment::where('engagement_id', $engagementA->id)->count())->toBe(0);
});

it('forbids a non-dispatcher member from assigning (403, org-internal RBAC)', function () {
    ['worker' => $worker, 'engagement' => $engagement] = companyEngagement();

    // The worker-role member tries to assign themselves — no dispatch authority.
    Sanctum::actingAs($worker);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('forbids a dispatcher of another org from touching this engagement (403)', function () {
    ['worker' => $worker, 'engagement' => $engagement] = companyEngagement();

    $orgB = Organization::factory()->create();
    $dispatcherB = User::factory()->create();
    Membership::factory()->create(['user_id' => $dispatcherB->id, 'organization_id' => $orgB->id, 'role' => 'dispatcher']);

    Sanctum::actingAs($dispatcherB);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden();
});

it('rejects a second lead on the engagement (409)', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org, 'engagement' => $engagement] = companyEngagement();

    // First lead.
    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker, 'lead'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    // A second worker, assigned as lead → conflict.
    $worker2 = User::factory()->create();
    Membership::factory()->create(['user_id' => $worker2->id, 'organization_id' => $org->id, 'role' => 'worker']);

    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker2, 'lead'),
        ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('title', 'Assignment conflicts with an existing one');
});

it('rejects assigning the same worker twice (409)', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'engagement' => $engagement] = companyEngagement();

    Sanctum::actingAs($dispatcher);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker, 'helper'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker, 'helper'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
});

it('removing the lead frees the slot for a new lead', function () {
    ['dispatcher' => $dispatcher, 'worker' => $worker, 'org' => $org, 'engagement' => $engagement] = companyEngagement();

    Sanctum::actingAs($dispatcher);
    $created = $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker, 'lead'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $leadId = $created->json('data.id');

    // Remove the lead.
    $this->deleteJson("/api/v1/engagements/{$engagement->id}/assignments/{$leadId}",
        [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    $removed = Assignment::findOrFail($leadId);
    expect($removed->status)->toBe(AssignmentStatus::Removed)
        ->and($removed->removed_at)->not->toBeNull();

    // A new lead can now be assigned.
    $worker2 = User::factory()->create();
    Membership::factory()->create(['user_id' => $worker2->id, 'organization_id' => $org->id, 'role' => 'worker']);
    $this->postJson("/api/v1/engagements/{$engagement->id}/assignments", assignPayload($worker2, 'lead'),
        ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

it('DB trigger blocks a cross-org worker even if the app layer were bypassed', function () {
    $org = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['provider_party_id' => $org->party_id]);

    // A worker with NO membership in that org — a raw insert must be rejected by the trigger.
    $stranger = User::factory()->create();

    expect(fn () => Assignment::factory()->create([
        'engagement_id' => $engagement->id,
        'worker_user_id' => $stranger->id,
        'assigned_by_user_id' => $stranger->id,
        'role' => 'helper',
    ]))->toThrow(QueryException::class);
});
