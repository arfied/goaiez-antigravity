<?php

declare(strict_types=1);

namespace App\Jobs\Sms;

use App\Enums\InboundMediaOutcome;
use App\Jobs\AutopilotJob;
use App\Models\InboundMedia;
use App\Models\InboundMessage;
use App\Services\Sms\InboundMediaCapture;
use App\Services\Sms\InboundMediaFetcher;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Fetch one media part of an inbound MMS and put it in the object store —
 * T176 P10.
 *
 * ⛔ **A JOB RATHER THAN THE WEBHOOK, AND THE REASON IS THE CARRIER'S RETRY.**
 * Fetching a CDN inside `POST /webhooks/infobip/inbound` holds the request open
 * for as long as the CDN takes; Infobip answers a slow or failed webhook by
 * redelivering, and the redelivery is refused by `inbound_messages`' unique
 * `provider_message_id` — so a slow fetch would lose the media *and* cost every
 * other message in the same delivery batch. `IngestGmailPushJob` is the same
 * shape for the same reason: the endpoint acknowledges, the work happens here,
 * and a failure is a failed job somebody can read.
 *
 * ⛔ **THE LAST CLAUSE IS FALSE AND THE REST OF THE PARAGRAPH IS RIGHT —
 * CORRECTED 2026-08-25 (9591, 9592).** **Nobody reads `failed_jobs`** (9370):
 * the only alerting reader in `app/` wants twenty-five rows in an hour, which is
 * a count of traffic rather than of severity, and `jobs:prune-failed` deletes the
 * row at thirty days. ⚠️ **`IngestGmailPushJob` no longer says this about
 * itself** — it was corrected in the same wave — so a reader following that
 * citation now arrives at the opposite claim.
 *
 * ✅ **AND THIS CLASS IS THE ONE THAT DID NOT NEED THE SENTENCE**, which is why
 * only the clause moves. {@see self::failed()} writes
 * {@see InboundMediaOutcome::RefusedUnreachable} onto an `inbound_media` row —
 * durable, tenant-scoped, row-level-secured, reached by an erasure — and **that
 * row is the record, not the failed job**. The paragraph forty lines down already
 * says so; this one was crediting the wrong mechanism beside it.
 * ⚠️ **What that row does not yet have is a screen**: the Inbox is P18 and
 * unbuilt, which is argued where it is checkable rather than here —
 * `ownerNavExcludedRoutes()`' entry for `account.inbound-media.show`, whose own
 * reason is *"it is opened from wherever the message is being read"*. **That is
 * a deferral with a written argument and not a gap this correction discovered.**
 *
 * ⛔ **NOT AN {@see AutopilotJob}, DELIBERATELY.** That base class
 * gates on the tenant pause, the suspension and the automation kill switches
 * before any side effect, which is right for *acting on a tenant's behalf*.
 * Receiving is not an automation: a paused account still gets its customers'
 * messages, and dropping a photograph because autopilot is off would be the
 * platform losing somebody else's content over a setting that was never about
 * them.
 *
 * ## Idempotency, in three layers
 *
 * A redelivered *webhook* never reaches this job — `InboundMessages` returns
 * early when the database refuses the duplicate `provider_message_id`. What this
 * job defends against is its own redelivery, and it does so three ways:
 *
 *   1. the existence check below, which skips the fetch entirely — so a retry
 *      after a lost acknowledgement costs no bandwidth and no carrier request
 *   2. `(business_id, inbound_message_id, ordinal)` is unique, and
 *      {@see InboundMediaCapture} reads the 23505 as "already done" rather than
 *      as an error — the layer that holds when two workers race, which check 1
 *      alone does not
 *   3. ⚠️ **the storage path is DETERMINISTIC**, so a retry that re-fetches
 *      overwrites the same object instead of orphaning the first one. A random
 *      path would leave bytes on the disk that no row names and nothing will
 *      ever delete
 *
 * ⚠️ **DETERMINISTIC IS NOT SECRET, AND THAT IS AN AFFORDABLE TRADE HERE.**
 * `CampaignMedia` uses an unguessable path because its file is served through a
 * signed URL to a carrier; nothing about *this* object is ever served by path.
 * It sits on a private bucket and the only route to it authenticates, resolves
 * the tenant and asks a policy — so the path is not the secret and does not have
 * to be.
 *
 * ## What retries and what does not
 *
 * ⚠️ **THREE OF THE FIVE REFUSALS ARE FINAL THE MOMENT THEY ARE MADE.** An
 * untrusted host, a content type we do not keep and a file over the ceiling will
 * all give the same answer on the third attempt as on the first, so they are
 * written immediately and the job succeeds — a "failed" job for a decision we
 * made correctly is noise in the one place an operator looks for real failures.
 *
 * ⛔ **`RefusedUnreachable` IS THE ONE THAT RETRIES**, because a 502 from a CDN
 * and a timeout are exactly what backoff exists for. It is written from
 * {@see self::failed()} once the attempts are spent, which is also what catches
 * an object store that would not take the write.
 */
final class CaptureInboundMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * ⚠️ **THE SAME DISK THE TENANT EXPORT USES**, which is Cloudflare R2 behind
     * Laravel's `s3` driver (`CLAUDE.md`: zero egress is why R2). It is written
     * onto the row rather than assumed at read time, so a later move between
     * object stores can be told what it has already moved.
     */
    public const string DISK = 's3';

    public int $tries = 3;

    public bool $failOnTimeout = true;

    /**
     * Seconds. Long enough that a CDN having a bad minute has recovered, short
     * enough that an owner still sees the picture the same morning.
     */
    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.media_seconds'));
    }

    public function __construct(
        public readonly int $businessId,
        public readonly int $inboundMessageId,
        public readonly int $ordinal,
        // ⚠️ **THE URL LIVES IN THE QUEUE PAYLOAD AND NOT IN A COLUMN.** 2204
        // established that media travels as a reference rather than as bytes,
        // and this is the inbound half of it: the `jobs` row is deleted when the
        // job completes, while a column would keep an attacker-chosen address
        // for ever in a row something could be tempted to re-fetch.
        //
        // ⛔ **THIS SAID "AND NOWHERE DURABLE" AND THAT WAS NEVER TRUE OF THE
        // FAILING CASE — CORRECTED 2026-08-23 (8725-8735).** `$backoff` gives
        // this job three attempts; the third failure copies the payload into
        // `failed_jobs`, which has no row-level security, no tenant predicate
        // and no crypto-shred, and which nothing pruned at all until
        // `jobs:prune-failed` landed the same week (8610-8639). So the honest
        // reading is **thirty days rather than for ever**, which is still the
        // right side of the trade this comment makes and is not the "nowhere"
        // it claimed. ⚠️ **AND WHAT IS AT THAT ADDRESS IS A PICTURE A MEMBER OF
        // THE PUBLIC SENT A BUSINESS** — `Architecture/QueuePayloadTest`
        // classifies this parameter and `PruneFailedJobs` names the class,
        // because the list that used to name it named neither.
        public readonly string $url,
    ) {}

    public function handle(InboundMediaFetcher $fetcher, InboundMediaCapture $capture): void
    {
        Tenancy::actingAs($this->businessId, function () use ($fetcher, $capture): void {
            $message = InboundMessage::query()->find($this->inboundMessageId);

            if ($message === null) {
                // `inbound_messages` refuses `deleting()`, so this cannot happen
                // short of a database repair. Returning rather than throwing:
                // there is nothing a retry could fix.
                return;
            }

            if ($this->alreadyCaptured()) {
                // Layer 1. A retry after a lost acknowledgement costs no
                // bandwidth and asks the carrier for nothing.
                return;
            }

            $result = $fetcher->fetch($this->url);

            if ($result->outcome === InboundMediaOutcome::RefusedUnreachable) {
                // ⚠️ **THROWN RATHER THAN RECORDED, SO THE QUEUE RETRIES.**
                // Writing the refusal here would make the first transient 502
                // permanent — and layer 1 above would then skip the retry that
                // would have succeeded. The message carries no URL: it is an
                // address a stranger chose, and a failed-job row is exactly the
                // sort of durable place this slice keeps it out of.
                throw new RuntimeException(
                    'Inbound media part '.$this->ordinal.' of message '.$this->inboundMessageId
                    .' could not be fetched. Retrying.'
                );
            }

            if (! $result->outcome->isStored()) {
                // Final by construction — see the class docblock.
                $capture->recordRefusal($message, $this->ordinal, $result->outcome);

                return;
            }

            $bytes = (string) $result->bytes;
            $path = $this->storagePath();

            if (Storage::disk(self::DISK)->put($path, $bytes) === false) {
                // ⚠️ **`throw` ON FALSE RATHER THAN A SILENT SKIP.**
                // `config/filesystems.php` sets `'throw' => false` on this disk,
                // so a failed upload answers false and nothing else — which is
                // how `tenant_exports` once ended up with `ready` rows pointing
                // at objects that were never there. A row written after this
                // would promise a picture that is not on the disk, and the CHECK
                // constraint cannot tell the difference.
                throw new RuntimeException(
                    'Inbound media part '.$this->ordinal.' of message '.$this->inboundMessageId
                    .' could not be written to the object store. Retrying.'
                );
            }

            $capture->recordStored(
                message: $message,
                ordinal: $this->ordinal,
                contentType: (string) $result->contentType,
                byteSize: strlen($bytes),
                checksum: hash('sha256', $bytes),
                storageDisk: self::DISK,
                storagePath: $path,
            );
        });
    }

    /**
     * Every attempt is spent: record that we could not get it here.
     *
     * ⚠️ **THIS IS WHY {@see InboundMediaOutcome::RefusedUnreachable} IS ONE
     * CASE COVERING A FAMILY.** What lands here is a connection that failed, a
     * status that was not 200, a timeout, or an object store that refused the
     * write — and from this method they are genuinely indistinguishable. The
     * exception's class is logged, which is where the distinction lives.
     *
     * ⛔ **AND WITHOUT IT THE WHOLE PATH WOULD BE SILENT ON ITS WORST OUTCOME.**
     * A job that exhausted its retries and wrote nothing leaves an owner with a
     * feed entry saying a customer sent a photo and no row explaining why there
     * is nothing to open — which is the shape of a support ticket nobody can
     * answer.
     */
    public function failed(?Throwable $e): void
    {
        Log::warning('Inbound media could not be captured after every retry.', [
            'reason' => $e === null ? 'unknown' : $e::class,
            'business_id' => $this->businessId,
            'inbound_message_id' => $this->inboundMessageId,
            'ordinal' => $this->ordinal,
            'actor' => InboundMediaCapture::ACTOR,
        ]);

        Tenancy::actingAs($this->businessId, function (): void {
            $message = InboundMessage::query()->find($this->inboundMessageId);

            if ($message === null || $this->alreadyCaptured()) {
                return;
            }

            app(InboundMediaCapture::class)->recordRefusal(
                $message,
                $this->ordinal,
                InboundMediaOutcome::RefusedUnreachable,
            );
        });
    }

    /**
     * The object's address, derived rather than generated — see the class
     * docblock for why determinism beats unguessability on this one path.
     *
     * ⚠️ **TENANT-FIRST, WHICH IS THE `multi-tenancy` SKILL'S STORAGE RULE.** A
     * key that began with the message id would put two businesses' objects in
     * one prefix, and a prefix is what a bucket policy, a lifecycle rule and a
     * bulk delete all operate on.
     *
     * ⚠️ **NO EXTENSION.** The content type is a column; putting it in the path
     * would make the address depend on a value discovered *during* the fetch, so
     * a retry that saw a different header would write a second object beside the
     * first.
     */
    public function storagePath(): string
    {
        return 'inbound-media/'.$this->businessId.'/'.$this->inboundMessageId.'/'.$this->ordinal;
    }

    /**
     * ⚠️ **CALLED INSIDE {@see Tenancy::actingAs()} BY BOTH CALLERS.**
     * `InboundMedia` is tenant-scoped, so this query is filtered by the global
     * scope with RLS forced beneath it — the `business_id` is deliberately not
     * repeated as a `where`, because a hand-written tenant predicate is the
     * thing that goes wrong when somebody copies the query.
     */
    private function alreadyCaptured(): bool
    {
        return InboundMedia::query()
            ->where('inbound_message_id', $this->inboundMessageId)
            ->where('ordinal', $this->ordinal)
            ->exists();
    }
}
