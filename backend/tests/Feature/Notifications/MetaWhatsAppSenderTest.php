<?php

declare(strict_types=1);

use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpDelivery;
use App\Domain\Notifications\MetaWhatsAppSender;
use App\Domain\Notifications\WhatsAppSender;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp through Meta's Cloud API (P7-05, doc 07): one generic approved template per language,
 * the copy as parameters, the follow-up id as the URL button's suffix. Best effort by contract —
 * nothing here may throw at the delivery layer.
 */
function metaSender(array $templates = []): MetaWhatsAppSender
{
    return new MetaWhatsAppSender(
        accessToken: 'tok',
        phoneNumberId: '1234567890',
        defaultTemplate: 'handyman_follow_up',
        templates: $templates,
        languages: ['fr' => 'fr', 'en' => 'en'],
        deepLinkBase: 'https://app.handyman.cm/follow-up',
        baseUrl: 'https://graph.test',
        apiVersion: 'v21.0',
    );
}

it('sends the generic template with the copy as parameters and the follow-up id on the button', function () {
    Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    metaSender()->send('+237699000111', 'review_request', ['Comment ça s’est passé ?', 'Dites-nous en deux mots.'], 'fr', 'https://app.handyman.cm/follow-up/01a0-fu');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://graph.test/v21.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer tok')
            && $body['messaging_product'] === 'whatsapp'
            && $body['to'] === '237699000111' // digits only, no plus
            && $body['type'] === 'template'
            && $body['template']['name'] === 'handyman_follow_up'
            && $body['template']['language']['code'] === 'fr'
            && $body['template']['components'][0] === ['type' => 'body', 'parameters' => [
                ['type' => 'text', 'text' => 'Comment ça s’est passé ?'],
                ['type' => 'text', 'text' => 'Dites-nous en deux mots.'],
            ]]
            && $body['template']['components'][1] === ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [
                ['type' => 'text', 'text' => '01a0-fu'],
            ]];
    });
});

it('uses a kind\'s own template when one has been approved for it, and English for an unknown locale', function () {
    $payload = metaSender(['review_request' => 'handyman_review_request'])
        ->payload('+237677000222', 'review_request', ['How did it go?', 'Two words will do.'], 'de', null);

    expect($payload['template']['name'])->toBe('handyman_review_request')
        ->and($payload['template']['language']['code'])->toBe('en')
        ->and($payload['template']['components'])->toHaveCount(1); // no deep link → no button component
});

it('flattens the copy into what Meta accepts as a parameter — one line, single-spaced, bounded', function () {
    $payload = metaSender()->payload('+237677000333', 'maintenance_due', ["Votre  climatiseur\n\tdemande un entretien", str_repeat('x', 2000)], 'fr', null);
    $params = $payload['template']['components'][0]['parameters'];

    expect($params[0]['text'])->toBe('Votre climatiseur demande un entretien')
        ->and(mb_strlen($params[1]['text']))->toBe(1024)
        ->and(str_ends_with($params[1]['text'], '…'))->toBeTrue();
});

it('never throws — Meta down, Meta refusing, a number not on WhatsApp are all logged and dropped', function () {
    Log::spy();
    Http::fake([
        'graph.test/*/1/messages' => fn () => throw new ConnectionException('timed out'),
        'graph.test/*/2/messages' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']], 401),
        'graph.test/*/3/messages' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400),
    ]);

    foreach (['1', '2', '3'] as $id) {
        $sender = new MetaWhatsAppSender('tok', $id, 'handyman_follow_up', [], ['en' => 'en'], 'https://x/follow-up', 'https://graph.test');
        $sender->send('+237677000444', 'review_request', ['a', 'b'], 'en', null);
    }

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m): bool => $m === 'whatsapp.meta.unreachable')->once();
    // A bad token is ours to fix (warning); a number not on WhatsApp is a fact about the recipient (info).
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $m, array $ctx): bool => $level === 'warning' && $m === 'whatsapp.meta.rejected' && $ctx['code'] === 190)->once();
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $m, array $ctx): bool => $level === 'info' && $m === 'whatsapp.meta.rejected' && $ctx['code'] === 131026)->once();
    Http::assertSentCount(2); // the timed-out call never became a request
});

it('is what the follow-up delivery layer sends through when the driver is meta', function () {
    config()->set('notifications.whatsapp', 'meta');
    config()->set('notifications.whatsapp_meta.access_token', 'tok');
    config()->set('notifications.whatsapp_meta.phone_number_id', '1234567890');
    config()->set('notifications.whatsapp_meta.base_url', 'https://graph.test');
    // The app on its own host, as in production — the button must still carry the id.
    config()->set('notifications.follow_up_link_base', 'https://app.handyman.test/follow-up');
    app()->forgetInstance(WhatsAppSender::class);
    Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.2']]])]);

    $user = User::factory()->create(['comms_locale' => 'fr', 'phone_e164' => '+237699000555']);
    $followUp = FollowUp::factory()->create(['target_user_id' => $user->id, 'target_party_id' => $user->party_id, 'channel' => FollowUpChannel::WhatsApp->value]);

    app(FollowUpDelivery::class)->deliver($followUp, $user);

    Http::assertSent(fn (Request $r): bool => $r->data()['to'] === '237699000555'
        && $r->data()['template']['language']['code'] === 'fr'
        && $r->data()['template']['components'][1]['parameters'][0]['text'] === $followUp->id);
});
