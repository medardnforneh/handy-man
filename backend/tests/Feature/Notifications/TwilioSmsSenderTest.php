<?php

declare(strict_types=1);

use App\Domain\Notifications\SmsSender;
use App\Domain\Notifications\TwilioSmsSender;
use App\Domain\Safety\Actions\RaisePanicAlert;
use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS through Twilio (doc 07 "sms → transactional only"). Best effort by contract — a panic
 * fan-out must reach the other contacts when one number is dead.
 */
function twilio(string $from = '+15005550006'): TwilioSmsSender
{
    return new TwilioSmsSender(accountSid: 'ACtest', authToken: 'secret', from: $from, baseUrl: 'https://twilio.test');
}

it('posts the message as the form Twilio reads, under basic auth, from the configured sender', function () {
    Http::fake(['twilio.test/*' => Http::response(['sid' => 'SM1', 'status' => 'queued', 'num_segments' => '1'], 201)]);

    twilio()->send('+237677000111', 'Votre code HandyMan : 123456.');

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://twilio.test/2010-04-01/Accounts/ACtest/Messages.json'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('ACtest:secret'))
        && $r->isForm()
        && $r['To'] === '+237677000111'
        && $r['From'] === '+15005550006'
        && $r['Body'] === 'Votre code HandyMan : 123456.');
});

it('sends through a Messaging Service when the sender is one, and an alphanumeric id as From otherwise', function () {
    expect(twilio('MG0123456789abcdef')->fields('+237677000111', 'x'))
        ->toBe(['To' => '+237677000111', 'Body' => 'x', 'MessagingServiceSid' => 'MG0123456789abcdef'])
        ->and(twilio('HandyMan')->fields('+237677000111', 'x'))
        ->toBe(['To' => '+237677000111', 'Body' => 'x', 'From' => 'HandyMan']);
});

it('never throws — Twilio down, a bad token, a landline are all logged and dropped', function () {
    Log::spy();
    Http::fake(['twilio.test/*' => Http::sequence()
        ->pushResponse(fn () => throw new ConnectionException('timed out'))
        ->push(['code' => 20003, 'message' => 'Authentication Error', 'status' => 401], 401)
        ->push(['code' => 21614, 'message' => "'To' number is not a valid mobile number", 'status' => 400], 400),
    ]);

    foreach (['+237677000111', '+237677000222', '+237222000333'] as $to) {
        twilio()->send($to, 'x');
    }

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m): bool => $m === 'sms.twilio.unreachable')->once();
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $m, array $ctx): bool => $level === 'warning' && $m === 'sms.twilio.rejected' && $ctx['code'] === 20003)->once();
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $m, array $ctx): bool => $level === 'info' && $m === 'sms.twilio.rejected' && $ctx['code'] === 21614)->once();
    Http::assertSentCount(2);
});

it('is what a panic alert fans out through when the driver is twilio — and one dead number does not stop the others', function () {
    config()->set('notifications.sms', 'twilio');
    config()->set('notifications.twilio', ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'from' => 'HandyMan', 'base_url' => 'https://twilio.test']);
    app()->forgetInstance(SmsSender::class);
    Http::fake([
        'twilio.test/*' => function (Request $r) {
            return $r['To'] === '+237677000111'
                ? Http::response(['code' => 21211, 'message' => 'Invalid To', 'status' => 400], 400)
                : Http::response(['sid' => 'SM2', 'status' => 'queued'], 201);
        },
    ]);

    $user = User::factory()->create();
    EmergencyContact::factory()->create(['user_id' => $user->id, 'phone_e164' => '+237677000111']);
    EmergencyContact::factory()->create(['user_id' => $user->id, 'phone_e164' => '+237677000222']);

    app(RaisePanicAlert::class)->handle($user);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r): bool => $r['To'] === '+237677000222' && $r['From'] === 'HandyMan');
});
