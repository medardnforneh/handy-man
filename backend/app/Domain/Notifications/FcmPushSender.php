<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging HTTP v1 sender (build plan P5-05). One request per token to
 * `/v1/projects/{project}/messages:send`; a per-token failure is logged and skipped, never thrown,
 * so one dead token can't sink a whole fan-out. Live delivery pends real project credentials (the
 * OAuth2 access token is provisioned at deploy); the request shape here is the contract.
 */
final class FcmPushSender implements PushSender
{
    public function __construct(
        private readonly string $projectId,
        private readonly string $accessToken,
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

        $url = "{$this->baseUrl}/v1/projects/{$this->projectId}/messages:send";
        $accepted = 0;

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($this->accessToken)
                    ->acceptJson()
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $message->title, 'body' => $message->body],
                            'data' => $message->data,
                        ],
                    ]);

                if ($response->successful()) {
                    $accepted++;
                }
            } catch (\Throwable $e) {
                Log::warning('FCM push failed for a token', ['error' => $e->getMessage()]);
            }
        }

        return $accepted;
    }
}
