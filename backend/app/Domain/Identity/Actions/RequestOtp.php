<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Otp\OtpSender;
use App\Domain\Identity\OtpException;
use App\Models\OtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issues an OTP challenge (build plan P1-02). Rate limited by phone, IP AND device — SMS pumping is
 * a real fraud vector (doc 04). The code is hashed at rest; only the transient plaintext is sent.
 */
final class RequestOtp
{
    public function __construct(
        private readonly OtpSender $sender,
    ) {}

    public function handle(string $phoneE164, string $purpose, ?string $ip = null, ?string $deviceId = null): OtpChallenge
    {
        $this->enforceRateLimits($phoneE164, $ip, $deviceId);

        $code = $this->generateCode();

        $challenge = OtpChallenge::query()->create([
            'phone_e164' => $phoneE164,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
            'created_at' => now(),
        ]);

        $this->sender->send($phoneE164, $code, $purpose);

        return $challenge;
    }

    private function enforceRateLimits(string $phoneE164, ?string $ip, ?string $deviceId): void
    {
        // Phone limit is counted from the durable record (survives cache flushes) — this is the
        // primary SMS-pumping guard and the P1-02 acceptance.
        $recentForPhone = OtpChallenge::query()
            ->where('phone_e164', $phoneE164)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentForPhone >= (int) config('otp.max_per_phone_per_hour')) {
            throw OtpException::rateLimited();
        }

        // IP + device limits via the rate limiter (defence in depth).
        $this->hitOrFail("otp-ip:{$ip}", (int) config('otp.max_per_ip_per_hour'), $ip);
        $this->hitOrFail("otp-device:{$deviceId}", (int) config('otp.max_per_device_per_hour'), $deviceId);
    }

    private function hitOrFail(string $key, int $max, ?string $discriminator): void
    {
        if ($discriminator === null || $discriminator === '') {
            return;
        }

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw OtpException::rateLimited();
        }

        RateLimiter::hit($key, 3600);
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.code_length');
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
