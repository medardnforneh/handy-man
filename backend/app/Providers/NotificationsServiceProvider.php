<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\FakePushSender;
use App\Domain\Notifications\FakeSmsSender;
use App\Domain\Notifications\FakeWhatsAppSender;
use App\Domain\Notifications\FcmAccessToken;
use App\Domain\Notifications\FcmPushSender;
use App\Domain\Notifications\Listeners\NotifyOnOutboxMessage;
use App\Domain\Notifications\LogSmsSender;
use App\Domain\Notifications\LogWhatsAppSender;
use App\Domain\Notifications\MetaWhatsAppSender;
use App\Domain\Notifications\PushSender;
use App\Domain\Notifications\SmsSender;
use App\Domain\Notifications\TwilioSmsSender;
use App\Domain\Notifications\WhatsAppSender;
use App\Events\OutboxMessagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Wires push notifications (build plan P5-05). The active {@see PushSender} is chosen by
 * `config('notifications.push')` — Fake by default (tests/local), FCM in prod. The app depends on
 * the interface, never a provider. Push fan-out subscribes to the outbox seam
 * ({@see OutboxMessagePublished}), so a notification fires only for a committed event.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One shared Fake instance, resolvable by its concrete type so a test can assert its record.
        $this->app->singleton(FakePushSender::class);

        $this->app->singleton(PushSender::class, function ($app): PushSender {
            $driver = (string) config('notifications.push', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakePushSender::class),
                'fcm' => new FcmPushSender(
                    projectId: (string) config('notifications.fcm.project_id'),
                    auth: $this->fcmAccessToken(),
                    baseUrl: (string) config('notifications.fcm.base_url'),
                ),
                default => throw new InvalidArgumentException("Unknown push sender: {$driver}"),
            };
        });

        // One shared Fake SMS instance, resolvable by its concrete type for test assertions.
        $this->app->singleton(FakeSmsSender::class);

        $this->app->singleton(SmsSender::class, function ($app): SmsSender {
            $driver = (string) config('notifications.sms', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakeSmsSender::class),
                'log' => new LogSmsSender,
                'twilio' => new TwilioSmsSender(
                    accountSid: (string) config('notifications.twilio.account_sid'),
                    authToken: (string) config('notifications.twilio.auth_token'),
                    from: (string) config('notifications.twilio.from'),
                    baseUrl: (string) config('notifications.twilio.base_url'),
                ),
                default => throw new InvalidArgumentException("Unknown SMS sender: {$driver}"),
            };
        });

        // One shared Fake WhatsApp instance, resolvable by its concrete type for test assertions.
        $this->app->singleton(FakeWhatsAppSender::class);

        $this->app->singleton(WhatsAppSender::class, function ($app): WhatsAppSender {
            $driver = (string) config('notifications.whatsapp', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakeWhatsAppSender::class),
                'log' => new LogWhatsAppSender,
                'meta' => new MetaWhatsAppSender(
                    accessToken: (string) config('notifications.whatsapp_meta.access_token'),
                    phoneNumberId: (string) config('notifications.whatsapp_meta.phone_number_id'),
                    defaultTemplate: (string) config('notifications.whatsapp_meta.template'),
                    templates: (array) config('notifications.whatsapp_meta.templates', []),
                    languages: (array) config('notifications.whatsapp_meta.languages', []),
                    deepLinkBase: (string) config('notifications.follow_up_link_base'),
                    baseUrl: (string) config('notifications.whatsapp_meta.base_url'),
                    apiVersion: (string) config('notifications.whatsapp_meta.api_version'),
                ),
                default => throw new InvalidArgumentException("Unknown WhatsApp sender: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, NotifyOnOutboxMessage::class);
    }

    /**
     * The service account wins when one is configured (a file, or the JSON base64-encoded into the
     * environment); a static token is the fallback for a one-off. Misconfiguration surfaces here,
     * at boot of the sender, not as a silent no-op per push.
     */
    private function fcmAccessToken(): FcmAccessToken
    {
        $file = (string) config('notifications.fcm.service_account_file');
        $inline = (string) config('notifications.fcm.service_account_json');

        $json = match (true) {
            $file !== '' => (string) file_get_contents($file),
            $inline !== '' => (string) base64_decode($inline, true),
            default => '',
        };

        if ($json !== '') {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return FcmAccessToken::serviceAccount($decoded);
        }

        return FcmAccessToken::static((string) config('notifications.fcm.access_token'));
    }
}
