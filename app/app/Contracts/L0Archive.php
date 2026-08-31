<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Jobs\ArchivePixelBatchJob;
use App\Services\Export\ExportBuilder;
use App\Services\Warehouse\L0Batch;
use Carbon\CarbonImmutable;

/**
 * The landing layer — the only durable thing in the warehouse.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.1: *"L0 LANDING (Bronze). R2. Raw, immutable,
 * exactly as received. Never mutated. Never queried by product code. THE REPLAY
 * SOURCE. If L1/L2 are wrong, rebuild from here."*
 *
 * ⚠️ **THIS INTERFACE EXISTS BECAUSE CLAUDE.md ASKS FOR IT BY NAME.** §"Pixel /
 * warehouse" defers the Cloudflare Workers collector and ClickHouse and then
 * says: *"Keep ingest behind an interface so re-splitting is a swap, not a
 * rewrite."* When L0 moves back to a Worker writing R2 directly, this is the
 * seam that moves.
 *
 * ⚠️ **THIS DOCBLOCK USED TO SAY "NOTHING CALLS `store()` FROM A REQUEST" AND
 * THAT STOPPED BEING TRUE AT DECISIONS 4960–4979 — CORRECTED 2026-08-18
 * (5080).** `POST /api/pixel/e` → `PixelCollector::receive()` →
 * `ArchivePixelBatchJob::handle()` → `store()` is a real, reachable chain today.
 * ⚠️ **A writer existing is not traffic arriving** (4966): §10's bundle
 * delivery is still unbuilt, so nothing serves the pixel to a real page and no
 * genuine visitor event has ever reached this call — but a direct `POST` to the
 * route, by a business's own key, writes a real object. Read decisions 4861 and
 * 4966 before treating either a green replay suite or this correction as a
 * working pipeline.
 */
interface L0Archive
{
    /**
     * Append one immutable batch and return the object path it landed at.
     *
     * ⚠️ **"APPEND" IS LOAD-BEARING AND IMPLEMENTATIONS MUST REFUSE AN
     * OVERWRITE.** §5.1's rule is *"L0 is append-only and never deleted before
     * its retention horizon"*, and an archive that silently replaces an object
     * is one where a replay of yesterday returns something other than what
     * yesterday returned — with nothing anywhere recording that it changed.
     */
    public function store(L0Batch $batch): string;

    /**
     * Every object path for one business over a closed date range, sorted.
     *
     * ⚠️ **SORTED IS PART OF THE CONTRACT, NOT A CONVENIENCE.** The replay reads
     * these in the order given; an object store's own listing order is not
     * guaranteed and differs between drivers, so a rebuild that trusted it would
     * derive rows in a different order on a different backend.
     *
     * @return list<string>
     */
    public function paths(int $businessId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Read one object back to the canonical lines it was written from.
     *
     * ⚠️ **THIS RETURNS THE WHOLE OBJECT AND WHAT BOUNDS IT IS THE WRITER, NOT
     * THE CONTRACT — MEASURED 2026-08-23 (8365).** An implementation has to
     * decompress the object to read any of it, so the array is the whole file;
     * what keeps that small is that the only writer,
     * {@see ArchivePixelBatchJob}, builds an `L0Batch` with **exactly
     * one** `L0Receipt`, and a receipt is one beacon POST of at most
     * `StorePixelBatchRequest::MAX_EVENTS` — fifty events. So one object is fifty
     * events and reading it costs nothing worth measuring.
     *
     * ⛔ **`L0Batch` ITSELF ACCEPTS A LIST OF RECEIPTS AND NOTHING CAPS IT**, so
     * that bound is a fact about today's collector rather than about this
     * interface. Driven: one object holding 656 receipts of fifty events made a
     * replay peak at **43.1 MB** where the same 32,800 events in 656 objects
     * peaked at **5.0 MB**. A collector that batched receipts — which is the
     * obvious optimisation the day L0 moves back to an edge worker — would
     * reintroduce the accumulation 8360 removed, one layer up and with no test
     * watching. **A batching writer has to arrive with a generator here.**
     *
     * @return list<array<string, mixed>>
     */
    public function read(string $path): array;

    /**
     * Delete every L0 object ever written for one business, across every date
     * and every non-PHI data class, and report whether the archive is now
     * clear of them.
     *
     * ⚠️ **WHY THIS EXISTS, AND WHY IT DID NOT UNTIL 5080.** §5.1's rule is
     * "never deleted before its retention horizon" — a rule about *accidental*
     * or *casual* mutation, not about a lawful erasure request, and `store()`
     * writing a real object (see the interface docblock) is what makes this a
     * live gap rather than a theoretical one: {@see \App\Services\Tenant\
     * TenantDeletion} destroys a business's Postgres rows and its export
     * archive (decision 1902) but, until this method, left any pixel events it
     * had ever archived sitting in R2 for the full seven-year horizon with no
     * tenant left to have asked for them — storage-limitation drift with no
     * lawful purpose behind it, on a chain that now genuinely writes.
     *
     * ⚠️ **NOT A DELETE-BY-RANGE.** `paths()` takes a bounded window because a
     * full-archive listing to find one week is an O(archive) call; a purge is
     * the opposite shape on purpose — the whole business, once, forever, and
     * there is no smaller unit an erasure could mean.
     *
     * ⚠️ **PHI IS NEVER WALKED.** No `Phi`-classified business has ever had an
     * object land here — `store()` refuses one outright (decision 4863) — so
     * skipping the class is a correctness statement about what could exist,
     * not an optimisation.
     *
     * @return bool false when an object may still be there, which the caller
     *              must treat as a refusal rather than a completed erasure —
     *              {@see ExportBuilder::purgeAllFor()}'s
     *              contract, matched deliberately
     */
    public function purgeFor(int $businessId): bool;
}
