<?php

declare(strict_types=1);

use App\Events\OutboxMessagePublished;
use App\Models\OutboxMessage;
use App\Support\Outbox;
use App\Support\OutboxRelay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * P0-07 acceptance: a rolled-back transaction publishes nothing; the relay drains committed
 * messages exactly once and fans them out.
 */
it('publishes nothing when the surrounding transaction rolls back', function () {
    try {
        DB::transaction(function () {
            app(Outbox::class)->publish('engagement.created', ['engagement_id' => 1]);

            throw new RuntimeException('boom — roll it back');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(OutboxMessage::count())->toBe(0);

    // And the relay has nothing to publish.
    Event::fake();
    $published = app(OutboxRelay::class)->drain();

    expect($published)->toBe(0);
    Event::assertNotDispatched(OutboxMessagePublished::class);
});

it('persists the message when the transaction commits', function () {
    DB::transaction(function () {
        app(Outbox::class)->publish('engagement.created', ['engagement_id' => 42]);
    });

    expect(OutboxMessage::pending()->count())->toBe(1);
});

it('relays committed messages exactly once and marks them processed', function () {
    Event::fake();

    app(Outbox::class)->publish('ledger.entry.posted', ['transaction_id' => 7]);
    app(Outbox::class)->publish('engagement.created', ['engagement_id' => 42]);

    $published = app(OutboxRelay::class)->drain();
    expect($published)->toBe(2);

    Event::assertDispatchedTimes(OutboxMessagePublished::class, 2);
    Event::assertDispatched(OutboxMessagePublished::class,
        fn (OutboxMessagePublished $e) => $e->type === 'ledger.entry.posted'
            && $e->payload['transaction_id'] === 7
    );

    // A second drain finds nothing — messages are not re-published.
    expect(app(OutboxRelay::class)->drain())->toBe(0);
    expect(OutboxMessage::pending()->count())->toBe(0);
    expect(OutboxMessage::whereNotNull('processed_at')->count())->toBe(2);
});

it('does not relay a message scheduled for the future', function () {
    app(Outbox::class)->publish(
        'reminder.due',
        ['x' => 1],
        availableAt: now()->addHour(),
    );

    expect(app(OutboxRelay::class)->drain())->toBe(0);
    expect(OutboxMessage::pending()->count())->toBe(1);
});

it('drains a single batch via the console command', function () {
    Event::fake();

    app(Outbox::class)->publish('a.b', ['n' => 1]);

    $this->artisan('outbox:relay', ['--once' => true])
        ->assertSuccessful();

    expect(OutboxMessage::pending()->count())->toBe(0);
});
