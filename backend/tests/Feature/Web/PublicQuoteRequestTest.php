<?php

declare(strict_types=1);

use App\Domain\Identity\Otp\OtpSender;
use App\Domain\Jobs\JobStatus;
use App\Models\Address;
use App\Models\Consent;
use App\Models\Job;
use App\Models\OutboxMessage;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\SkillsSeeder;
use Tests\Support\FakeOtpSender;

/**
 * Launch checklist (doc 05): "A customer can find a provider and request a quote without loading
 * the app bundle (Blade)." Three server-rendered steps, no JavaScript, on the same domain actions
 * the app calls — so what lands in `jobs` is a real, open, quote-only job.
 */
beforeEach(function () {
    $this->fakeOtp = new FakeOtpSender;
    $this->app->instance(OtpSender::class, $this->fakeOtp);
    $this->seed(SkillsSeeder::class);
    $this->leaf = Skill::query()->where('is_leaf', true)->firstOrFail();
    $this->category = Skill::query()->where('is_leaf', false)->firstOrFail();
});

it('shows the request form on a trade, and not on a category', function () {
    $this->get(route('services.request', ['slug' => $this->leaf->slug]))
        ->assertOk()
        ->assertSee('name="phone_e164"', false)
        ->assertSee('name="engagement_mode"', false)
        ->assertSee('name="city"', false);

    $this->get(route('services.request', ['slug' => $this->category->slug]))->assertNotFound();
});

it('links the trade page\'s call to action to the form on a leaf, and to how-it-works on a category', function () {
    $this->get(route('services.show', ['slug' => $this->leaf->slug]))
        ->assertSee(route('services.request', ['slug' => $this->leaf->slug]), false);
    $this->get(route('services.show', ['slug' => $this->category->slug]))
        ->assertSee(route('home').'#how', false);
});

it('posts a remote request end to end: form, code, an open quote-only job for a brand-new customer', function () {
    $slug = $this->leaf->slug;

    // A number as a person types it, not as E.164.
    $this->post(route('services.request.store', ['slug' => $slug]), [
        'phone_e164' => '6 99 12 34 56',
        'engagement_mode' => 'remote',
        'title' => 'Refaire le logo de ma boutique',
        'description' => 'Un logo simple, deux couleurs.',
        'consent_terms' => '1',
    ])->assertRedirect(route('services.request.verify', ['slug' => $slug]));

    $code = $this->fakeOtp->codeFor('+237699123456');
    expect($code)->toMatch('/^\d{6}$/');

    $this->get(route('services.request.verify', ['slug' => $slug]))
        ->assertOk()
        ->assertSee('+237699123456')
        ->assertDontSee($code); // never on the page outside a developer's own machine

    $this->post(route('services.request.confirm', ['slug' => $slug]), ['code' => $code])
        ->assertRedirect(route('services.request.posted', ['slug' => $slug]));

    $user = User::query()->where('phone_e164', '+237699123456')->firstOrFail();
    $job = Job::query()->where('customer_party_id', $user->party_id)->firstOrFail();
    expect($job->status)->toBe(JobStatus::Open)
        ->and($job->skill_id)->toBe($this->leaf->id)
        ->and($job->engagement_mode->value)->toBe('remote')
        ->and($job->price_model)->toBe('quote_only')
        ->and($job->address_id)->toBeNull()
        ->and($job->title)->toBe('Refaire le logo de ma boutique')
        ->and(OutboxMessage::where('type', 'job.published')->count())->toBe(1)
        ->and(Consent::query()->where('user_id', $user->id)->pluck('purpose')->sort()->values()->all())->toBe(['privacy', 'terms']);

    $this->get(route('services.request.posted', ['slug' => $slug]))
        ->assertOk()
        ->assertSee($job->reference);

    // The confirmation is shown once; a reload goes back to the trade rather than a stale receipt.
    $this->get(route('services.request.posted', ['slug' => $slug]))
        ->assertRedirect(route('services.show', ['slug' => $slug]));
});

it('posts an on-site request with an address at the city\'s centroid and the location consent', function () {
    $slug = $this->leaf->slug;

    $this->post(route('services.request.store', ['slug' => $slug]), [
        'phone_e164' => '+237677000111',
        'engagement_mode' => 'onsite',
        'title' => 'Fuite sous l’évier',
        'city' => 'douala',
        'line1' => 'Rue 1.234, en face de la pharmacie',
        'quarter' => 'Bonapriso',
        'consent_location' => '1',
        'consent_terms' => '1',
    ])->assertRedirect();

    $this->post(route('services.request.confirm', ['slug' => $slug]), ['code' => $this->fakeOtp->codeFor('+237677000111')])
        ->assertRedirect(route('services.request.posted', ['slug' => $slug]));

    $user = User::query()->where('phone_e164', '+237677000111')->firstOrFail();
    $job = Job::query()->where('customer_party_id', $user->party_id)->firstOrFail();
    $address = Address::query()->findOrFail($job->address_id);

    expect($job->engagement_mode->value)->toBe('onsite')
        ->and($address->city)->toBe('Douala')
        ->and($address->quarter)->toBe('Bonapriso')
        ->and($address->line1)->toBe('Rue 1.234, en face de la pharmacie')
        ->and(round($address->point->latitude, 3))->toBe(4.051)
        ->and(round($address->point->longitude, 3))->toBe(9.768)
        ->and(Consent::query()->where('user_id', $user->id)->where('purpose', 'location_tracking')->where('granted', true)->exists())->toBeTrue();
});

it('refuses an on-site request without a place or the location consent', function () {
    $this->from(route('services.request', ['slug' => $this->leaf->slug]))
        ->post(route('services.request.store', ['slug' => $this->leaf->slug]), [
            'phone_e164' => '+237677000222',
            'engagement_mode' => 'onsite',
            'title' => 'Fuite sous l’évier',
            'consent_terms' => '1',
        ])
        ->assertSessionHasErrors(['city', 'line1', 'consent_location']);

    expect($this->fakeOtp->codeFor('+237677000222'))->toBeNull()
        ->and(Job::query()->count())->toBe(0);
});

it('keeps the customer on the code page after a wrong code, and posts nothing', function () {
    $slug = $this->leaf->slug;
    $this->post(route('services.request.store', ['slug' => $slug]), [
        'phone_e164' => '+237677000333', 'engagement_mode' => 'remote', 'title' => 'Un site vitrine', 'consent_terms' => '1',
    ]);

    $this->from(route('services.request.verify', ['slug' => $slug]))
        ->post(route('services.request.confirm', ['slug' => $slug]), ['code' => '000000'])
        ->assertRedirect(route('services.request.verify', ['slug' => $slug]))
        ->assertSessionHasErrors('code');

    expect(Job::query()->count())->toBe(0)
        ->and(User::query()->where('phone_e164', '+237677000333')->exists())->toBeFalse();
});

it('surfaces the OTP rate limit on the form rather than sending a fourth code', function () {
    $slug = $this->leaf->slug;
    $payload = ['phone_e164' => '+237677000444', 'engagement_mode' => 'remote', 'title' => 'Un site vitrine', 'consent_terms' => '1'];

    foreach (range(1, 3) as $_) {
        $this->post(route('services.request.store', ['slug' => $slug]), $payload)->assertRedirect(route('services.request.verify', ['slug' => $slug]));
    }
    $this->from(route('services.request', ['slug' => $slug]))
        ->post(route('services.request.store', ['slug' => $slug]), $payload)
        ->assertRedirect(route('services.request', ['slug' => $slug]))
        ->assertSessionHasErrors('phone_e164');
});

it('does not let a draft for one trade be confirmed on another', function () {
    $other = Skill::query()->where('is_leaf', true)->whereKeyNot($this->leaf->id)->firstOrFail();
    $this->post(route('services.request.store', ['slug' => $this->leaf->slug]), [
        'phone_e164' => '+237677000555', 'engagement_mode' => 'remote', 'title' => 'Un site vitrine', 'consent_terms' => '1',
    ]);

    $this->get(route('services.request.verify', ['slug' => $other->slug]))
        ->assertRedirect(route('services.request', ['slug' => $other->slug]));
});
