<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\FakePushSender;
use App\Domain\Notifications\FakeSmsSender;
use App\Domain\Notifications\FakeWhatsAppSender;
use App\Domain\Notifications\FcmPushSender;
use App\Domain\Notifications\Listeners\NotifyOnOutboxMessage;
use App\Domain\Notifications\LogSmsSender;
use App\Domain\Notifications\LogWhatsAppSender;
use App\Domain\Notifications\PushSender;
use App\Domain\Notifications\SmsSender;
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
                    accessToken: (string) config('notifications.fcm.access_token'),
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
                default => throw new InvalidArgumentException("Unknown WhatsApp sender: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, NotifyOnOutboxMessage::class);
    }
}
