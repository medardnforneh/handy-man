<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The bearer token FCM HTTP v1 wants (build plan P5-05, doc 11). Google issues it for an hour
 * against a signed JWT from a service account — the "token exchange" the deployment doc listed
 * as the missing deploy-time piece. This is it, in the app rather than a cron on the box: the
 * service-account JSON is the only secret to provision, and the hourly token is cached for
 * fifty-five minutes and minted again on demand.
 *
 * `FcmAccessToken::static()` keeps the old shape — a pre-obtained token from the environment —
 * for a test or a one-off; a deployed build uses `serviceAccount()`.
 */
final class FcmAccessToken
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'fcm.access_token';

    /** Google's tokens live 3600s; renew with a margin so a fan-out never straddles the expiry. */
    private const CACHE_TTL_SECONDS = 3300;

    /**
     * @param  array{client_email: string, private_key: string, token_uri?: string}|null  $serviceAccount
     */
    private function __construct(
        private readonly ?string $static,
        private readonly ?array $serviceAccount,
    ) {}

    public static function static(string $token): self
    {
        return new self($token, null);
    }

    /**
     * @param  array<string, mixed>  $serviceAccount  the decoded service-account JSON from the Firebase console
     */
    public static function serviceAccount(array $serviceAccount): self
    {
        foreach (['client_email', 'private_key'] as $field) {
            if (! isset($serviceAccount[$field]) || ! is_string($serviceAccount[$field]) || $serviceAccount[$field] === '') {
                throw new RuntimeException("FCM service account is missing `{$field}`.");
            }
        }

        /** @var array{client_email: string, private_key: string, token_uri?: string} $serviceAccount */
        return new self(null, $serviceAccount);
    }

    /** Null when no token can be had — the sender logs and skips rather than throwing. */
    public function token(): ?string
    {
        if ($this->static !== null) {
            return $this->static;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $fresh = $this->exchange();
        if ($fresh !== null) {
            Cache::put(self::CACHE_KEY, $fresh, self::CACHE_TTL_SECONDS);
        }

        return $fresh;
    }

    /** After a 401: the cached token is no longer good, whatever its age. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function exchange(): ?string
    {
        if ($this->serviceAccount === null) {
            return null;
        }

        $tokenUri = $this->serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $assertion = $this->assertion($tokenUri);
        if ($assertion === null) {
            return null;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('fcm.token.unreachable', ['error' => $e->getMessage()]);

            return null;
        }

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::warning('fcm.token.rejected', ['status' => $response->status(), 'error' => $response->json('error_description') ?? $response->json('error')]);

            return null;
        }

        return $token;
    }

    /** RS256 JWT, as Google's service-account flow specifies — no library needed for one claim set. */
    private function assertion(string $tokenUri): ?string
    {
        $now = time();
        $segments = [
            self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            self::b64(json_encode([
                'iss' => $this->serviceAccount['client_email'] ?? '',
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR)),
        ];

        $key = openssl_pkey_get_private($this->serviceAccount['private_key'] ?? '');
        if ($key === false) {
            Log::warning('fcm.token.bad_private_key');

            return null;
        }

        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::warning('fcm.token.sign_failed');

            return null;
        }

        return implode('.', $segments).'.'.self::b64($signature);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
