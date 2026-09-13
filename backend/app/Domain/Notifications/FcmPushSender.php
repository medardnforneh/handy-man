<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\Device;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging HTTP v1 sender (build plan P5-05). One request per token to
 * `/v1/projects/{project}/messages:send`; a per-token failure is logged and skipped, never thrown,
 * so one dead token can't sink a whole fan-out. The bearer token comes from {@see FcmAccessToken}
 * (a service-account exchange in production, cached for the hour Google grants it).
 *
 * A token FCM reports as gone — the app was uninstalled, or the token rotated and the device has
 * not checked in since — is cleared on its device row here, in the one place that learns it. The
 * channel ladder then stops choosing push for that device and falls through to WhatsApp, instead
 * of "sending" every nudge into a void for as long as the row lives.
 */
final class FcmPushSender implements PushSender
{
    public function __construct(
        private readonly string $projectId,
        private readonly FcmAccessToken $auth,
        private readonly string $baseUrl,
    ) {}

    public function name(): string
    {
        return 'fcm';
    }

    public function send(array $tokens, PushMessage $message): int
    {
        if ($tokens === []) {
            return 0;
        }

        $bearer = $this->auth->token();
        if ($bearer === null) {
            Log::warning('fcm.push.no_credentials', ['tokens' => count($tokens)]);

            return 0;
        }

        $url = "{$this->baseUrl}/v1/projects/{$this->projectId}/messages:send";
        $accepted = 0;

        foreach ($tokens as $token) {
            try {
                $response = $this->post($url, $bearer, $token, $message);

                // An hour-old token can expire mid fan-out; mint one and retry this token once.
                if ($response->status() === 401) {
                    $this->auth->forget();
                    $bearer = $this->auth->token() ?? $bearer;
                    $response = $this->post($url, $bearer, $token, $message);
                }

                if ($response->successful()) {
                    $accepted++;

                    continue;
                }

                $this->reject($token, $response);
            } catch (Throwable $e) {
                Log::warning('fcm.push.failed', ['error' => $e->getMessage()]);
            }
        }

        return $accepted;
    }

    private function post(string $url, string $bearer, string $token, PushMessage $message): Response
    {
        return Http::withToken($bearer)
            ->acceptJson()
            ->timeout(10)
            ->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $message->title, 'body' => $message->body],
                    // FCM's data map is string → string, which is what PushMessage already types.
                    'data' => $message->data,
                ],
            ]);
    }

    private function reject(string $token, Response $response): void
    {
        $status = (string) $response->json('error.status', '');
        $gone = $status === 'NOT_FOUND';
        foreach ((array) $response->json('error.details', []) as $detail) {
            $gone = $gone || (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED');
        }

        if ($gone) {
            Device::query()->where('push_token', $token)->update(['push_token' => null]);
            Log::info('fcm.push.token_gone', ['status' => $status]);

            return;
        }

        Log::warning('fcm.push.rejected', [
            'http' => $response->status(),
            'status' => $status,
            'message' => $response->json('error.message'),
        ]);
    }
}
