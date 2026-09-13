<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The first superadmin on a fresh installation (docs/11-deployment.md) — the one staff account that
 * no existing staff account can grant.
 */
it('creates the first superadmin with a one-time password, and is idempotent on the email', function () {
    $this->artisan('staff:bootstrap', ['email' => 'Founder@Example.cm', 'phone' => '+237677000001', '--name' => 'The Founder'])
        ->expectsOutputToContain('superadmin: founder@example.cm')
        ->expectsOutputToContain('One-time password')
        ->assertSuccessful();

    $user = User::query()->where('email', 'founder@example.cm')->firstOrFail();
    expect($user->hasRole('superadmin'))->toBeTrue()
        ->and($user->party->display_name)->toBe('The Founder')
        ->and($user->password_hash)->not->toBeNull()
        ->and(Hash::check('password', (string) $user->password_hash))->toBeFalse(); // never the demo one

    // Again: no second account, no new password, the role still there.
    $this->artisan('staff:bootstrap', ['email' => 'founder@example.cm', 'phone' => '+237677000001'])
        ->expectsOutputToContain('Account existed')
        ->assertSuccessful();
    expect(User::query()->where('email', 'founder@example.cm')->count())->toBe(1);
});

it('refuses a bad email or phone before touching the database', function () {
    $this->artisan('staff:bootstrap', ['email' => 'not-an-email', 'phone' => '+237677000002'])->assertFailed();
    $this->artisan('staff:bootstrap', ['email' => 'ok@example.cm', 'phone' => '677 00 00 02'])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
