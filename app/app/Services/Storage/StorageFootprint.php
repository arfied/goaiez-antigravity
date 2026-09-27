<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Console\Commands\ShowStorageFootprint;
use App\Enums\StoredObjectKind;
use App\Models\CampaignRecipient;
use App\Models\InboundMedia;
use App\Models\KnowledgeSource;
use App\Models\Voicemail;
use App\Services\Conversations\ConversationThreads;
use App\Services\Export\ExportBuilder;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many bytes this application is holding in object storage for one tenant —
 * decisions 4760–4763, answering 4687.
 *
 * ⛔ **THIS IS THE MEASUREMENT, AND THERE IS DELIBERATELY NO CEILING BEHIND IT.
 * OBJECT STORAGE IN AGGREGATE REMAINS UNCAPPED.** 4687 recorded that every fetch
 * path has a per-object byte ceiling and nothing bounds the total, and ranked it
 * last of three uncapped paths because R2 has zero egress and per-GB storage is
 * the cheapest line in this product. 4761 is the ruling that followed: a
 * per-tenant aggregate ceiling is **wrong on four of the five kinds**, not merely
 * unnecessary —
 *
 *   - an **export** may not be delayed, gated or degraded at all (`28` §3.7),
 *     and `ExportBuilder`'s own docblock already names a byte cap as *"the one
 *     thing this class may not do"*;
 *   - **inbound media** and **voicemail audio** are received rather than spent.
 *     Nobody at the tenant chose them, and the person whose photograph or voice
 *     would be dropped is not the person who filled the bucket — 4686's
 *     argument, one channel over: refusing a customer for a balance is the
 *     missed-call failure this product exists to fix;
 *   - **campaign images** are rendered inside a send that is already refused by
 *     `SendCredits` when the SMS balance is out, so a second ceiling over the
 *     same act would refuse it twice and once by a limit rule 43 forbids showing.
 *
 * What is left that a ceiling could honestly refuse is **knowledge uploads**,
 * which already carry a 2 MB per-file limit on the screen that accepts them.
 * So the ceiling was refused and the counter was built, because 3297's rule is
 * that a path with no counter *does not look uncapped, it looks free* — and
 * `sending_health_windows` is this codebase's standing lesson that a threshold
 * on a dead counter is a decoration. The counter comes first; the ruling on what
 * to do with it is the owner's and is not made here.
 *
 * ## What this measures, and what it does not
 *
 * ⚠️ **IT COUNTS ROWS, NEVER THE BUCKET.** Every figure is derived from a
 * tenant-scoped table that names an object path, not from a `Storage::allFiles()`
 * listing. That is deliberate — a listing is an unscoped read of a shared bucket
 * and R2 has no tenant boundary in it — but it means **an orphan is invisible
 * here**: bytes on the disk that no row names are, by construction, bytes this
 * class cannot see. `ExportBuilder::purgeOrphans()` is the only orphan sweep in
 * this application and it covers only the export prefix. Reported rather than
 * implied, on 314–316's rule: the claim is written to the size of the mechanism.
 *
 * ⛔ **AND ONE WHOLE STORE FALLS IN THAT GAP RATHER THAN A HANDFUL OF
 * STRAGGLERS, WHICH THE PARAGRAPH ABOVE DID NOT SAY UNTIL 2026-08-23.** The
 * pixel L0 archive writes one gzipped object per beacon per business and
 * creates **no row anywhere**, so *"bytes on the disk that no row names"*
 * covers every object of it — technically true, and it reads as being about
 * leftovers from a failed delete. It is not counted here, it is not a
 * {@see StoredObjectKind}, and adding a case is the **wrong** remedy for three
 * reasons that enum's docblock now carries. **How much the total is short by is
 * not derivable from this application at all**, which is why
 * {@see ShowStorageFootprint} says the archive is excluded and names no figure.
 *
 * ⚠️ **AND IT IS A FOOTPRINT, NOT A BILL.** Nothing here debits the ledger,
 * prices a byte, or converts to money. There is no storage SKU in
 * `DefaultsManifest` and inventing a rate from memory is what 255, 277, 684 and
 * 1349 are all instances of.
 *
 * ## The tenant boundary
 *
 * ⛔ **FAIL-CLOSED ON A TENANT AND NEVER GIVEN A BUSINESS ID.** `forTenant()`
 * opens with {@see Tenancy::idOrFail()} and takes no argument, so it cannot be
 * called without one established — every query beneath it is filtered by the
 * global scope with RLS `FORCE`d underneath. A signature that accepted an id
 * would invite a caller to loop it, and **a per-tenant total that can be read
 * across tenants is a leak rather than a metric**: five tables, five global
 * scopes, and one `withoutGlobalScopes()` between an operator and every
 * account's contents. {@see ShowStorageFootprint} therefore names one account
 * and wraps the call in `Tenancy::actingAs()`, which is 569's by-reference
 * pattern and `AccountAudit`'s reason for it.
 */
final class StorageFootprint
{
    /**
     * ⛔ **THE EXPORT LINE COMES THROUGH ITS OWNER RATHER THAN OFF THE MODEL, AND
     * A LINT IS WHY** (4770). `tenant_exports` has a chokepoint in
     * `CrmTest` admitting five files, and reading `TenantExport::query()` here
     * made this a sixth. **The allowlist was not widened** —
     * {@see ExportBuilder::storedObjectTotals()} answers three integers, so the
     * model never leaves the class that owns it and the lint got *narrower*
     * relative to the feature, which is the direction 1911 moved this same shape.
     */
    public function __construct(private readonly ExportBuilder $exports) {}

    /**
     * Every kind's totals for the tenant in context, in {@see StoredObjectKind}
     * order so two runs of the Ops command are diffable.
     *
     * @return list<StoredObjectTotals>
     */
    public function forTenant(): array
    {
        // Fail-closed. See the class docblock: no argument, and this throws
        // rather than returning an empty footprint when nobody set a tenant —
        // "zero bytes" and "you did not say whose" must never look the same.
        Tenancy::idOrFail();

        return [
            // See the constructor: three integers from the class that owns the
            // table, never a query against the model.
            $this->fromCounts(StoredObjectKind::Export, $this->exports->storedObjectTotals()),
            $this->totals(
                StoredObjectKind::KnowledgeUpload,
                // ⛔ **THE PREDICATE IS "A ROW THAT NAMES AN OBJECT", NEVER "A ROW
                // OF THE RIGHT TYPE", AND THAT DISTINCTION COST A MUTATION TO
                // FIND** (4764). This read
                // `->where('type', KnowledgeSourceType::Upload)->whereNotNull('file_path')`
                // with a comment calling the type filter load-bearing; **deleting
                // it left the whole suite green**, because a `Url` source is a
                // crawled page with a checksum, chunks and no `file_path` — so
                // the path predicate was already doing all the work and the
                // comment was 314–316's shape. It is worse than redundant: the
                // one future shape it was written for is a `Url` source that
                // *did* cache a copy, and that row would have an object this
                // footprint must count. Object existence is the same question on
                // all five kinds, and it is asked the same way on all five.
                KnowledgeSource::query()->whereNotNull('file_path'),
                'byte_size',
            ),
            $this->totals(
                StoredObjectKind::CampaignMedia,
                CampaignRecipient::query()->whereNotNull('media_path'),
                'media_bytes',
            ),
            $this->totals(
                StoredObjectKind::InboundMedia,
                // ⚠️ **`storage_path`, NOT `outcome`.** `inbound_media` records
                // every refusal as a first-class row with no bytes behind it —
                // that is the whole design of `InboundMediaOutcome` — so counting
                // rows would report a PHI refusal as a stored object.
                InboundMedia::query()->whereNotNull('storage_path'),
                'byte_size',
            ),
            $this->fromCounts(StoredObjectKind::WhatsappMedia, app(ConversationThreads::class)->storedMediaTotals()),
            $this->totals(
                StoredObjectKind::VoicemailRecording,
                // Same shape: `VoicemailAudioState::Unavailable` is a voicemail we
                // could not or would not fetch, and it keeps its row.
                Voicemail::query()->whereNotNull('recording_path'),
                'recording_bytes',
            ),
        ];
    }

    /**
     * The same three numbers, when a chokepoint means another class had to count
     * them. See the constructor.
     *
     * @param  array{objects: int, bytes: int, unmeasured: int}  $counts
     */
    private function fromCounts(StoredObjectKind $kind, array $counts): StoredObjectTotals
    {
        return new StoredObjectTotals(
            kind: $kind,
            objects: $counts['objects'],
            bytes: $counts['bytes'],
            unmeasured: $counts['unmeasured'],
        );
    }

    /**
     * Three aggregates over one already-narrowed, already-scoped builder.
     *
     * ⚠️ **THE BUILDER ARRIVES SCOPED AND IS NEVER RE-FILTERED HERE.** Every
     * model passed in carries `BelongsToTenant`, so the `business_id` predicate
     * is the global scope's. Repeating it as a `where` is the hand-written tenant
     * predicate the multi-tenancy skill warns about — the one that goes wrong
     * when somebody copies the query — and RLS catches a forgotten filter, never
     * a wrong one.
     *
     * ⚠️ **THREE QUERIES RATHER THAN ONE `selectRaw`.** This runs once, from a
     * console command, against five tables; a hand-written aggregate select is
     * how a raw string ends up next to a tenant boundary for no measurable gain.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function totals(StoredObjectKind $kind, Builder $query, string $column): StoredObjectTotals
    {
        return new StoredObjectTotals(
            kind: $kind,
            objects: (clone $query)->count(),
            // `sum()` over an empty set answers 0, and over a nullable column
            // ignores the nulls — which is exactly the split this class wants,
            // with `unmeasured` counting the ones it skipped.
            bytes: (int) (clone $query)->sum($column),
            unmeasured: (clone $query)->whereNull($column)->count(),
        );
    }
}
