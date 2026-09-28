<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\L0Archive;
use App\Enums\AlertOrigin;
use App\Enums\DataClassification;
use App\Enums\OperatorAlertKind;
use App\Services\Ops\OperatorAlerts;
use App\Services\Warehouse\L0Batch;
use App\Services\Warehouse\L0Receipt;
use App\Services\Warehouse\L1Derivation;
use App\Services\Warehouse\L1Loader;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 10 — *"Write L0 → enqueue → batch to L1."*
 *
 * ⛔ **THIS IS THE ONLY THING IN `app/` THAT WRITES L0**, and until it landed
 * nothing did (decision 4861). Read [[\App\Contracts\L0Archive]] first: the
 * archive is append-only — for ever rather than for §5.2's seven years, because
 * nothing in this application expires L0 at all (7706) — and refuses an
 * overwrite, so a byte written wrongly is wrong for as long as the object
 * lives.
 *
 * ---------------------------------------------------------------------------
 * WHY THE ARCHIVE WRITE IS QUEUED WHEN §11 PUTS IT IN THE REQUEST
 * ---------------------------------------------------------------------------
 * §11's own order is *"Write L0 → enqueue"*, which reads as an in-request write.
 * That order is written for the architecture §3 specifies — a Cloudflare Worker
 * at the edge, where an R2 `PUT` is a local call. `CLAUDE.md` collapsed that into
 * *"ingest, aggregation and reports run in Laravel + Postgres + Horizon"*, so
 * here the same write is an internet round trip from a cPanel box, and §11's
 * harder constraint — *"always 204, always <50 ms"* — is the one that survives
 * the move. **The specification's constraint is kept and its step order is not**,
 * which is the trade named out loud rather than silently made.
 *
 * ⚠️ **WHAT THAT COSTS: A BATCH CAN BE ACKNOWLEDGED AND THEN FAIL TO ARCHIVE.**
 * The `204` says *received*, not *durable*. The queue's own retries are what
 * close most of that gap and the failed-job table is where the rest lands; there
 * is no acknowledgement path back to a pixel that has already forgotten the
 * request, and inventing one would mean the visitor's browser retrying on
 * somebody else's website.
 *
 * ⛔ **AND THAT SENTENCE DESCRIBED THE COMMON CASE RATHER THAN THE RARE ONE FOR
 * A WEEK, ON EVERY DEPLOYMENT — 2026-08-25 (9421).** `Storage::disk('s3')`
 * could not be built at all until the driver was installed today (9408), so
 * **every** accepted beacon spent three attempts and landed in `failed_jobs`
 * from the day the collector shipped (2026-08-18). ⚠️ **The only alerting
 * reader of that table is a spike counter with a threshold of 25** (9370–9375),
 * which is a count of *traffic*: an installation with light pixel traffic
 * produced a handful of rows and rang nothing, and one with heavy traffic rang
 * a bell about "failed jobs" rather than about the archive.
 *
 * ⛔ **AND WHAT IS SITTING IN THOSE ROWS IS THIS JOB'S CONSTRUCTOR ARGUMENTS.**
 * `$payload` is the beacon as received and `$ipHash` rides beside it, in a
 * table `CLAUDE.md`'s Schema row exempts from row-level security and which no
 * erasure or crypto-shred reaches. Nothing about that is new today and it is
 * **worse in the past than in the future**: from now on the write can succeed.
 * ⛔ **Whether to purge the accumulated rows is not derivable from here** — it
 * is a fact about each running install — and this class may not answer it.
 *
 * ⛔ **"THERE IS STILL NO `failed()` HOOK AND NO BELL" WAS TRUE AND IS NOT —
 * CORRECTED 2026-08-26 (9840). BOTH READINGS KEPT AND DATED** (4368). That
 * sentence continued *"the right instrument is an `OperatorAlertKind` case,
 * which 7023's lint requires be minted with its raiser in one slice, and that
 * enum is another lane's this wave. A `failed()` that only logged would be a
 * claim with no reader."* **Every clause of it was right and the whole of it is
 * now built**: {@see OperatorAlertKind::PixelArchiveFailed} was minted with
 * {@see self::failed()} as its raiser and a row of its own on the alert board,
 * in one slice, exactly as that lint requires.
 *
 * ⛔ **WHAT THE BELL DOES NOT DO, SAID HERE BECAUSE THE PARAGRAPH ABOVE READS
 * AS THOUGH SOMEBODY IS NOW PAGED.** `ops.alert_email` and `ops.alert_sms` both
 * seed empty, and blank is the off switch — so on an install where neither has
 * been set, every ring is a row in `operator_alerts` and a `critical` log line
 * and reaches no person at all. That is the owner's to set; it is not this
 * class's to claim.
 *
 * ⚠️ **AND WHAT IT COSTS FOR AS LONG AS THE JOB IS PENDING**: the payload sits
 * in the queue store, which on this box is Postgres. That is the same data class
 * as the archive it is on its way to — a `Phi` business and any value-shaped
 * field were refused *before* dispatch, by [[\App\Services\Pixel\PixelCollector]]
 * — so nothing reaches the queue that may not reach L0. **That ordering is
 * load-bearing and a gate moved into this job would break it.**
 *
 * ---------------------------------------------------------------------------
 * IDEMPOTENCY
 * ---------------------------------------------------------------------------
 * The batch id is minted by the collector and carried here, never generated in
 * `handle()`. `ObjectStoreL0Archive::store()` refuses an overwrite and names the
 * collision it can actually prevent: *"a re-run of the same batch — which is what
 * a retried queue job does."* So a retry after a partial failure re-derives the
 * same rows from the same object rather than writing a second object holding the
 * same events. ⚠️ **A retry that gets past the archive write therefore throws**,
 * and that is the correct loud failure rather than a silent duplicate: the L1
 * insert below is `insertOrIgnore` on `(business_id, event_id)`, so the
 * derivation half is genuinely idempotent and only the archive half objects.
 *
 * ⛔ **THIS SAID "ON `event_id`" UNTIL 2026-08-20 AND THAT WAS THE WHOLE KEY,
 * WHICH MADE THIS PARAGRAPH TRUE OF ONE TENANT AND FALSE OF THE PLATFORM —
 * BOTH READINGS KEPT AND DATED** (6182, 6240). `event_id` arrives from the
 * browser, so under the old key the conflict `insertOrIgnore` swallowed here
 * could be with **another tenant's** row: this job would archive the batch,
 * report success, and write nothing at all. ⚠️ **The claim above is now true as
 * written** — the conflict can only be a row this tenant already holds.
 */
final class ArchivePixelBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * How long one archive stays quiet after ringing.
     *
     * ⚠️ **DECLARED ON THE CLASS THAT APPLIES IT, ON 7661's RULE** — the alert
     * board prints this figure and reads it off here rather than repeating it,
     * so an operator is told what will actually happen. The argument for six
     * rather than the twenty-four its two siblings use is in
     * {@see self::failed()}.
     */
    public const int ARCHIVE_REPEAT_HOURS = 6;

    /**
     * Three attempts.
     *
     * An R2 timeout is ordinary and worth retrying. A malformed batch is not
     * retryable at all and cannot occur — the collector validated the body before
     * dispatching — so what these attempts buy is the object store being briefly
     * unreachable, which is the failure that actually happens.
     */
    public int $tries = 3;

    /**
     * ⚠️ **JITTERED.** Every beacon from one busy page lands in the same second,
     * so a fixed ladder would have every failed job retry in the same second too
     * — against an object store that has just demonstrated it is struggling.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $base = QueueBackoff::fromSetting('queue.backoff.pixel_archive_seconds');

        return [
            $base[0] + random_int(-10, 10),
            $base[1] + random_int(-30, 30),
        ];
    }

    /**
     * ⚠️ **THE PAYLOAD IS THE RECEIVED BYTES, AND THAT IS THE POINT.** It is
     * carried as the exact string the collector read and is never decoded here —
     * decision 4874. A job that re-encoded it would change key order, float
     * rendering and unicode escaping, and the archive would stop being what
     * arrived.
     *
     * ⚠️ **`$receivedAt` IS THE COLLECTOR'S CLOCK READING, NOT THIS JOB'S**
     * (decision 4862). It decides the L0 partition and every derived row's
     * `received_at`, so reading the clock here would make the same batch derive
     * differently depending on how long the queue was.
     *
     * ⚠️ **THE FOUR ENRICHMENT ARGUMENTS ARE THE COLLECTOR'S
     * `App\Services\Pixel\PixelEnrichment`, ALREADY COMPUTED — decision 5000s.**
     * This job never reads a User-Agent header or an address; by the time it
     * runs, the request that carried them is long gone, which is the strongest
     * possible enforcement of "the collector never touches a raw address" —
     * this file cannot touch one even if it wanted to.
     */
    public function __construct(
        private readonly int $businessId,
        private readonly string $batchId,
        private readonly string $payload,
        private readonly string $receiptId,
        private readonly CarbonImmutable $receivedAt,
        private readonly ?string $ipHash = null,
        private readonly string $browser = 'unknown',
        private readonly ?string $browserVersion = null,
        private readonly string $os = 'unknown',
    ) {}

    public function handle(L0Archive $archive, L1Loader $loader): void
    {
        Tenancy::actingAs($this->businessId, function () use ($archive, $loader): void {
            // ⛔ `DataClassification::Pii` IS NOT A GUESS AND IS NOT READ FROM THE
            // BUSINESS ROW HERE. The collector refuses a `Phi` business outright
            // before anything is dispatched, so a batch that reaches this job is
            // one whose classification was already decided and acted on. Re-reading
            // the column would put a second decider on the hardest boundary in
            // this system — and would let a reclassification *between* dispatch
            // and delivery route bytes the collector never approved.
            //
            // ⚠️ AND IT IS BELT-AND-BRACES RATHER THAN THE BELT: passing `Phi`
            // here would be refused by `ObjectStoreL0Archive::store()` anyway
            // (4863), which is the layer that cannot be talked out of it.
            $batch = new L0Batch(
                batchId: $this->batchId,
                businessId: $this->businessId,
                dataClass: DataClassification::Pii,
                receivedAt: $this->receivedAt,
                receipts: [new L0Receipt($this->receiptId, $this->payload)],
                ipHash: $this->ipHash,
                browser: $this->browser,
                browserVersion: $this->browserVersion,
                os: $this->os,
            );

            $path = $archive->store($batch);

            // §11 row 10's *"batch to L1"*.
            //
            // ⛔ **DERIVED BY READING THE OBJECT BACK RATHER THAN FROM THE
            // IN-MEMORY BATCH**, which costs one `GET` and buys the only thing
            // that matters here: the rows written live are then produced by
            // **exactly** the code path a replay uses — `L0Archive::read()` →
            // `L1Derivation::rows()` → `L1Loader::insert()`. Deriving from the
            // batch instead would be a second derivation that agrees with the
            // first until the day it does not, and the disagreement would show up
            // as a replay that "corrupts" rows nobody had touched.
            $rows = [];

            foreach ($archive->read($path) as $line) {
                foreach (L1Derivation::rows($line, $path) as $row) {
                    $rows[] = $row;
                }
            }

            // ⚠️ AN EMPTY DERIVATION IS NOT AN ERROR. A malformed payload is
            // archivable by design (4874) and becomes a reject downstream; the
            // count of those is `etl_runs.l0_rejected` on a replay. ⚠️ **NOT
            // THE SAME `ingest_rejects` §11 ROW 2 NOW WRITES** (decision 5000s)
            // — that table records a mismatched origin at the collector,
            // *before* a batch is ever dispatched; a malformed payload inside
            // an otherwise-accepted batch is a derivation-time concern and
            // still only a count, not a record (4875).
            $loader->insert($rows);
        });
    }

    /**
     * The queue has stopped trying, and the beacon it was carrying is gone.
     *
     * ## ⛔ Why this hook is not the same report as the failed-job spike
     *
     * ⛔ **`ops.failed_job_spike` COUNTS FAILURES, WHICH IS A COUNT OF TRAFFIC**
     * (9370–9375). Its threshold is twenty-five inside a window, so a tenant
     * whose site sends a handful of beacons an hour can lose **every one of
     * them, for days**, without ever reaching it — which is exactly the shape
     * that left a totally stopped mail transport unread for five days. And on a
     * busy tenant it rings about *"background jobs"*, naming no subsystem.
     * ⛔ **This is the measured production case rather than a hypothetical**:
     * `Storage::disk('s3')` could not be built at all between 2026-08-18 and
     * 2026-08-25 (9408, 9421), so every accepted beacon in that week spent three
     * attempts and landed in `failed_jobs`, and nothing anywhere said so.
     *
     * ⛔ **AND {@see AutopilotJob}'s BELL DOES NOT COVER THIS JOB.**
     * That hook is `final` on the base class every automation extends; this
     * class extends nothing and implements `ShouldQueue` directly, so the day
     * that bell landed it covered the automations and left the pixel archive,
     * the mailbox polls, the dunning ladder and the webhook ingests reporting to
     * `failed_jobs` and the spike counter alone — which the alert board's own
     * `AutomationAbandoned` row says out loud.
     *
     * ## ⚠️ The subject is the disk, and {@see self::ARCHIVE_REPEAT_HOURS} is
     * why that is affordable
     *
     * ⚠️ **PER ARCHIVE, WHICH IS THE THING AN OPERATOR GOES AND FIXES** —
     * `DeliverPlatformMail::failed()`'s reasoning, arriving at object storage.
     * Every plausible cause is one fault about one store: unreachable,
     * unauthenticated, a bucket that does not exist, an endpoint pointing at the
     * wrong company. A business id in the subject would page once per tenant for
     * a single cause, so the account rides in `context` instead — which is
     * `AutomationAbandoned`'s trade, made for the same reason.
     *
     * ⚠️ **SIX HOURS RATHER THAN THE TWENTY-FOUR ITS TWO SIBLINGS USE, AND THE
     * DIFFERENCE IS ARGUED RATHER THAN INHERITED.** `MAIL_PATH_REPEAT_HOURS` and
     * `AutopilotJob::ABANDONED_REPEAT_HOURS` are both a day, over work that is
     * **recoverable**: a platform email can be sent again once the transport is
     * fixed, and an abandoned automation is re-dispatched by its own schedule.
     * ⛔ **Nothing here is recoverable.** The visitor's browser was answered
     * `204` and has forgotten the request; L0 is the only durable copy this
     * architecture keeps; `warehouse:replay` rebuilds from L0 and from nothing
     * else. So a day of silence after one ring is a day of permanent loss, and
     * four rings a day is what that is worth. ⚠️ **It is bounded and the
     * arithmetic is the point**: four is well inside
     * {@see OperatorAlerts::PUSH_BUDGET_PER_KIND}, so choosing it cannot spend
     * the kind's whole allowance and silence it — 511 in the other direction.
     *
     * ## ⛔ What may never travel out of here
     *
     * ⛔ **NOT `$exception->getMessage()`** (9378, and `AutopilotJob::failed()`'s
     * wider version of the same rule). `context` is rendered on the alert board,
     * spread into a `critical` log line, emailed and texted. An object store's
     * message is written by somebody else's server and routinely quotes the
     * bucket, the endpoint, the signed URL and the key — which on this archive
     * is `class=…/business=…/dt=…`, a tenant's identity and their traffic's
     * dates in one string. **The class name carries the diagnosis; the message
     * carries the store's own idea of what to say.**
     *
     * ⛔ **AND NOT `$this->payload` OR `$this->ipHash` IN ANY FORM.** Those are
     * the received bytes and a hashed address — another person's data by
     * `tests/Feature/Architecture/QueuePayloadTest.php`'s own classification.
     * They already sit in `failed_jobs` behind a login, which is the argument for
     * not putting a second copy on a screen and into a text message.
     *
     * ## ⛔ Contained, because a bell may never be a brake (R25)
     *
     * ⛔ **Nothing in here may throw into the worker.** A queue that failed a job
     * and then died reporting it would lose the `failed_jobs` row this method is
     * relying on. {@see OperatorAlerts::raise()} already returns rather than
     * throws; the `catch` covers the container resolution, the config read and
     * the `rangSince()` query in front of it.
     *
     * ⚠️ **`AlertOrigin::Platform` IS STATED RATHER THAN DETECTED**, on
     * `DeliverPlatformMail::failed()`'s reasoning: under `QUEUE_CONNECTION=sync`
     * this runs inside the visitor's own `POST /api/pixel/e`, and
     * `AlertOrigin::detected()` would read a stranger's browser as the ringer.
     */
    public function failed(?Throwable $exception): void
    {
        try {
            $alerts = app(OperatorAlerts::class);
            $disk = (string) config('warehouse.l0_disk');

            if ($alerts->rangSince(
                OperatorAlertKind::PixelArchiveFailed,
                $disk,
                CarbonImmutable::now()->subHours(self::ARCHIVE_REPEAT_HOURS),
            )) {
                return;
            }

            $alerts->raise(
                OperatorAlertKind::PixelArchiveFailed,
                $disk,
                self::archiveSummary($disk, $this->businessId),
                [
                    'disk' => $disk,
                    // ⚠️ **OUR OWN ACCOUNT NUMBER, WHICH IS NOT PERSONAL DATA
                    // AND IS THE ONLY THING HERE AN OPERATOR CAN ACT ON.** It is
                    // in `context` rather than in the subject because the subject
                    // is the dedupe key — see the paragraph above.
                    'business_id' => $this->businessId,
                    // ⚠️ **MINTED BY THE COLLECTOR** (`Str::uuid()`), so it is
                    // ours and it is what joins this bell to the `failed_jobs`
                    // row that still holds the bytes.
                    'batch_id' => $this->batchId,
                    // ⚠️ **THE COLLECTOR'S CLOCK, NOT THIS METHOD'S** (4862). It
                    // is the L0 partition this batch would have landed in, which
                    // is the day an operator would have to replay — and the queue
                    // may have held the job across a UTC midnight.
                    'received_day' => $this->receivedAt->utc()->toDateString(),
                    // ⛔ **THE CLASS, NEVER THE MESSAGE.** See above. `null` where
                    // the queue failed the job without one, which `Job::fail()`
                    // permits.
                    'exception' => $exception === null ? null : $exception::class,
                    'attempts' => $this->tries,
                    'repeat_quiet_hours' => self::ARCHIVE_REPEAT_HOURS,
                ],
                origin: AlertOrigin::Platform,
            );
        } catch (Throwable $e) {
            Log::error('a lost pixel batch could not be reported to the operator', [
                'business_id' => $this->businessId,
                'batch_id' => $this->batchId,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * One sentence, safe in a text message, with the action last.
     *
     * ⚠️ **IT FITS INSIDE {@see OperatorAlerts::SUMMARY_LIMIT} AT REALISTIC
     * WIDTHS AND IS PINNED BY A TEST RATHER THAN BY THIS SENTENCE.** `clamp()`
     * elides the **middle**, so the closing instruction survives even where a
     * long disk name and a long account number do not — which is why the remedy
     * is the last clause and the figures are in `context`.
     */
    private static function archiveSummary(string $disk, int $businessId): string
    {
        return 'Pixel batches are failing permanently on the '.$disk.' archive, so visitor events '
            .'this platform already acknowledged are lost and nothing retries them. First seen on '
            .'account '.$businessId.'; others are not paged separately. Quiet for '
            .self::ARCHIVE_REPEAT_HOURS.'h. Check the warehouse.l0_disk credentials.';
    }
}
