<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Support\Redact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS through Twilio's Programmable Messaging API (doc 07 "sms → transactional only"): the OTP
 * that lets anyone sign in, the panic fan-out to emergency contacts, and the last rung of the
 * follow-up ladder for a phone that is not on WhatsApp.
 *
 * Twilio rather than a Cameroonian aggregator because its API is the one this adapter could be
 * written against without guessing — the shape is stable, documented and reaches every MTN and
 * Orange number. It is also the expensive option per message; the volume here is small (OTPs and
 * emergencies, not marketing) and a local aggregator, when one is chosen for price, is another
 * class behind the same interface (docs/BUILD_STATE.md, open decisions).
 *
 * Best effort by contract ({@see SmsSender}): one unreachable number, a landline, an outage — all
 * logged and dropped, never thrown. A panic alert must reach the other contacts.
 */
final class TwilioSmsSender implements SmsSender
{
    /**
     * @param  string  $from  a purchased number in E.164, an alphanumeric sender id, or a Messaging
     *                        Service SID (`MG…`) — Twilio takes the last as a different field
     */
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
        private readonly string $baseUrl = 'https://api.twilio.com',
    ) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function send(string $phoneE164, string $message): void
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->acceptJson()
                ->timeout(10)
                ->post("{$this->baseUrl}/2010-04-01/Accounts/{$this->accountSid}/Messages.json", $this->fields($phoneE164, $message));
        } catch (ConnectionException $e) {
            Log::warning('sms.twilio.unreachable', ['to' => Redact::phone($phoneE164), 'error' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::warning('sms.twilio.failed', ['to' => Redact::phone($phoneE164), 'error' => $e->getMessage()]);

            return;
        }

        if ($response->successful()) {
            Log::info('sms.twilio.sent', [
                'to' => Redact::phone($phoneE164),
                'sid' => $response->json('sid'),
                'status' => $response->json('status'),
                'segments' => $response->json('num_segments'),
            ]);

            return;
        }

        // A number Twilio cannot deliver to — invalid (21211), a landline (21614), opted out
        // (21610) — is a fact about the recipient; everything else (bad credentials, an unverified
        // sender, a queue full) is ours to read, with Twilio's own message.
        $code = (int) $response->json('code', 0);
        Log::log(in_array($code, [21211, 21614, 21610], true) ? 'info' : 'warning', 'sms.twilio.rejected', [
            'to' => Redact::phone($phoneE164),
            'status' => $response->status(),
            'code' => $code,
            'message' => $response->json('message'),
        ]);
    }

    /**
     * The form Twilio reads. Public so the test can pin it without a fake server.
     *
     * @return array<string, string>
     */
    public function fields(string $phoneE164, string $message): array
    {
        $fields = ['To' => $phoneE164, 'Body' => $message];
        $fields[str_starts_with($this->from, 'MG') ? 'MessagingServiceSid' : 'From'] = $this->from;

        return $fields;
    }
}
