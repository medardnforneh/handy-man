<?php

declare(strict_types=1);

use App\Domain\Metrics\MarketplaceAnalytics;
use App\Models\UsageDay;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Launch checklist (doc 05): "Mobile-browser share of customer traffic instrumented (the doc 08
 * switch trigger)." Doc 08 switches to Flutter only if mobile-APP usage is proven to dominate
 * mobile-web; this is the proof's data, recorded from launch as one row per person per platform
 * per day.
 */
const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 13; TECNO KI5q) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36';
const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

it('records one row per person per platform per day, however many requests they make', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    foreach (range(1, 5) as $_) {
        $this->getJson('/api/v1/auth/me', ['X-Client-Platform' => 'android'])->assertOk();
    }

    $rows = UsageDay::query()->where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->platform)->toBe('android')
        ->and($rows->first()->day->toDateString())->toBe(now()->toDateString());
});

it('tells a phone\'s browser from a desktop\'s when the client is the web build', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me', ['X-Client-Platform' => 'web', 'User-Agent' => ANDROID_UA])->assertOk();
    $this->getJson('/api/v1/auth/me', ['X-Client-Platform' => 'web', 'User-Agent' => DESKTOP_UA])->assertOk();
    // A client that predates the header is a browser too — classified by its agent, never dropped.
    $this->getJson('/api/v1/auth/me', ['User-Agent' => ANDROID_UA])->assertOk();

    expect(UsageDay::query()->where('user_id', $user->id)->pluck('platform')->sort()->values()->all())
        ->toBe(['web_desktop', 'web_mobile']);
});

it('records nothing for an unauthenticated request', function () {
    $this->getJson('/api/v1/meta', ['X-Client-Platform' => 'android'])->assertOk();

    expect(UsageDay::query()->count())->toBe(0);
});

it('computes the app share of person-days over the window, with the web split underneath', function () {
    $users = User::factory()->count(4)->create();
    $rows = [
        [$users[0], 'android', 0], [$users[0], 'android', 1], [$users[0], 'android', 2],
        [$users[1], 'ios', 0],
        [$users[2], 'web_mobile', 0], [$users[2], 'web_mobile', 1],
        [$users[3], 'web_desktop', 0],
        [$users[3], 'web_desktop', 40], // outside a 30-day window — not counted
    ];
    foreach ($rows as [$user, $platform, $daysAgo]) {
        UsageDay::query()->create(['user_id' => $user->id, 'day' => now()->subDays($daysAgo)->toDateString(), 'platform' => $platform, 'first_seen_at' => now()]);
    }

    $share = app(MarketplaceAnalytics::class)->platformShare(30);

    expect($share['person_days'])->toBe(7)
        ->and($share['app_share'])->toBe(round(4 / 7, 4))
        ->and($share['shares']['web_mobile'])->toBe(round(2 / 7, 4))
        ->and($share['shares']['web_desktop'])->toBe(round(1 / 7, 4));
});
