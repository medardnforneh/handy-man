<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Engagements\Policies\EngagementPolicy;
use App\Domain\Identity\Otp\LogOtpSender;
use App\Domain\Identity\Otp\OtpSender;
use App\Domain\Identity\Otp\SmsOtpSender;
use App\Domain\Reference\Policies\NotePolicy;
use App\Listeners\RecordLastLogin;
use App\Models\Engagement;
use App\Models\Note;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Domain policies live under their module (not App\Policies), so Laravel's policy
     * auto-discovery can't find them. Bind them explicitly here — one line per policy.
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Note::class => NotePolicy::class,
        Engagement::class => EngagementPolicy::class,
    ];

    public function register(): void
    {
        // OTP delivery: the log in dev, the SMS rail (notifications.sms) anywhere real.
        $this->app->bind(OtpSender::class, function ($app): OtpSender {
            $driver = (string) config('otp.sender', 'log');

            return match ($driver) {
                'log' => new LogOtpSender,
                'sms' => $app->make(SmsOtpSender::class),
                default => throw new InvalidArgumentException("Unknown OTP sender: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Session logins are the admin panel; the app signs in with tokens and stamps its own.
        Event::listen(Login::class, RecordLastLogin::class);

        $this->registerRateLimiters();
    }

    /**
     * The API's rate limiters (see `config/api.rate_limits` for why each exists).
     *
     * Named here because Laravel no longer defines an `api` limiter for us, and a `throttle:api`
     * middleware naming a limiter that does not exist is a runtime error rather than a silent
     * no-op — so these and the middleware in `bootstrap/app.php` have to land together.
     *
     * The per-user key uses the `sanctum` guard by name. The default guard is `web` (session),
     * which a Bearer client never satisfies, so `$request->user()` would be null for every app
     * request and the whole API would share one bucket per IP — which for anyone behind a carrier
     * NAT (most of this product's users) would throttle a city.
     */
    private function registerRateLimiters(): void
    {
        // Each closure reads its own limit when the request arrives, not when the provider boots,
        // so a test (or a `config:cache`d override) actually takes effect.
        RateLimiter::for('api', function (Request $request): Limit {
            $user = Auth::guard('sanctum')->user();
            $limit = Limit::perMinute(self::limit('api', 120));

            return $user !== null
                ? $limit->by('u:'.$user->getAuthIdentifier())
                : $limit->by('ip:'.$request->ip());
        });

        // Credential endpoints: always by IP. Keying these by user would be meaningless — nobody
        // is authenticated yet, which is the entire point of the routes being brute-forceable.
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(self::limit('auth', 20))->by('auth:'.$request->ip()));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(self::limit('webhooks', 60))->by('hook:'.$request->ip()));
    }

    private static function limit(string $name, int $fallback): int
    {
        $configured = config("api.rate_limits.{$name}");

        return is_numeric($configured) ? (int) $configured : $fallback;
    }
}
