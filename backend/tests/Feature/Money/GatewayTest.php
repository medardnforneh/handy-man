<?php

declare(strict_types=1);

use App\Domain\Money\Gateways\CinetPayGateway;
use App\Domain\Money\Gateways\CollectionRequest;
use App\Domain\Money\Gateways\FakeGateway;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * P3-03: the PaymentGateway abstraction, its config-driven selection, an in-memory Fake that drives
 * the whole money flow deterministically, and the CinetPay adapter's request building, status
 * mapping and HMAC webhook verification.
 */
it('resolves the Fake gateway by default', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);
});

it('resolves CinetPay when configured', function () {
    config(['payments.gateway' => 'cinetpay']);
    $this->app->forgetInstance(PaymentGateway::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(CinetPayGateway::class);
});

it('drives a collection pending → settled through the Fake gateway', function () {
    $fake = new FakeGateway;

    $result = $fake->requestCollection(new CollectionRequest('R1', 5000, 'XAF', '+237650000000', 'Job'));
    expect($result->status)->toBe(GatewayStatus::Pending)
        ->and($result->externalRef)->toBe('R1')
        ->and($fake->fetchStatus('R1')->status)->toBe(GatewayStatus::Pending);

    $fake->settle('R1', GatewayStatus::Succeeded);
    expect($fake->fetchStatus('R1')->status)->toBe(GatewayStatus::Succeeded);
});

it('verifies a Fake webhook by its signature header', function () {
    $fake = new FakeGateway;

    $signed = Request::create('/webhook', 'POST', ['reference' => 'R1', 'status' => 'succeeded'], server: ['HTTP_X_FAKE_SIGNATURE' => 'valid']);
    $unsigned = Request::create('/webhook', 'POST', ['reference' => 'R1', 'status' => 'succeeded']);

    expect($fake->verifyWebhook($signed))->toBeTrue()
        ->and($fake->verifyWebhook($unsigned))->toBeFalse()
        ->and($fake->parseWebhook($signed)->externalRef)->toBe('R1')
        ->and($fake->parseWebhook($signed)->status)->toBe(GatewayStatus::Succeeded);
});

it('builds a CinetPay collection request and maps code 201 → pending', function () {
    Http::fake([
        '*/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_token' => 'tok', 'payment_url' => 'https://pay.cinetpay/x']]),
    ]);
    $gw = new CinetPayGateway('key', 'site', 'secret', 'https://api.test', 'https://notify', 'https://return');

    $result = $gw->requestCollection(new CollectionRequest('TX1', 1000, 'XAF', '+237650000000', 'Job'));

    expect($result->status)->toBe(GatewayStatus::Pending)
        ->and($result->externalRef)->toBe('TX1')
        ->and($result->paymentUrl)->toBe('https://pay.cinetpay/x');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/payment')
        && $request['transaction_id'] === 'TX1'
        && $request['amount'] === 1000
        && $request['channels'] === 'MOBILE_MONEY');
});

it('maps CinetPay check statuses to the normalised status', function () {
    $gw = new CinetPayGateway('key', 'site', 'secret', 'https://api.test', 'https://notify', 'https://return');

    Http::fake(['*/v2/payment/check' => Http::sequence()
        ->push(['code' => '00', 'data' => ['status' => 'ACCEPTED']])
        ->push(['code' => '600', 'data' => ['status' => 'REFUSED']])
        ->push(['code' => '00', 'data' => ['status' => 'WAITING_FOR_CUSTOMER']])]);

    expect($gw->fetchStatus('TX1')->status)->toBe(GatewayStatus::Succeeded)
        ->and($gw->fetchStatus('TX1')->status)->toBe(GatewayStatus::Failed)
        ->and($gw->fetchStatus('TX1')->status)->toBe(GatewayStatus::Pending);
});

it('verifies a CinetPay webhook via its HMAC x-token and rejects a tampered one', function () {
    $secret = 'secret123';
    $gw = new CinetPayGateway('key', 'site', $secret, 'https://api.test', 'https://notify', 'https://return');

    // Fields in the exact order CinetPay concatenates them for the token.
    $fields = [
        'cpm_site_id' => 'site', 'cpm_trans_id' => 'TX1', 'cpm_trans_date' => '2026-07-19',
        'cpm_amount' => '1000', 'cpm_currency' => 'XAF', 'signature' => 'sig',
        'payment_method' => 'MOMO', 'cel_phone_num' => '650000000', 'cpm_phone_prefixe' => '237',
        'cpm_language' => 'fr', 'cpm_version' => 'V4', 'cpm_payment_config' => 'SINGLE',
        'cpm_page_action' => 'PAYMENT', 'cpm_custom' => '', 'cpm_designation' => 'Job', 'cpm_error_message' => '',
    ];
    $payload = implode('', array_values($fields));
    $token = hash_hmac('sha256', $payload, $secret);

    $good = Request::create('/webhook', 'POST', $fields, server: ['HTTP_X_TOKEN' => $token]);
    $bad = Request::create('/webhook', 'POST', $fields, server: ['HTTP_X_TOKEN' => 'deadbeef']);
    $unsigned = Request::create('/webhook', 'POST', $fields);

    expect($gw->verifyWebhook($good))->toBeTrue()
        ->and($gw->verifyWebhook($bad))->toBeFalse()
        ->and($gw->verifyWebhook($unsigned))->toBeFalse()
        ->and($gw->parseWebhook($good)->externalRef)->toBe('TX1');
});
