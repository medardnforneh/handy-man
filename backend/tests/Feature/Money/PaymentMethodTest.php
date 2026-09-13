<?php

declare(strict_types=1);

use App\Domain\Money\Gateways\CinetPayGateway;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\PaymentMethod;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The product's payment methods (founder decision 2026-09-13): MTN Mobile Money, Orange Money and
 * cash — nothing else. The two mobile rails are named on every collection and payout; the operator
 * is inferred from the number's prefix and can be overridden by the person, never guessed past
 * them; cash never touches the gateway.
 */
it('names exactly three payment methods, and tells the app so', function () {
    expect(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()))
        ->toBe(['mtn_momo', 'orange_money', 'cash']);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('payment_methods', ['mtn_momo', 'orange_money', 'cash']);
});

it('knows which operator a Cameroon number belongs to', function (string $msisdn, ?PaymentMethod $expected) {
    expect(PaymentMethod::fromMsisdn($msisdn))->toBe($expected);
})->with([
    'MTN 67x' => ['+237677123456', PaymentMethod::MtnMomo],
    'MTN 650' => ['650123456', PaymentMethod::MtnMomo],
    'MTN 68x low' => ['237 683 12 34 56', PaymentMethod::MtnMomo],
    'Orange 69x' => ['+237699123456', PaymentMethod::OrangeMoney],
    'Orange 655' => ['655123456', PaymentMethod::OrangeMoney],
    'Orange 68x high' => ['+237686123456', PaymentMethod::OrangeMoney],
    'unallocated 66x' => ['+237661123456', null],
    'landline' => ['+237222123456', null],
    'too short' => ['+23767', null],
]);

it('records the rail on a collection, inferred from the number when the app did not say', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/payment-intents', [
        'purpose' => 'lead_credits', 'amount_minor' => 5000, 'msisdn' => '+237699000111',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.method', 'orange_money');
});

it('lets the person override the inferred rail — a ported number is theirs to know', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/payment-intents', [
        'purpose' => 'lead_credits', 'amount_minor' => 5000, 'msisdn' => '+237699000222', 'method' => 'mtn_momo',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.method', 'mtn_momo');
});

it('refuses a number no operator claims when the app did not say which rail', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/payment-intents', [
        'purpose' => 'lead_credits', 'amount_minor' => 5000, 'msisdn' => '+237661000333',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('type', fn (string $t): bool => str_ends_with($t, 'unknown-mobile-rail'));

    expect(PaymentIntent::query()->count())->toBe(0);
});

it('never lets cash into a collection', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/payment-intents', [
        'purpose' => 'lead_credits', 'amount_minor' => 5000, 'msisdn' => '+237677000444', 'method' => 'cash',
    ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['method']);
});

it('tells CinetPay which operator to push to', function () {
    Http::fake(['*/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://pay.cinetpay/x']])]);
    $this->app->instance(PaymentGateway::class, new CinetPayGateway('k', 's', 'sec', 'https://api.cinetpay', 'https://n', 'https://r'));

    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/payment-intents', [
        'purpose' => 'lead_credits', 'amount_minor' => 5000, 'msisdn' => '+237677000555',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/payment') && $request['payment_method'] === 'MTNCM');
});
