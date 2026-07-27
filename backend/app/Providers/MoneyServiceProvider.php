<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Money\Gateways\CinetPayGateway;
use App\Domain\Money\Gateways\FakeGateway;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\Listeners\CaptureDepositOnEngagement;
use App\Events\OutboxMessagePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds the single active {@see PaymentGateway} from config (doc 03). Nothing else in the app names
 * a provider — swap providers by changing `config('payments.gateway')`. The Fake is the default so
 * tests and local dev drive the money flows without live credentials.
 */
final class MoneyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Agreement-time deposit capture (P3-13) rides the outbox seam so the gateway call lands
        // after the acceptance transaction has committed.
        Event::listen(OutboxMessagePublished::class, CaptureDepositOnEngagement::class);
    }

    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            $gateway = (string) config('payments.gateway', 'fake');

            return match ($gateway) {
                'fake' => new FakeGateway,
                'cinetpay' => new CinetPayGateway(
                    apikey: (string) config('payments.cinetpay.apikey'),
                    siteId: (string) config('payments.cinetpay.site_id'),
                    secretKey: (string) config('payments.cinetpay.secret_key'),
                    baseUrl: (string) config('payments.cinetpay.base_url'),
                    notifyUrl: (string) config('payments.cinetpay.notify_url'),
                    returnUrl: (string) config('payments.cinetpay.return_url'),
                ),
                default => throw new InvalidArgumentException("Unknown payments gateway: {$gateway}"),
            };
        });
    }
}
