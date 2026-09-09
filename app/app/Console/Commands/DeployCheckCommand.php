<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mail\MailQuota;
use App\Services\Pixel\PixelDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The gate that refuses an unconfigured box — §265.
 *
 * ⛔⛔ IT RETURNS 1. IT IS NOT A WARNING.
 *
 * Every check below exists because the failure it catches is SILENT. A queued job
 * with no worker looks accepted. `MAIL_MAILER=log` returns success and delivers
 * nothing. An unseeded send ceiling refuses every message including sign-in links.
 * None of these throws; all of them look fine; the customer finds out.
 *
 * ⚠️ THE ONE THAT MATTERS MOST IS THE WORKER. `QUEUE_CONNECTION=database` and every
 * job `implements ShouldQueue`, so with no worker running a missed call produces a
 * 200 from the webhook, a row in the jobs table, and no text ever. The sixty-second
 * promise fails on every call and nothing anywhere reports an error.
 */
final class DeployCheckCommand extends Command
{
    protected $signature = 'app:deploy-check {--json}';

    protected $description = 'Refuse to call this box deployed until it can actually run.';

    /** @var list<array{name:string, ok:bool, detail:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->workerIsRunning();
        $this->schedulerIsRunning();
        $this->exactlyOneScheduler();
        $this->mailerIsNotLog();
        $this->sendCeilingIsSeeded();
        $this->credentialsArePresent();
        $this->pixelBundleIsPublished();
        $this->numberStockExists();
        $this->queueDriverIsNotSync();

        $failed = array_values(array_filter($this->results, static fn (array $r): bool => ! $r['ok']));

        if ($this->option('json')) {
            $this->line((string) json_encode($this->results, JSON_PRETTY_PRINT));
        } else {
            foreach ($this->results as $r) {
                $this->line(($r['ok'] ? '  ok   ' : '  FAIL ').$r['name'].'  —  '.$r['detail']);
            }
        }

        if ($failed !== []) {
            $this->newLine();
            $this->error(count($failed).' check(s) failed. THIS BOX IS NOT DEPLOYED.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All checks passed. The box can run.');

        return self::SUCCESS;
    }

    private function record(string $name, bool $ok, string $detail): void
    {
        $this->results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    }

    /**
     * ⭐⭐⭐ THE HEARTBEAT MUST RING AT ZERO.
     *
     * "The queue is empty" and "nothing is reading the queue" must not produce the
     * same silence. A worker that has not checked in within one window is DOWN,
     * regardless of how few jobs are waiting — an idle queue is the state in which
     * a dead worker looks healthiest.
     */
    private function workerIsRunning(): void
    {
        $last = cache()->get('goaiez:worker:heartbeat');
        if (is_numeric($last)) {
            $last = Carbon::createFromTimestamp($last);
        }

        if ($last === null) {
            $this->record('worker running', false, 'NO WORKER HEARTBEAT. Queued jobs are accepted and never run — including the missed-call text-back.');

            return;
        }

        if (! $last instanceof \DateTimeInterface) {
            $this->record('worker running', false, 'heartbeat unreadable ('.get_debug_type($last).')');

            return;
        }

        $last = Carbon::instance($last);
        $age = (int) $last->diffInSeconds(now(), absolute: true);
        $ok = $age < 120;

        $this->record(
            'worker running',
            $ok,
            $ok
                ? "last heartbeat {$age}s ago"
                : 'NO WORKER HEARTBEAT. Queued jobs are accepted and never run — including the missed-call text-back.'
        );
    }

    private function schedulerIsRunning(): void
    {
        $last = cache()->get('goaiez:scheduler:heartbeat');
        if (is_numeric($last)) {
            $last = Carbon::createFromTimestamp($last);
        }

        if ($last === null) {
            $this->record('scheduler running', false, 'NO SCHEDULER TICK. 29 scheduled tasks are not firing — token refresh, review reminders, deletions, parked-number release.');

            return;
        }

        if (! $last instanceof \DateTimeInterface) {
            $this->record('scheduler running', false, 'heartbeat unreadable ('.get_debug_type($last).')');

            return;
        }

        $last = Carbon::instance($last);
        $age = (int) $last->diffInSeconds(now(), absolute: true);
        $ok = $age < 120;

        $this->record(
            'scheduler running',
            $ok,
            $ok
                ? "last tick {$age}s ago"
                : 'NO SCHEDULER TICK. 29 scheduled tasks are not firing — token refresh, review reminders, deletions, parked-number release.'
        );
    }

    /**
     * ⛔⛔ TWO SCHEDULERS DOUBLE-SEND EVERY REMINDER IN THE PRODUCT.
     *
     * The tick is claimed with an atomic add. A second scheduler on the same minute
     * fails to claim it and is therefore visible here rather than in a customer's
     * inbox, twice.
     */
    private function exactlyOneScheduler(): void
    {
        $claims = (int) cache()->get('goaiez:scheduler:claims_this_minute', 0);
        $ok = $claims <= 1;

        $this->record(
            'exactly one scheduler',
            $ok,
            $ok ? 'one claimant' : "{$claims} schedulers claimed the same tick — every reminder will send {$claims}×"
        );
    }

    private function mailerIsNotLog(): void
    {
        $mailer = (string) config('mail.default');
        $ok = $mailer !== 'log' && $mailer !== 'array';

        $this->record(
            'mailer is real',
            $ok,
            $ok ? "mailer={$mailer}" : "mailer={$mailer} — RETURNS SUCCESS AND DELIVERS NOTHING."
        );
    }

    /**
     * The smtp mailer refuses every message until this is seeded — sign-in links
     * included — and the refusal reads like a rate limit rather than a missing seed.
     */
    private function sendCeilingIsSeeded(): void
    {
        $ceiling = app(MailQuota::class)->ceiling();
        $ok = is_int($ceiling) && $ceiling > 0;

        $this->record(
            'send ceiling seeded',
            $ok,
            $ok ? "ceiling={$ceiling}/day" : 'UNSEEDED — the smtp mailer refuses every message, including sign-in links.'
        );
    }

    /**
     * ⛔ P-193 / the vault: a key still in .env after wave 0's gate FAILS THE GATE.
     */
    private function credentialsArePresent(): void
    {
        try {
            $count = (int) DB::table('platform_credentials')->count();
        } catch (\Throwable) {
            $count = 0;
        }

        $ok = $count > 0;
        $this->record(
            'credential vault loaded',
            $ok,
            $ok ? "{$count} credential(s) in X-206" : 'VAULT EMPTY — nothing hardcoded, which is right; nothing loaded either.'
        );
    }

    /**
     * `public/build` is gitignored and `pixel:publish` is deliberately outside
     * `composer deploy`, so a deploy never moves the pixel and /p.js serves nothing.
     */
    private function pixelBundleIsPublished(): void
    {
        try {
            $version = app(PixelDelivery::class)->choose();
            $this->record('pixel bundle published', true, 'ok '.$version->sha);
        } catch (\RuntimeException $e) {
            $this->record('pixel bundle published', false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->record('pixel bundle published', false, get_debug_type($e));
        }
    }

    private function numberStockExists(): void
    {
        try {
            $free = (int) DB::table('phone_numbers')->whereNull('business_id')->count();
        } catch (\Throwable) {
            $free = 0;
        }

        $ok = $free > 0;
        $this->record(
            'number stock',
            $ok,
            $ok ? "{$free} unassigned number(s)" : 'NO NUMBERS — run sms:load-number-pool to register stock, or numbers:return-parked to recycle.'
        );
    }

    /**
     * ⚠️ IN PRODUCTION ONLY. `sync` is correct locally and catastrophic on a box:
     * it moves every job into the web request, so a missed-call text-back would
     * block the carrier webhook and time it out.
     */
    private function queueDriverIsNotSync(): void
    {
        $driver = (string) config('queue.default');
        $ok = ! app()->environment('production') || $driver !== 'sync';

        $this->record(
            'queue driver',
            $ok,
            $ok ? "driver={$driver}" : 'driver=sync IN PRODUCTION — every job runs inside the web request.'
        );
    }
}
