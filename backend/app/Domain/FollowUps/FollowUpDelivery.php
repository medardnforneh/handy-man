<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Domain\Notifications\PushMessage;
use App\Domain\Notifications\PushSender;
use App\Domain\Notifications\SmsSender;
use App\Domain\Notifications\WhatsAppSender;
use App\Models\Device;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\Party;
use App\Models\Quotation;
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
        $vars = $this->variables($followUp, $locale);
        $title = $this->copy($followUp->kind, 'title', $locale, $vars);
        $body = $this->copy($followUp->kind, 'body', $locale, $vars);
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

    /**
     * @param  array<string, string>  $vars
     */
    private function copy(FollowUpKind $kind, string $part, string $locale, array $vars = []): string
    {
        $key = "followup.{$kind->value}.{$part}";
        $resolved = Lang::has($key)
            ? (string) __($key, $vars, $locale)
            : (string) __("followup.generic.{$part}", $vars, $locale);

        // A per-kind string that names something we could not resolve would go out with a raw `:service`
        // in it. Fall back to the generic copy rather than send a message with a placeholder in it —
        // this is the one failure a recipient would definitely notice.
        return str_contains($resolved, ':') && preg_match('/:[a-z_]+/', $resolved) === 1
            ? (string) __("followup.generic.{$part}", [], $locale)
            : $resolved;
    }

    /**
     * The values a per-kind template may name. Resolved in the target's comms locale — a maintenance
     * nudge that says "your AC servicing" is worth far more than one that says "your service", and
     * the taxonomy is already bilingual (P1-07b).
     *
     * @return array<string, string>
     */
    private function variables(FollowUp $followUp, string $locale): array
    {
        $vars = [];

        $job = $followUp->job_id !== null ? Job::query()->find($followUp->job_id) : null;
        $skill = $job?->skill()->first();
        if ($skill !== null) {
            $vars['service'] = $skill->name($locale);
        }

        if ($followUp->quotation_id !== null) {
            $quotation = Quotation::query()->find($followUp->quotation_id);
            $provider = $quotation !== null
                ? Party::query()->find($quotation->provider_party_id)
                : null;
            // An empty display name is as unusable as a missing one — either way there is nothing to
            // put in the message, and the placeholder guard in copy() then falls back to generic.
            if ($provider !== null && trim((string) $provider->display_name) !== '') {
                $vars['provider'] = (string) $provider->display_name;
            }
        }

        return $vars;
    }
}
