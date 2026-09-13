<?php

declare(strict_types=1);

use App\Domain\Notifications\FcmAccessToken;
use App\Domain\Notifications\FcmPushSender;
use App\Domain\Notifications\PushMessage;
use App\Domain\Notifications\PushSender;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * FCM HTTP v1 with the service-account exchange (P5-05, doc 11): the JWT Google verifies, the
 * hourly token cached, a dead registration token cleared where it is learnt.
 */
/**
 * A committed, test-only RSA key (tests/Fixtures) rather than one minted per run: PHP on Windows
 * cannot generate a key without an openssl.cnf it does not ship with. It signs nothing real.
 */
function fcmKeyPair(): array
{
    $private = (string) file_get_contents(__DIR__.'/../../Fixtures/fcm-service-account-test.key');
    $key = openssl_pkey_get_private($private);

    return [$private, openssl_pkey_get_details($key)['key']];
}

function fcmServiceAccount(string $privateKey): array
{
    return ['client_email' => 'push@handyman-test.iam.gserviceaccount.com', 'private_key' => $privateKey, 'token_uri' => 'https://oauth.test/token'];
}

function fcmSender(FcmAccessToken $auth): FcmPushSender
{
    return new FcmPushSender(projectId: 'handyman-test', auth: $auth, baseUrl: 'https://fcm.test');
}

beforeEach(fn () => Cache::forget('fcm.access_token'));

it('mints the bearer from a signed service-account JWT, verifiable with the public key, and caches it', function () {
    [$private, $public] = fcmKeyPair();
    Http::fake([
        'oauth.test/token' => Http::response(['access_token' => 'ya29.minted', 'expires_in' => 3599, 'token_type' => 'Bearer']),
        'fcm.test/*' => Http::response(['name' => 'projects/handyman-test/messages/1']),
    ]);
    $auth = FcmAccessToken::serviceAccount(fcmServiceAccount($private));

    $sent = fcmSender($auth)->send(['tok-a', 'tok-b'], new PushMessage('Hi', 'There', ['type' => 'follow_up', 'follow_up_id' => 'fu-1']));

    expect($sent)->toBe(2);
    Http::assertSentCount(3); // ONE exchange for two pushes
    Http::assertSent(function (Request $r) use ($public): bool {
        if ($r->url() !== 'https://oauth.test/token') {
            return false;
        }
        expect($r['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:jwt-bearer');
        [$h, $p, $s] = explode('.', (string) $r['assertion']);
        $claims = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        expect($claims['iss'])->toBe('push@handyman-test.iam.gserviceaccount.com')
            ->and($claims['scope'])->toBe('https://www.googleapis.com/auth/firebase.messaging')
            ->and($claims['aud'])->toBe('https://oauth.test/token')
            ->and($claims['exp'] - $claims['iat'])->toBe(3600)
            ->and(openssl_verify("{$h}.{$p}", base64_decode(strtr($s, '-_', '+/')), $public, OPENSSL_ALGO_SHA256))->toBe(1);

        return true;
    });
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://fcm.test/v1/projects/handyman-test/messages:send'
        && $r->hasHeader('Authorization', 'Bearer ya29.minted')
        && $r['message']['token'] === 'tok-a'
        && $r['message']['notification'] === ['title' => 'Hi', 'body' => 'There']
        && $r['message']['data'] === ['type' => 'follow_up', 'follow_up_id' => 'fu-1']);
    expect(Cache::get('fcm.access_token'))->toBe('ya29.minted');
});

it('clears a registration token FCM reports as gone, so the ladder stops choosing push for that device', function () {
    Http::fake([
        'fcm.test/*' => function (Request $r) {
            return $r['message']['token'] === 'tok-dead'
                ? Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'ok']);
        },
    ]);
    $user = User::factory()->create();
    Device::factory()->create(['user_id' => $user->id, 'push_token' => 'tok-dead']);
    Device::factory()->create(['user_id' => $user->id, 'push_token' => 'tok-live']);

    $sent = fcmSender(FcmAccessToken::static('ya29.static'))->send(['tok-dead', 'tok-live'], new PushMessage('Hi', 'There'));

    expect($sent)->toBe(1)
        ->and(Device::query()->where('push_token', 'tok-dead')->exists())->toBeFalse()
        ->and(Device::query()->where('push_token', 'tok-live')->exists())->toBeTrue();
});

it('re-mints once on a 401 mid fan-out, and never throws when Google is down', function () {
    [$private] = fcmKeyPair();
    Cache::put('fcm.access_token', 'ya29.stale', 3300);
    Http::fake([
        'oauth.test/token' => Http::response(['access_token' => 'ya29.fresh', 'expires_in' => 3599]),
        'fcm.test/*' => fn (Request $r) => $r->hasHeader('Authorization', 'Bearer ya29.stale')
            ? Http::response(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401)
            : Http::response(['name' => 'ok']),
    ]);

    $sent = fcmSender(FcmAccessToken::serviceAccount(fcmServiceAccount($private)))->send(['tok-a'], new PushMessage('Hi', 'There'));

    expect($sent)->toBe(1)->and(Cache::get('fcm.access_token'))->toBe('ya29.fresh');
});

it('sends nothing, and throws nothing, when Google will not issue a token', function () {
    [$private] = fcmKeyPair();
    Http::fake(['oauth.test/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400)]);

    expect(fcmSender(FcmAccessToken::serviceAccount(fcmServiceAccount($private)))->send(['tok-a'], new PushMessage('Hi', 'There')))->toBe(0);
    Http::assertNotSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://fcm.test'));
    expect(Cache::get('fcm.access_token'))->toBeNull();
});

it('is built from the service account in the environment when the driver is fcm', function () {
    [$private] = fcmKeyPair();
    config()->set('notifications.push', 'fcm');
    config()->set('notifications.fcm.project_id', 'handyman-test');
    config()->set('notifications.fcm.service_account_json', base64_encode(json_encode(fcmServiceAccount($private))));
    app()->forgetInstance(PushSender::class);

    expect(app(PushSender::class))->toBeInstanceOf(FcmPushSender::class)->and(app(PushSender::class)->name())->toBe('fcm');

    config()->set('notifications.fcm.service_account_json', base64_encode('{"client_email":"x"}'));
    app()->forgetInstance(PushSender::class);
    expect(fn () => app(PushSender::class))->toThrow(RuntimeException::class, 'private_key');
});
