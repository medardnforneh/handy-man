<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Support\Redact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp through Meta's Cloud API (build plan P7-05, doc 07) — the workhorse follow-up channel.
 *
 * Every message this product sends on WhatsApp is business-initiated (a nudge hours or days after
 * the last exchange), which puts it outside the 24-hour service window; Meta delivers those ONLY as
 * pre-approved templates. Rather than a template per follow-up kind and language — thirty-two
 * submissions through a human review queue — the product uses ONE generic utility template per
 * language: `{{1}}` is the title, `{{2}}` the body, and a URL button carries the follow-up id as its
 * dynamic suffix, which is the deep link the delivery layer already builds. The copy itself stays
 * in this codebase (lang/followup.*), where it is bilingual and tested. A kind that later earns a
 * richer template of its own is mapped in `notifications.whatsapp_meta.templates`.
 *
 * Best effort by contract: a single unreachable number, a rejected parameter or a Meta outage is
 * logged and dropped, never thrown — the follow-up row is the record, and the channel ladder is
 * the retry. The exact template text to submit is in docs/07-follow-ups-and-lifecycle.md.
 */
final class MetaWhatsAppSender implements WhatsAppSender
{
    /** Meta rejects a body parameter with a newline, a tab or more than four consecutive spaces. */
    private const MAX_PARAM_LENGTH = 1024;

    /**
     * @param  array<string, string>  $templates  follow-up kind → template name, for kinds with their own
     * @param  array<string, string>  $languages  our locale → Meta language code
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly string $defaultTemplate,
        private readonly array $templates,
        private readonly array $languages,
        private readonly string $deepLinkBase,
        private readonly string $baseUrl = 'https://graph.facebook.com',
        private readonly string $apiVersion = 'v21.0',
    ) {}

    public function name(): string
    {
        return 'meta';
    }

    public function send(string $phoneE164, string $template, array $variables, string $locale, ?string $deepLink = null): void
    {
        $payload = $this->payload($phoneE164, $template, $variables, $locale, $deepLink);

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(10)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);
        } catch (ConnectionException $e) {
            Log::warning('whatsapp.meta.unreachable', ['to' => Redact::phone($phoneE164), 'template' => $payload['template']['name'], 'error' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::warning('whatsapp.meta.failed', ['to' => Redact::phone($phoneE164), 'error' => $e->getMessage()]);

            return;
        }

        if ($response->successful()) {
            Log::info('whatsapp.meta.sent', [
                'to' => Redact::phone($phoneE164),
                'template' => $payload['template']['name'],
                'message_id' => $response->json('messages.0.id'),
            ]);

            return;
        }

        // A number that is not on WhatsApp (131026) is a fact about the recipient, not a failure of
        // ours; everything else — a bad token, an unapproved template, a rate limit — is a warning
        // someone should read, with Meta's own message.
        $code = (int) $response->json('error.code', 0);
        Log::log($code === 131026 ? 'info' : 'warning', 'whatsapp.meta.rejected', [
            'to' => Redact::phone($phoneE164),
            'template' => $payload['template']['name'],
            'status' => $response->status(),
            'code' => $code,
            'message' => $response->json('error.message'),
        ]);
    }

    /**
     * The Cloud API template message. Public so the test can pin the shape without a fake server.
     *
     * @param  array<int, string>  $variables
     * @return array<string, mixed>
     */
    public function payload(string $phoneE164, string $template, array $variables, string $locale, ?string $deepLink): array
    {
        $components = [];

        $body = array_values(array_filter(array_map(fn (string $v): string => self::sanitise($v), $variables), fn (string $v): bool => $v !== ''));
        if ($body !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $body),
            ];
        }

        // The URL button's dynamic suffix: the part of the deep link after the configured base
        // (`https://app.handyman.cm/follow-up/` + id). Meta appends it to the URL approved on the
        // template, so the template is approved once and every follow-up reuses it.
        $suffix = $this->suffixOf($deepLink);
        if ($suffix !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $suffix]],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            // Meta wants digits only, no plus.
            'to' => ltrim($phoneE164, '+'),
            'type' => 'template',
            'template' => [
                'name' => $this->templates[$template] ?? $this->defaultTemplate,
                'language' => ['code' => $this->languages[$locale] ?? $this->languages['en'] ?? 'en'],
                'components' => $components,
            ],
        ];
    }

    private function suffixOf(?string $deepLink): ?string
    {
        if ($deepLink === null || $this->deepLinkBase === '') {
            return null;
        }
        $base = rtrim($this->deepLinkBase, '/').'/';
        if (! str_starts_with($deepLink, $base)) {
            return null;
        }
        $suffix = substr($deepLink, strlen($base));

        return $suffix !== '' ? $suffix : null;
    }

    /** What Meta will accept as a text parameter: one line, single-spaced, bounded. */
    private static function sanitise(string $text): string
    {
        $flat = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_strlen($flat) > self::MAX_PARAM_LENGTH ? mb_substr($flat, 0, self::MAX_PARAM_LENGTH - 1).'…' : $flat;
    }
}
