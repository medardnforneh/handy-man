<?php

declare(strict_types=1);

namespace App\Domain\Money\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * CinetPay adapter (doc 03) — MoMo + Orange in one API. Collections are initiated against
 * /v2/payment and the authoritative status is read from /v2/payment/check; the notify callback only
 * tells us *something changed*, so we always re-check rather than trust the callback body (doc 03).
 *
 * Webhook authenticity uses CinetPay's HMAC-SHA256 token: the merchant recomputes an HMAC over the
 * documented ordered concatenation of the POSTed fields with the secret key and compares it, in
 * constant time, to the `x-token` header. Confirm the exact field list against a live sandbox
 * callback before go-live; it is isolated here so nothing else in the app depends on it.
 */
final class CinetPayGateway implements PaymentGateway
{
    /** The POST fields CinetPay concatenates (in this order) to build the x-token HMAC. */
    private const TOKEN_FIELDS = [
        'cpm_site_id', 'cpm_trans_id', 'cpm_trans_date', 'cpm_amount', 'cpm_currency', 'signature',
        'payment_method', 'cel_phone_num', 'cpm_phone_prefixe', 'cpm_language', 'cpm_version',
        'cpm_payment_config', 'cpm_page_action', 'cpm_custom', 'cpm_designation', 'cpm_error_message',
    ];

    public function __construct(
        private readonly string $apikey,
        private readonly string $siteId,
        private readonly string $secretKey,
        private readonly string $baseUrl,
        private readonly string $notifyUrl,
        private readonly string $returnUrl,
    ) {}

    public function name(): string
    {
        return 'cinetpay';
    }

    public function requestCollection(CollectionRequest $request): GatewayResult
    {
        $response = Http::acceptJson()->post("{$this->baseUrl}/v2/payment", [
            'apikey' => $this->apikey,
            'site_id' => $this->siteId,
            'transaction_id' => $request->reference,
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
            'description' => $request->description,
            'customer_phone_number' => $request->msisdn,
            'channels' => 'MOBILE_MONEY',
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
        ]);

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();

        // CinetPay returns code '201' when the payment is created.
        if (($data['code'] ?? null) === '201') {
            $inner = (array) ($data['data'] ?? []);

            return new GatewayResult(
                GatewayStatus::Pending,
                $request->reference,
                $data,
                isset($inner['payment_url']) ? (string) $inner['payment_url'] : null,
            );
        }

        return new GatewayResult(
            GatewayStatus::Failed,
            $request->reference,
            $data,
            null,
            isset($data['code']) ? (string) $data['code'] : 'error',
        );
    }

    public function requestPayout(PayoutRequest $request): GatewayResult
    {
        // CinetPay transfers use a separate money-out API + auth token; wired with live credentials.
        $response = Http::acceptJson()->post("{$this->baseUrl}/v1/transfer/money/send/contact", [
            'apikey' => $this->apikey,
            'site_id' => $this->siteId,
            'client_transaction_id' => $request->reference,
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
            'phone' => $request->msisdn,
        ]);

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();
        $ok = ($data['code'] ?? null) === 0 || ($data['code'] ?? null) === '0';

        return new GatewayResult(
            $ok ? GatewayStatus::Pending : GatewayStatus::Failed,
            $request->reference,
            $data,
            null,
            $ok ? null : (string) ($data['code'] ?? 'error'),
        );
    }

    public function fetchStatus(string $externalRef): GatewayResult
    {
        $response = Http::acceptJson()->post("{$this->baseUrl}/v2/payment/check", [
            'apikey' => $this->apikey,
            'site_id' => $this->siteId,
            'transaction_id' => $externalRef,
        ]);

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();
        $inner = (array) ($data['data'] ?? []);
        $status = $this->mapStatus(isset($inner['status']) ? (string) $inner['status'] : (string) ($data['code'] ?? ''));

        return new GatewayResult($status, $externalRef, $data);
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = (string) $request->header('x-token', '');
        if ($token === '') {
            return false;
        }

        $payload = '';
        foreach (self::TOKEN_FIELDS as $field) {
            $payload .= (string) $request->input($field, '');
        }

        $expected = hash_hmac('sha256', $payload, $this->secretKey);

        return hash_equals($expected, $token);
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $ref = (string) $request->input('cpm_trans_id', '');

        // The callback is a trigger, not the truth: report Pending and let the handler fetchStatus.
        return new GatewayEvent($ref, 'cinetpay.notification', GatewayStatus::Pending, (array) $request->all());
    }

    private function mapStatus(string $raw): GatewayStatus
    {
        return match (strtoupper($raw)) {
            'ACCEPTED', '00' => GatewayStatus::Succeeded,
            'REFUSED' => GatewayStatus::Failed,
            'EXPIRED' => GatewayStatus::Expired,
            default => GatewayStatus::Pending, // PENDING, WAITING_FOR_CUSTOMER, CREATED, …
        };
    }
}
