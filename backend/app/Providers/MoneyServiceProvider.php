<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Money\Gateways\CinetPayGateway;
use App\Domain\Money\Gateways\FakeGateway;
use App\Domain\Money\Gateways\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds the single active {@see PaymentGateway} from config (doc 03). Nothing else in the app names
 * a provider — swap providers by changing `config('payments.gateway')`. The Fake is the default so
 * tests and local dev drive the money flows without live credentials.
 */
final class MoneyServiceProvider extends ServiceProvider
{
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
