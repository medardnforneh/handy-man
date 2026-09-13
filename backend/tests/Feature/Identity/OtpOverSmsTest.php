<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\RequestOtp;
use App\Domain\Identity\Otp\LogOtpSender;
use App\Domain\Identity\Otp\OtpSender;
use App\Domain\Identity\Otp\SmsOtpSender;
use App\Domain\Notifications\FakeSmsSender;
use App\Models\User;

/**
 * The OTP reaching a phone (build plan P1-02 → doc 07). Until this existed the only OtpSender was
 * the log, in every environment: a deployed build would have taken the phone number, written the
 * code to a file nobody reads, and left the person on the verify screen for ever.
 */
it('is the log in development and the SMS rail anywhere real — chosen by config, not by code', function () {
    config()->set('otp.sender', 'log');
    expect(app(OtpSender::class))->toBeInstanceOf(LogOtpSender::class);

    config()->set('otp.sender', 'sms');
    expect(app(OtpSender::class))->toBeInstanceOf(SmsOtpSender::class);
});

it('texts the code through the SMS rail when someone asks to sign in', function () {
    config()->set('otp.sender', 'sms');
    config()->set('otp.ttl_minutes', 5);
    $sms = app(FakeSmsSender::class);

    $issued = app(RequestOtp::class)->handle('+237677000111', 'signup');

    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['to'])->toBe('+237677000111')
        ->and($sms->sent[0]['message'])->toContain($issued->code)
        ->and($sms->sent[0]['message'])->toContain('5 min');
});

it('speaks both languages to a stranger, French first, and one to someone it knows', function () {
    config()->set('otp.ttl_minutes', 5);
    $sender = app(SmsOtpSender::class);

    $stranger = $sender->message('+237677000111', '123456');
    expect($stranger)->toBe("Votre code HandyMan : 123456. Valable 5 min. Ne le partagez jamais.\nYour HandyMan code: 123456. Valid 5 min. Never share it.")
        // One GSM-7 segment: no accents anywhere, and both lines together under 160 characters.
        ->and(strlen($stranger))->toBe(mb_strlen($stranger))
        ->and(strlen($stranger))->toBeLessThanOrEqual(160);

    User::factory()->create(['phone_e164' => '+237677000222', 'comms_locale' => 'en']);
    expect($sender->message('+237677000222', '123456'))->toBe('Your HandyMan code: 123456. Valid 5 min. Never share it.');
});
