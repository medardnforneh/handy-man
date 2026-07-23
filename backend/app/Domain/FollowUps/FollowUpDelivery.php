<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Domain\Notifications\PushMessage;
use App\Domain\Notifications\PushSender;
use App\Domain\Notifications\SmsSender;
use App\Domain\Notifications\WhatsAppSender;
use App\Models\Device;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

/**
 * Delivers a follow-up on its channel (build plan P7-05/06). Copy is resolved in the target's comms
 * locale (doc 09) — a per-kind string, falling back to a generic one — and every message deep-links
 * back to the follow-up so a tap can be recorded as a `response_action`. The follow-up row itself is
 * the in-app record; this handles the outbound push / WhatsApp / SMS.
 */
final class FollowUpDelivery
{
    public function __construct(
        private readonly PushSender $push,
        private readonly WhatsAppSender $whatsApp,
        private readonly SmsSender $sms,
    ) {}

    public function deliver(FollowUp $followUp, User $user): void
    {
        $locale = $user->comms_locale ?: (string) config('app.locale', 'en');
        $title = $this->copy($followUp->kind, 'title', $locale);
        $body = $this->copy($followUp->kind, 'body', $locale);
        $deepLink = rtrim((string) config('app.url', ''), '/')."/follow-up/{$followUp->id}";

        match ($followUp->channel) {
            FollowUpChannel::Push => $this->deliverPush($user, $followUp, $title, $body),
            FollowUpChannel::WhatsApp => $this->whatsApp->send($user->phone_e164, $followUp->kind->value, [$title, $body], $locale, $deepLink),
            FollowUpChannel::Sms => $this->sms->send($user->phone_e164, "{$title} — {$body} {$deepLink}"),
            // in_app is the follow-up row itself; email is reserved for receipts, not nudges.
            FollowUpChannel::InApp, FollowUpChannel::Email => null,
        };
    }

    private function deliverPush(User $user, FollowUp $followUp, string $title, string $body): void
    {
        /** @var array<int, string> $tokens */
        $tokens = Device::query()
            ->where('user_id', $user->id)
            ->whereNotNull('push_token')
            ->whereNull('revoked_at')
            ->pluck('push_token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $this->push->send($tokens, new PushMessage($title, $body, [
            'type' => 'follow_up',
            'follow_up_id' => $followUp->id,
            'kind' => $followUp->kind->value,
        ]));
    }

    private function copy(FollowUpKind $kind, string $part, string $locale): string
    {
        $key = "followup.{$kind->value}.{$part}";

        return Lang::has($key)
            ? (string) __($key, [], $locale)
            : (string) __("followup.generic.{$part}", [], $locale);
    }
}
