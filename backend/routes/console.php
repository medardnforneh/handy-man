<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * The schedule — everything the product does when nobody is tapping a screen.
 *
 * Twelve commands existed and NONE was scheduled: found while writing the production stack, where
 * the question "what runs the scheduler" had no answer because nothing had ever been given to it.
 * Locally every one of them was invoked by hand or by a test, so the gap was invisible. In
 * production it would have meant no follow-up ever sent, no offer ever expired, no stuck payment
 * ever resolved, no review ever revealed — a product that only moves while someone is looking at it.
 *
 * Two things are NOT here because they are daemons, not ticks: `outbox:relay` (the transactional
 * outbox worker — every push, WhatsApp, deposit capture and broadcast rides it) and `horizon`
 * (the Redis queue). Both run as long-lived processes under a supervisor (deploy/compose.yml).
 *
 * `withoutOverlapping` on anything that polls a gateway or walks a table: a slow tick must never
 * be lapped by the next. `onOneServer` so a second app container does not double-send.
 */

// --- minute-grained: things a person is waiting on ---
Schedule::command('follow-ups:dispatch')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('offers:expire')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('dispatch:cascade')->everyMinute()->withoutOverlapping()->onOneServer();

// --- money: poll the gateway for what the webhook may have missed (doc 03: the callback is a
//     trigger, not the truth) ---
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('payouts:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// --- safety: a worker who should have arrived and has not ---
Schedule::command('safety:check-in-watchdog')->everyTenMinutes()->withoutOverlapping()->onOneServer();

// --- windows that close: reviews' double-blind period, the customer's review window on a deliverable ---
Schedule::command('reviews:reveal')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('deliverables:auto-approve')->hourly()->withoutOverlapping()->onOneServer();

// --- nightly: resolve what is stuck, compare the ledger to the world, and rebuild the balance
//     cache from the entries (the entries are the truth; the cache is a convenience) ---
Schedule::command('reconcile:nightly')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('ledger:rebuild-balances')->dailyAt('02:30')->withoutOverlapping()->onOneServer();

// --- the retention schedule (doc 04, config/retention.php): personal data past its purpose ---
Schedule::command('data:retain')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

// --- housekeeping the framework provides ---
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
