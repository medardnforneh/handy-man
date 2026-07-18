<?php

declare(strict_types=1);

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * P1-01 acceptance (doc 02): the parties supertype with users/organizations subtypes, UUID PKs,
 * and the kind-enforcing constraint trigger — you cannot attach a user to an organization-kind
 * party, nor an organization to an individual-kind party.
 */
it('creates an individual user backed by an individual party', function () {
    $user = User::factory()->create();

    expect($user->getKey())->toBeString()                      // uuid
        ->and($user->party)->not->toBeNull()
        ->and($user->party->kind)->toBe(Party::KIND_INDIVIDUAL)
        ->and($user->party->isIndividual())->toBeTrue();
});

it('creates a company backed by an organization party', function () {
    $org = Organization::factory()->create();

    expect($org->party->kind)->toBe(Party::KIND_ORGANIZATION)
        ->and($org->party->isOrganization())->toBeTrue();
});

it('REJECTS attaching a user to an organization-kind party at the DB level', function () {
    $orgParty = Party::factory()->organization()->create();

    expect(fn () => User::factory()->create(['party_id' => $orgParty->id]))
        ->toThrow(QueryException::class);
});

it('REJECTS attaching an organization to an individual-kind party at the DB level', function () {
    $individualParty = Party::factory()->individual()->create();

    expect(fn () => Organization::factory()->create(['party_id' => $individualParty->id]))
        ->toThrow(QueryException::class);
});

it('links a user to an organization through a membership with an org role', function () {
    $membership = Membership::factory()->create(['role' => 'dispatcher']);

    expect($membership->user)->toBeInstanceOf(User::class)
        ->and($membership->organization)->toBeInstanceOf(Organization::class)
        ->and($membership->user->organizations)->toHaveCount(1)
        ->and($membership->organization->members->first()->getKey())->toBe($membership->user->getKey());
});

it('enforces one membership per user per organization', function () {
    $membership = Membership::factory()->create();

    expect(fn () => Membership::factory()->create([
        'user_id' => $membership->user_id,
        'organization_id' => $membership->organization_id,
    ]))->toThrow(QueryException::class);
});

it('stores phone as the unique primary identifier and defaults locale to fr', function () {
    $user = User::factory()->create(['phone_e164' => '+237699112233']);

    expect($user->phone_e164)->toBe('+237699112233')
        ->and($user->locale)->toBe('fr');

    expect(fn () => User::factory()->create(['phone_e164' => '+237699112233']))
        ->toThrow(QueryException::class); // unique phone
});
