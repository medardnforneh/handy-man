<?php

declare(strict_types=1);

namespace App\Domain\Identity\Otp;

use App\Domain\Notifications\SmsSender;
use App\Models\User;

/**
 * OTP delivery over the SMS rail (build plan P1-02 → doc 07). Until this existed the only
 * {@see OtpSender} was the log — in every environment — so no one could ever have signed in to a
 * deployed build. It composes the general {@see SmsSender}, so the aggregator is chosen once
 * (`notifications.sms`) for OTPs, panic alerts and follow-ups alike.
 *
 * The copy is one line per language, unaccented on purpose (GSM-7: accents push a text into the
 * UCS-2 alphabet and halve what fits in one segment). Someone signing in already has a
 * `comms_locale` and gets one line; someone signing UP is unknown and gets both, French first —
 * the two together still fit a single 160-character segment.
 */
final class SmsOtpSender implements OtpSender
{
    public function __construct(
        private readonly SmsSender $sms,
    ) {}

    public function send(string $phoneE164, string $code, string $purpose): void
    {
        $this->sms->send($phoneE164, $this->message($phoneE164, $code));
    }

    /** Public so the test can pin the copy without a transport. */
    public function message(string $phoneE164, string $code): string
    {
        $vars = ['code' => $code, 'minutes' => (int) config('otp.ttl_minutes')];
        $known = User::query()->where('phone_e164', $phoneE164)->value('comms_locale');

        $locales = is_string($known) && $known !== '' ? [$known] : ['fr', 'en'];

        return implode("\n", array_map(fn (string $locale): string => (string) __('sms.otp', $vars, $locale), $locales));
    }
}
