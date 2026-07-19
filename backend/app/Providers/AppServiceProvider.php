<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Engagements\Policies\EngagementPolicy;
use App\Domain\Identity\Otp\LogOtpSender;
use App\Domain\Identity\Otp\OtpSender;
use App\Domain\Reference\Policies\NotePolicy;
use App\Models\Engagement;
use App\Models\Note;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // OTP delivery — LogOtpSender in dev; the comms layer (doc 07) swaps in WhatsApp/SMS.
        $this->app->bind(OtpSender::class, LogOtpSender::class);
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
