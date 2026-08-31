<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\AutopilotActionType;
use App\Enums\DataClassification;
use App\Enums\InboundMediaOutcome;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Models\Business;
use App\Models\InboundMedia;
use App\Models\InboundMessage;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Decide what happens to the pictures on an inbound MMS — T176 P10, skill 12.
 *
 * ⛔ **IT DECIDES; IT DOES NOT FETCH.** Everything that touches the network is
 * {@see CaptureInboundMediaJob}'s, and the split is not tidiness. This runs
 * inside the carrier's webhook, where a slow CDN would hold the request open
 * until Infobip gives up and redelivers — against an `inbound_messages` row that
 * refuses the replay, so the media would be lost *and* the retry would cost
 * every other message in the same delivery batch. The webhook decides and
 * acknowledges; the fetch is a job somebody can read when it fails.
 *
 * ⛔ **THE SPLIT IS RIGHT AND ITS LAST FIVE WORDS ARE NOT — CORRECTED
 * 2026-08-25 (9591, 9592).** **Nobody reads `failed_jobs`** (9370). What makes
 * this split safe is not that somebody looks in that table; it is that
 * {@see CaptureInboundMediaJob::failed()} writes
 * {@see InboundMediaOutcome::RefusedUnreachable} onto an `inbound_media` row
 * once the attempts are spent — a durable, tenant-scoped record this class
 * itself creates through {@see self::recordRefusal()}. ⚠️ **So the honest
 * sentence names the row rather than the queue**, and it is the same row the
 * four refusals decided *here* are written to: an MMS whose picture we could not
 * keep is recorded identically whether we declined to fetch it or could not
 * reach it, and the outcome column is what tells them apart.
 *
 * ## Where it sits in {@see InboundMessages}, and what the ordering protects
 *
 * ⛔ **AFTER COMPLIANCE, ALWAYS.** STOP, HELP, START and the suppression they
 * write are unconditional under every recorded sending basis (2099), and this
 * must never become something they wait behind. A tenant lookup, a
 * classification read and an insert placed before `suppressFromCarrier()` would
 * sit between a person saying STOP and the refusal being written, which
 * `InboundMessages::stop()`'s own docblock forbids by name.
 *
 * ⚠️ **AND IT RUNS FOR EVERY KEYWORD, INCLUDING STOP.** A photograph attached to
 * the word STOP is still a photograph that person sent this business, and the
 * suppression is already written and is platform-wide either way. Skipping the
 * capture on the compliance branches would lose media on exactly the messages
 * that later get argued about.
 *
 * ⛔ **THE ONE SENDER THAT IS NOW REFUSED IS THE BUSINESS'S OWN ACCOUNT HOLDER
 * AFTER THEY HAVE SAID STOP — wave 41 lane E, decision 11100.** 10830 stopped
 * storing the **words** of that person's text and this path went on keeping the
 * **picture**, so one MMS got two opposite answers and the flattering one landed
 * on the attachment. {@see InboundMediaOutcome::RefusedOwnerStopped} carries the
 * full argument. ⚠️ **It is not "the sender said STOP"** — a carrier suppression
 * is about outbound messages to a member of the public and changes nothing here;
 * what is refused is *this receiving number's own account holder, stopped*, and
 * a stopped account holder of business A texting business B is still B's
 * customer.
 *
 * ⚠️ **IT SWALLOWS ITS OWN FAILURE AT THE CALL SITE**, for `linkToCampaign()`'s
 * reason: an exception escaping a carrier webhook turns a 200 into a 500, and
 * Infobip answers a 500 by redelivering. **An MMS whose picture we could not
 * keep is still a received message** and still reaches the person.
 *
 * ## ⛔ THE PHI RULING, WHICH IS THE LOAD-BEARING DECISION IN THIS CLASS (4166)
 *
 * **A PHI-classified tenant's inbound media is never fetched and never stored.**
 * `29` §2 rule 24 puts health information in a separate schema, under a separate
 * role, under a separate KMS key, and `CLAUDE.md` records that the key and the
 * collector enforcement **wait on Stage 3** — which is also why no healthcare or
 * dental tenant can be onboarded today at all.
 *
 * Every other inbound surface is narrower than this one. A review body and a
 * text message are words somebody chose to type; **an MMS is whatever a customer
 * pointed a camera at**, and to a dental practice that is a photograph of
 * somebody's mouth. There is no consent flow here of the kind 2079–2081 built
 * for review text — that checkbox is shown to a reviewer on a page we render,
 * and nobody is rendering anything to somebody's Messages app.
 *
 * ⛔ **"NO SUCH TENANT EXISTS YET" IS NOT THE ARGUMENT AND MUST NOT BECOME ONE.**
 * `TenantClassification` raises a business to `Phi` from Google's own categories
 * at provisioning, so the flag arrives without anybody deciding to set it — the
 * classification can appear before anybody notices, which is precisely why the
 * refusal is in the code and not in an onboarding checklist.
 *
 * ⚠️ **THE REFUSAL IS A ROW, NOT A SILENCE.** A refusal that wrote nothing would
 * be indistinguishable from an MMS that carried no media, and the owner would
 * never learn a customer had sent them something. The row carries no URL, no
 * bytes and no filename — only that a part arrived and was refused, which is the
 * least this can record while still being a record.
 */
final class InboundMediaCapture
{
    /** Who the record names for a decision nobody in this company took. */
    public const string ACTOR = 'system:inbound-media-capture';

    public function __construct(
        private readonly TenantNumbers $numbers,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Decide what happens to the pictures on this message.
     *
     * `$stoppedAccountHolderBusinessIds` is every business that has registered
     * **this sender** as its own account holder and whose registration is
     * stopped — wave 41 lane E, decision 11100.
     *
     * ⛔ **REQUIRED, WITH NO DEFAULT, AND THAT IS THE POINT OF IT.** A defaulted
     * empty array is the edit a lane makes to avoid touching a call site, and
     * `10841`'s finding is that a reflection lint over a parameter's *type*
     * stays green against one — so the property has to be that the caller
     * cannot omit the answer. There is exactly one caller in `app/`
     * ({@see InboundMessages::captureMedia()}) and it is the only place that
     * holds the sender, which is the one thing this class deliberately never
     * looks at.
     *
     * ⚠️ **THE LIST IS ABOUT THE SENDER AND THE FILTER IS ABOUT THE RECEIVING
     * NUMBER'S TENANT**, which is why it is a list rather than a boolean. An
     * account holder of business A texting business B's number is a customer of
     * B, and their photograph is B's to keep.
     *
     * @param  list<InboundMediaPart>  $parts
     * @param  list<int>  $stoppedAccountHolderBusinessIds
     */
    public function capture(InboundMessage $message, array $parts, array $stoppedAccountHolderBusinessIds): void
    {
        if ($parts === []) {
            // The ordinary case by a wide margin: most inbound text is text.
            return;
        }

        $toNumber = $message->to_number;

        if ($toNumber === null) {
            $this->refuseWithoutTenant($message, 'the payload carried no receiving number');

            return;
        }

        // ⚠️ **OUR OWN NUMBER ESTABLISHES THE TENANT AND NOTHING ELSE MAY.**
        // R8's reverse lookup, and 2125's precedent: a tenant inferred from
        // *"who last messaged this person"* is wrong the first time somebody is
        // a customer of two businesses — and here being wrong would write one
        // business's customer's photograph into another business's account.
        $businessId = $this->numbers->tenantFor($toNumber);

        if ($businessId === null) {
            // The shared Lane A pool number, or a number no longer ours. There
            // is no tenant to store this against and `inbound_media` is
            // tenant-owned; guessing one is the breach the paragraph above
            // refuses.
            $this->refuseWithoutTenant($message, 'the receiving number resolves to no tenant');

            return;
        }

        // ⚠️ **DECIDED HERE, NOT INSIDE THE TENANT**, because the question is
        // about the sender and the answer was computed by the caller before any
        // tenant was established. Passing a boolean down keeps
        // {@see self::withinTenant()} the one place that reads the business row.
        $senderIsAStoppedAccountHolder = in_array($businessId, $stoppedAccountHolderBusinessIds, true);

        Tenancy::actingAs($businessId, function () use ($message, $parts, $businessId, $senderIsAStoppedAccountHolder): void {
            $this->withinTenant($message, $parts, $businessId, $senderIsAStoppedAccountHolder);
        });
    }

    /**
     * @param  list<InboundMediaPart>  $parts
     */
    private function withinTenant(
        InboundMessage $message,
        array $parts,
        int $businessId,
        bool $senderIsAStoppedAccountHolder,
    ): void {
        $business = Business::query()->find($businessId);

        if ($business === null) {
            // Unreachable in practice — `tenantFor()` read a `phone_numbers` row
            // that names it — and a log rather than a throw, because this runs
            // inside a carrier webhook where a throw is a redelivery.
            Log::warning('Inbound media arrived for a number whose business could not be read.', [
                'business_id' => $businessId,
                'inbound_message_id' => $message->getKey(),
                'actor' => self::ACTOR,
            ]);

            return;
        }

        if ($senderIsAStoppedAccountHolder) {
            // ⛔ **THE OTHER HALF OF 10830, AND IT RETURNS BEFORE THE ACTIVITY
            // ITEM ON PURPOSE.** `InboundMessages::recordOwnerReply()` files
            // nothing at all for a stopped account holder — no row, no feed
            // entry, no audit line — and a feed entry here would say
            // {@see AutopilotActionType::PhotoReceived}'s own sentence, *"A
            // customer texted you a photo"*, about the account holder
            // themselves. **A wrong sentence is worse than a missing one**, and
            // this is the one person the platform has been told to leave alone.
            //
            // ⚠️ **THE REFUSAL IS STILL A ROW** — this class's opening rule.
            // What survives is that a part arrived and was refused, with no
            // bytes, no URL and no filename behind it, so *"they sent us
            // something and we did not keep it"* stays answerable a year later.
            // ⚠️ **`array_keys()` RATHER THAN THE PARTS THEMSELVES**, because
            // the ordinal is the whole of what this arm needs and iterating the
            // values would put an attacker-supplied URL in scope inside the one
            // branch that exists to never look at one.
            foreach (array_keys($parts) as $ordinal) {
                $this->recordRefusal($message, $ordinal, InboundMediaOutcome::RefusedOwnerStopped);
            }

            return;
        }

        $handlesHealthInformation = $business->data_classification === DataClassification::Phi;

        // ⚠️ **ONE ENTRY PER MESSAGE, WRITTEN BEFORE THE OUTCOME IS KNOWN**, and
        // {@see AutopilotActionType::PhotoReceived} argues why: the arrival is
        // established by the payload, the storage is decided later, and an owner
        // who never hears about the picture we failed to fetch is worse served
        // than one who hears about it and finds nothing to open.
        $this->activity->record(
            AutopilotActionType::PhotoReceived,
            metadata: [
                'parts' => count($parts),
                'kept' => ! $handlesHealthInformation,
            ],
        );

        $failures = [];

        foreach ($parts as $ordinal => $part) {
            if ($handlesHealthInformation) {
                // ⛔ 4166. No socket is opened, so the bytes never enter this
                // process, and the URL never enters a queue payload either.
                $this->recordRefusal($message, $ordinal, InboundMediaOutcome::RefusedHealthTenant);

                continue;
            }

            try {
                CaptureInboundMediaJob::dispatch(
                    businessId: $businessId,
                    inboundMessageId: (int) $message->getKey(),
                    ordinal: $ordinal,
                    url: $part->url,
                );
            } catch (Throwable $e) {
                // ⛔ **ONE PART MUST NOT COST THE PARTS BESIDE IT**, which is
                // `InfobipInboundController`'s own rule about one unreadable
                // entry in a batch, one level down. ⚠️ **AND IT IS NOT
                // HYPOTHETICAL**: on an inline queue `dispatch()` *runs* the
                // job, so a first picture the CDN will not serve throws right
                // here — and without this the second picture is never dispatched
                // and never even gets a row. On a real queue driver the throw is
                // a queue connection that would not take the write, which is
                // rarer and worse.
                $failures[] = $e;
            }
        }

        if ($failures !== []) {
            // ⚠️ **ISOLATED IS NOT SWALLOWED.** Every part got its chance
            // above; this makes the failure visible to
            // {@see InboundMessages::captureMedia()}, which logs it with the
            // class name and our own row id and never the URL. Dropping it here
            // would make a partial capture look like a complete one, which is
            // the shape this file spends its refusals avoiding.
            throw $failures[0];
        }
    }

    /**
     * Write the row that says we refused, and the audit line beside it.
     *
     * ⚠️ **PUBLIC BECAUSE THE JOB WRITES THE SAME SHAPE**, and a second copy of
     * "what a refusal row looks like" is how the two drift — this codebase's
     * most repeated lesson. The caller is already inside
     * {@see Tenancy::actingAs()}; this does not open one, because a method that
     * silently established a tenant would be usable from a path that had not
     * decided which one.
     *
     * Returns false when the row was already there, which is a redelivery rather
     * than an error.
     */
    public function recordRefusal(InboundMessage $message, int $ordinal, InboundMediaOutcome $outcome): bool
    {
        return $this->write($message, $ordinal, $outcome);
    }

    /**
     * Write the row that says we kept it.
     *
     * ⚠️ **FIVE NAMED ARGUMENTS RATHER THAN AN ARRAY, AND THE TYPE CHECKER IS
     * WHY.** An array shape passed to `create()` widens to `array<string,
     * mixed>` and stops being checked against the model's own properties, so a
     * misspelt key would be silently discarded by the guard and produce a
     * `stored` row with no path — which the CHECK constraint then rejects at the
     * far end of a queue worker.
     */
    public function recordStored(
        InboundMessage $message,
        int $ordinal,
        string $contentType,
        int $byteSize,
        string $checksum,
        string $storageDisk,
        string $storagePath,
    ): bool {
        return $this->write(
            $message,
            $ordinal,
            InboundMediaOutcome::Stored,
            $contentType,
            $byteSize,
            $checksum,
            $storageDisk,
            $storagePath,
        );
    }

    /**
     * Destroy every photograph this tenant's customers ever texted them.
     *
     * ⛔ **CALLED BY `TenantDeletion::execute()` AND BY
     * NOTHING ELSE, AND IT IS NOT A PRUNE.** `StorageRetention` deletes an
     * object and **keeps the row**, on a period, for an account that still
     * exists. This deletes a prefix and keeps nothing, because the account is
     * about to stop existing — and it runs **before** the cascade, which is the
     * only moment `inbound_media` can still be asked anything.
     *
     * ⛔ **THE ERASURE USED TO MAKE THIS OBJECT *MORE* PERMANENT, NOT LESS**
     * (8871). `PruneStoredObjects` walks users, then each user's businesses,
     * then works inside `Tenancy::actingAs()`. After the account is destroyed
     * there is no business to walk and no row naming a path, so a **live**
     * tenant's customer photograph was pruned at the operator's period and an
     * **erased** tenant's was kept for ever. A statutory erasure was the worse
     * outcome for the person in the picture, who is not our customer and never
     * agreed to anything.
     *
     * ⚠️ **A PREFIX, NOT A ROW SWEEP** — `ExportBuilder::purgeAllFor()`'s
     * reasoning. `CaptureInboundMediaJob` puts the bytes and *then* writes the
     * row, so a worker that dies between the two leaves an object no row names;
     * a row-driven delete cannot see it and this can.
     *
     * ⚠️ **THE DISKS COME OFF THE ROWS, AND THE CONSTANT IS ADDED TO THEM.**
     * `inbound_media` records the disk it wrote to precisely so a later move
     * between object stores can be told what it has already moved
     * (`StorageRetention::pruneInboundMedia()` makes the same point), so
     * assuming today's constant would silently leave everything written before
     * it last changed. Both are swept, and the union is what makes this true
     * across a move rather than at one instant.
     *
     * ⚠️ **NO ROW, NO OBJECT-STORE CALL** — `ExportBuilder::purgeAllFor()`'s
     * guard shape. The signal is the **row**, not the path:
     * `StorageRetention`'s own rule is *the object goes and the row stays*, so a
     * pruned tenant still answers true here and still gets its prefix swept.
     *
     * ⛔ **AND IT BUYS TWO ROUND TRIPS RATHER THAN THE PROPERTY THAT GUARD IS
     * FAMOUS FOR, WHICH IS WORTH SAYING PLAINLY** (9010). *"An account that
     * never received an MMS cannot fail its own erasure on a bucket it had no
     * reason to touch"* is the sentence this shape is copied from, and it is
     * **already false of `s3` before this method exists**:
     * `L0Archive::purgeFor()` has no such guard and reaches that bucket on every
     * erasure ever performed. `TenantDeletionTest`'s own header says so from the
     * other side. So this saves a `LIST` and a `DELETE` for the ordinary account
     * and nothing more; the two kinds on the default disk get the real
     * property.
     *
     * @return bool false when anything may still be there, which refuses the
     *              whole deletion rather than completing it with the photograph
     *              surviving
     */
    public function purgeAllFor(int $businessId): bool
    {
        if (! InboundMedia::query()->exists()) {
            return true;
        }

        $disks = InboundMedia::query()
            ->whereNotNull('storage_disk')
            ->distinct()
            ->pluck('storage_disk')
            ->all();

        $disks[] = CaptureInboundMediaJob::DISK;

        $prefix = 'inbound-media/'.$businessId;
        $clear = true;

        foreach (array_unique($disks) as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            try {
                $disk = Storage::disk($name);
                $disk->deleteDirectory($prefix);

                if ($disk->allFiles($prefix) !== []) {
                    $clear = false;
                }
            } catch (Throwable) {
                // ⚠️ Refuses rather than throws, decision 823's rule: this is
                // reached from a sweep that walks every due deletion, and one
                // unreachable bucket must not abandon the rest of the queue.
                $clear = false;
            }
        }

        return $clear;
    }

    private function write(
        InboundMessage $message,
        int $ordinal,
        InboundMediaOutcome $outcome,
        ?string $contentType = null,
        ?int $byteSize = null,
        ?string $checksum = null,
        ?string $storageDisk = null,
        ?string $storagePath = null,
    ): bool {
        try {
            // ⚠️ **WRAPPED IN A TRANSACTION SO THE VIOLATION ROLLS BACK TO A
            // SAVEPOINT**, which is `InboundMessages::record()`'s Postgres fact
            // rather than a style choice: a failed statement inside an open
            // transaction aborts the whole transaction, so catching the unique
            // violation without the savepoint would poison every query after it
            // — including the ones handling the other parts of the same message.
            $media = DB::transaction(fn (): InboundMedia => InboundMedia::query()->create([
                'inbound_message_id' => $message->getKey(),
                'ordinal' => $ordinal,
                'outcome' => $outcome,
                'content_type' => $contentType,
                'byte_size' => $byteSize,
                'checksum' => $checksum,
                'storage_disk' => $storageDisk,
                'storage_path' => $storagePath,
                'created_at' => now(),
            ]));
        } catch (QueryException $e) {
            // 23505 is Postgres' unique_violation, read through `SqlState`
            // rather than `$e->getCode()` — that value is an int on some driver
            // paths and a bare comparison silently misses the match.
            if (SqlState::of($e) === '23505') {
                return false;
            }

            throw $e;
        }

        // ⚠️ **IDS AND AN OUTCOME, NEVER A URL, A NUMBER OR A FILENAME.** This
        // is the append-only record that a member of the public's photograph was
        // kept or refused, which is the question a complaint is answered from —
        // and the one thing the row itself could be argued to have been edited
        // into, which is why the model refuses updates.
        $this->audit->record(
            action: $outcome->isStored() ? 'inbound_media.captured' : 'inbound_media.refused',
            actor: self::ACTOR,
            entity: $media,
            metadata: [
                'outcome' => $outcome->value,
                'inbound_message_id' => $message->getKey(),
                'ordinal' => $ordinal,
            ],
        );

        return true;
    }

    /**
     * Nothing can be stored, because there is no tenant to store it against.
     *
     * ⚠️ **THE LOG RATHER THAN `audit_log`, AND NOT BY PREFERENCE** —
     * `InboundMessages`' own reasoning for an unparseable sender.
     * `AuditService::record()` opens with `Tenancy::idOrFail()` and this path has
     * no tenant to establish, which is the same fact that produced the refusal.
     *
     * ⚠️ **AND THE URL IS DELIBERATELY ABSENT FROM THE LINE.** It is an address
     * a stranger chose that points at a member of the public's photograph; a
     * capture that could not happen must not pay for itself by writing that into
     * the one place with no tenant and no retention policy.
     */
    private function refuseWithoutTenant(InboundMessage $message, string $because): void
    {
        Log::warning('An inbound MMS carried media that could not be filed against a tenant.', [
            'reason' => $because,
            'inbound_message_id' => $message->getKey(),
            'actor' => self::ACTOR,
        ]);
    }
}
