<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler heartbeat and single-scheduler claim (README-RUNTIME §265)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {
    cache()->put('goaiez:scheduler:heartbeat', now()->timestamp, 300);
    $minute = now()->format('YmdHi');
    $claimed = cache()->add("goaiez:scheduler:tick:{$minute}", true, 120);
    if (! $claimed) {
        cache()->increment('goaiez:scheduler:claims_this_minute');
    }
})->everyMinute()->name('scheduler-heartbeat');

/*
|--------------------------------------------------------------------------
| The platform schedule
|--------------------------------------------------------------------------
|
| Registered 2026-09-01. Until then this file carried only the heartbeat and
| `numbers:return-parked`, so dunning, credit resets, campaign sends, review
| reminders, deletions, token refresh and every prune never ran while the
| scheduler heartbeat stayed green.
|
| Every entry, cadence and overlap window below is taken verbatim from the
| sibling deployment's routes/console.php (goaiez-review-system, decisions
| 6960–6979), whose commands this application carries one for one.
|
| THE RULE ON EVERY WINDOW: `withoutOverlapping()` with no argument is a
| 1,440-minute lock, so a run that dies without releasing its mutex blocks its
| successors for a day. No window below outlives four runs of the entry it
| guards, and none reaches 1,440. The authority for each number is
| tests/Feature/Architecture/SchedulingTest.php, which asserts every entry
| against the live Schedule object and fails on one it has not been told about.
|
| Foreground entries (no runInBackground) are the ones whose exit code the
| scheduler itself must see: deletions, grant revocation, the heartbeat, the
| canary watch and the complaint-rate watch.
*/

// ---- Every minute / every five minutes: liveness and watches ----------------

Schedule::command('ops:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(4);

Schedule::command('ops:watch-platform-health')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('pixel:watch-canary')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('support:poll-mailbox')
    ->everyFiveMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

// ---- Every fifteen minutes: sweeps that enumerate and dispatch -------------

Schedule::command('oauth:refresh-tokens')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('reviews:reanalyse')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('reviews:reinvite')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('reviews:remind')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('agent:nudge')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('gbp:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('reviews:retry-stranded-replies')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('campaigns:run-due')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('messaging:watch-platform-complaint-rate')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

// ---- Hourly ----------------------------------------------------------------

Schedule::command('numbers:health-rollup')
    ->hourly()
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('billing:reconcile-purchases')
    ->hourly()
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('disputes:check-deadlines')
    ->hourly()
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('content:release-holds')
    ->hourly()
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('proof:recompute')
    ->hourlyAt(7)
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('billing:advance-dunning')
    ->hourlyAt(15)
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('campaigns:reconcile-unknown')
    ->hourlyAt(23)
    ->withoutOverlapping(120)
    ->runInBackground();

// ---- Nightly, in clock order (UTC) ----------------------------------------

Schedule::command('credits:reset-monthly')
    ->dailyAt('02:40')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('tenants:execute-deletions')
    ->dailyAt('03:00')
    ->withoutOverlapping(180);

Schedule::command('credits:run-auto-top-ups')
    ->dailyAt('03:00')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('gbp:revoke-owed-grants')
    ->dailyAt('03:05')
    ->withoutOverlapping(180);

Schedule::command('zernio:meter')
    ->dailyAt('03:10')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('mail:prune-send-meter')
    ->dailyAt('03:15')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('audits:prune')
    ->dailyAt('03:20')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('numbers:return-parked')
    ->dailyAt('03:20')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('auth:prune-magic-links')
    ->dailyAt('03:25')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('numbers:recover-rested')
    ->dailyAt('03:25')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('jobs:prune-failed')
    ->dailyAt('03:30')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('trials:prune-origins')
    ->dailyAt('03:35')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('exports:prune')
    ->dailyAt('03:40')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('messaging:prune-sending-health')
    ->dailyAt('03:45')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('storage:prune')
    ->dailyAt('03:50')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('pixel:prune-rejects')
    ->dailyAt('03:55')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('gsc:sync')
    ->dailyAt('04:00')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('zernio:reconcile')
    ->dailyAt('04:05')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('warehouse:prune')
    ->dailyAt('04:10')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('auth:prune-failed-sign-ins')
    ->dailyAt('04:15')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('automation:prune-runs')
    ->dailyAt('04:20')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('fetch:prune-attempts')
    ->dailyAt('04:25')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('visibility:sync-competitors')
    ->dailyAt('04:30')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('review-loss:prune-snapshots')
    ->dailyAt('04:35')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('owner-channel:prune')
    ->dailyAt('04:40')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('content:self-audit')
    ->dailyAt('04:45')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('trust:advance-first-week-paths')
    ->dailyAt('05:00')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('actuation:measure-site-changes')
    ->dailyAt('05:15')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('actuation:judge-speed-fixes')
    ->dailyAt('05:30')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('billing:send-renewal-reminders')
    ->dailyAt('05:30')
    ->withoutOverlapping(360)
    ->runInBackground();

Schedule::command('billing:send-trial-reminders')
    ->dailyAt('05:45')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command('owners:send-weekly-digest')
    ->dailyAt('06:00')
    ->withoutOverlapping(360)
    ->runInBackground();
