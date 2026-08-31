<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Contracts\L0Archive;
use App\Enums\DataClassification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * L0 on an S3-compatible object store — Cloudflare R2 in production.
 *
 * ⛔ **AND UNTIL 2026-08-25 THAT SENTENCE DESCRIBED A ROOM WITH NO DOOR**
 * (9408, 9420). `league/flysystem-aws-s3-v3` was in no `composer.lock` this
 * repository ever produced, so every call in this class raised
 * `Error: Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not
 * found` at {@see self::disk()} — **the append-only guard, the gzip, the PHI
 * refusal and the `put(…) === false` check below have never once run on any
 * deployment**, and `ArchivePixelBatchJob` has been failing every accepted
 * beacon into `failed_jobs` since the collector landed on 2026-08-18. The
 * driver is installed; whether a byte has ever reached R2 is a fact about
 * production and this file cannot answer it.
 *
 * ⚠️ **`'throw' => false` IS ONE-SIDED ON THIS DISK AND THIS CLASS DEPENDS ON
 * BOTH SIDES** (9424, measured against a real client). `put()` answers `false`
 * — which {@see self::store()} checks — while `exists()` and `allFiles()`
 * **raise regardless of the flag**, because `FilesystemAdapter` wraps neither.
 * So {@see self::store()}'s append-only probe and {@see self::paths()}'s day
 * walk fail loudly on an unreachable store, and that is the direction the
 * record of truth needs: a swallowed listing failure would let
 * `warehouse:replay` rebuild a populated range as empty and report success.
 *
 * CLAUDE.md §"Pixel / warehouse": *"L0 stays the durable, replayable archive:
 * gzipped JSONL in Cloudflare R2 (S3-compatible, so Laravel's `s3` driver — zero
 * egress matters for greeting audio and replays)."*
 *
 * ⚠️ **R2'S HOST IS INVISIBLE TO `SUBPROCESSOR-INVENTORY.md`'s SCANNER AND THAT
 * IS ALREADY WRITTEN DOWN.** `OutboundTest`'s third assertion says so in as many
 * words — *"R2's host arrives from AWS_ENDPOINT in config/filesystems.php"* — so
 * this class adds no host literal and the 428–433 parser stays green. What did
 * change on 2026-08-17 is the inventory row's own prose: it read *"No
 * `Storage::disk` call exists anywhere"*, and this file is that call. The row
 * moved from §3 to §2 on `InfobipClient`'s precedent (1576).
 *
 * ---------------------------------------------------------------------------
 * ⛔ NOTHING EXPIRES L0 — NOT AT SEVEN YEARS, NOT AT ANY OTHER NUMBER
 * ---------------------------------------------------------------------------
 * ⚠️ **THIS IS THE ONE THING TO READ BEFORE BELIEVING ANY SENTENCE IN THIS
 * REPOSITORY ABOUT L0's RETENTION** (7706, 2026-08-22). §5.2 says *"Retention:
 * 7 years, encrypted at rest"* and §18 repeats it, and **that is a sentence in a
 * document and nothing else**: there is no bucket lifecycle rule here, no config
 * key beside `warehouse.l0_disk` and `warehouse.schema_version`, no
 * [[\App\Enums\StoredObjectKind]] case — so `StorageRetention` and
 * `storage:prune` cannot see this store at all — and no scheduled command
 * anywhere reaches it. ⛔ **The only deletion path that exists is
 * {@see self::purgeFor()}, called from tenant erasure.** So the honest statement
 * is *stronger* than seven years: **a batch archived today is here for ever**,
 * and every docblock in `app/` that says *"append-only for seven years"* is
 * describing an intention rather than a mechanism.
 *
 * ⛔ **AND NOTHING COUNTED IT EITHER, WHICH IS THE SAME HOLE ONE INSTRUMENT
 * OVER — CLOSED 2026-08-23 (8980–8999).** The clause above already said
 * `StorageRetention` and `storage:prune` cannot see this store. The footprint
 * command could not size it either, and **said nothing about that**, so an
 * operator read a total that omitted the largest per-object writer in this
 * application while reading as complete — the exact failure
 * [[\App\Enums\StoredObjectKind]]'s own bolded rule is about. The lint that
 * registered this file as an exemption argued it was *"short by zero"* because
 * *"nothing in `app/` calls `L0Archive::store()` outside the replay command and
 * the tests"*; that stopped being true on 2026-08-18, when the collector landed
 * on a branch that was not an ancestor of the exemption's — verified with
 * `git merge-base --is-ancestor`, in both directions.
 *
 * ✅ **THE REMEDY IS A DISCLOSURE AND NOT A CASE**, and the three reasons a case
 * would be wrong are in that enum's docblock — the sharpest being that a case
 * hands an operator a `storage.retention_days.…` key over the record of truth.
 * Both Ops readers now name this store, and `StorageTest` fails the build if
 * either stops. ⛔ **The under-count itself is unchanged and permanent**, and
 * how large it is **is not derivable from this application**.
 *
 * ⚠️ **AND THE FIX IS NOT A CONSTANT SOMEBODY PICKS.** A horizon on the derived
 * layer is available because L1 and L2 are *"derived and disposable"* and a
 * replay rebuilds them — see [[WarehouseRetention]]. **L0 is the record of
 * truth**, so expiring it destroys the only copy of somebody's traffic and makes
 * every range before the cut permanently unreplayable, which is the one property
 * `CLAUDE.md`'s critical rules name for this layer. It is also cheaper to leave
 * than to get wrong: an R2 lifecycle rule is a bucket setting this repository
 * does not manage. **Owed to the owner as a ruling, with the mechanism owed to
 * whoever holds `StoredObjectKind` and `config/warehouse.php` next.**
 *
 * ⛔ **A `Phi`-CLASSIFIED BUSINESS IS REFUSED OUTRIGHT, NOT STRIPPED** (decision
 * 4863). §5.2 requires PHI to land under a separate prefix **with a separate KMS
 * key**, and CLAUDE.md puts that key behind Stage 3: *"rule 24's KMS key and
 * collector enforcement wait on Stage 3."* Writing PHI to the ordinary prefix
 * under the ordinary key while the separate one is unbuilt would be the
 * protection-asserted-before-it-is-true failure (314–316) with a seven-year
 * retention horizon attached — the bytes stay wrong for as long as the archive
 * lives. Refusing is the fail-closed reading, it is the one 4580 recommended for
 * the collector, and the cost of being wrong in this direction is a lost
 * datapoint rather than an unencrypted health record.
 */
final class ObjectStoreL0Archive implements L0Archive
{
    public function store(L0Batch $batch): string
    {
        if ($batch->dataClass === DataClassification::Phi) {
            throw new RuntimeException(
                'A PHI-classified business cannot write to L0 yet. `29` §2 rule 24 requires a separate '
                .'prefix under a separate KMS key and that key does not exist (CLAUDE.md: "rule 24\'s KMS '
                .'key and collector enforcement wait on Stage 3"). Refusing is the fail-closed reading; '
                .'writing under the ordinary key would be wrong for the seven years the object is retained.',
            );
        }

        $path = $batch->path();
        $disk = $this->disk();

        // §5.1: "L0 is append-only and never deleted before its retention
        // horizon." An object store's `put` is an overwrite, so immutability is
        // something this class enforces rather than something it inherits.
        //
        // ⚠️ NOT A TRANSACTION AND NOT A LOCK. Two writers racing on the same
        // key would both pass this check and one would win; the batch id is a
        // fresh UUID per batch, so the collision this can actually prevent is a
        // re-run of the *same* batch — which is the one that happens, because it
        // is what a retried queue job does.
        if ($disk->exists($path)) {
            throw new RuntimeException(
                'L0 object '.$path.' already exists. The landing layer is append-only (§5.1) and '
                .'overwriting it would change what every past replay of this range returns, silently.',
            );
        }

        // ⚠️ `gzencode()` WRITES A ZERO mtime IN ITS HEADER ON PHP 8.4, WHICH IS
        // WHY THE COMPRESSED OBJECT IS ITSELF REPRODUCIBLE — verified rather
        // than assumed, and pinned by a test, because the gzip format has a
        // 4-byte modification-time field and a writer that filled it would make
        // every object differ from its own re-encoding while decompressing to
        // identical bytes. The OS byte (0x03, Unix) is the other platform-
        // dependent field and is pinned in the same test.
        $encoded = gzencode($batch->lines(), 9);

        if ($encoded === false) {
            throw new RuntimeException('Could not gzip L0 object '.$path.'.');
        }

        if ($disk->put($path, $encoded) === false) {
            throw new RuntimeException('Could not write L0 object '.$path.'.');
        }

        return $path;
    }

    public function paths(int $businessId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $disk = $this->disk();
        $paths = [];

        // ⚠️ WALKED BY DAY RATHER THAN LISTED AND FILTERED. A prefix listing over
        // a seven-year archive to find one week is an O(archive) call that gets
        // slower every day it is not changed; the partition layout exists to be
        // navigated. It also means an empty day costs one listing and returns
        // nothing, rather than a range scan quietly returning a truncated page.
        for ($day = $from->utc()->startOfDay(); $day->lessThanOrEqualTo($to->utc()); $day = $day->addDay()) {
            foreach (DataClassification::cases() as $class) {
                if ($class === DataClassification::Phi) {
                    continue;
                }

                $prefix = 'class='.$class->value.'/business='.$businessId.'/dt='.$day->format('Y-m-d');

                foreach ($disk->allFiles($prefix) as $file) {
                    $paths[] = $file;
                }
            }
        }

        // The contract's sort. Object stores do not promise a listing order and
        // the local driver's differs from S3's; the replay reads these in order.
        //
        // ⚠️ NO `array_values()` AFTER IT — `sort()` reindexes in place, so the
        // wrap this used to carry was dead code and Larastan said so. Noted
        // rather than silently deleted: the declared return type is
        // `list<string>` and it is `sort()` that makes that true, so removing
        // the sort would break the type as well as the contract.
        sort($paths, SORT_STRING);

        return $paths;
    }

    public function read(string $path): array
    {
        $raw = $this->disk()->get($path);

        if ($raw === null) {
            throw new RuntimeException('L0 object '.$path.' is missing. A replay cannot rebuild what it cannot read.');
        }

        // ⛔ **THE `@` IS WHAT MAKES THE LINE BELOW REACHABLE, AND WITHOUT IT THAT
        // SENTENCE HAD NEVER BEEN PRINTED ANYWHERE — 2026-08-23 (8507).**
        // `gzdecode()` raises `E_WARNING` *before* it returns `false`, and
        // `Illuminate\Foundation\Bootstrap\HandleExceptions` promotes every
        // warning inside `error_reporting()` to an `ErrorException` — in every
        // environment, not only under `APP_DEBUG`. So a damaged object threw
        // `ErrorException: gzdecode(): data error` from the line above and the
        // typed refusal below was unreachable: **an operator with an unreadable
        // L0 object saw a PHP warning where this class had written them a
        // sentence naming the object.** Found by driving it — the assertion in
        // `WarehouseReplayCommandTest` failed on the message while the behaviour
        // it was testing was already correct, which is the only way a dead
        // `throw` of this shape ever surfaces.
        $plain = @gzdecode($raw);

        if ($plain === false) {
            throw new RuntimeException('L0 object '.$path.' is not readable gzip.');
        }

        $lines = [];

        foreach (explode("\n", $plain) as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = CanonicalJson::decode($line);

            // ⚠️ **VERSION-AWARE, SINCE §11 ROW 8 (decision 5000s).** The line's
            // own `schema_version` decides which shape it must match — a
            // version-1 line has no `ip_hash`/`browser`/`browser_version`/`os`
            // and never will, because it was written before those fields
            // existed and L0 is never rewritten. Reading every archived line
            // against today's shape is exactly the trap `config('warehouse.
            // schema_version')`'s own comment warns against: "the derivation has
            // to keep reading every version it ever wrote, forever."
            $schemaVersion = is_int($decoded['schema_version'] ?? null) ? $decoded['schema_version'] : 0;

            CanonicalJson::assertShape($decoded, L0Line::keysFor($schemaVersion), 'An L0 line read from '.$path);

            $lines[] = $decoded;
        }

        return $lines;
    }

    /**
     * ⛔ **THIS METHOD REFUSES WITH `false` AND RAISES ON A BROKEN DEPLOYMENT,
     * AND THE DISK IS RESOLVED OUTSIDE THE `try` TO KEEP THOSE APART** (9426).
     * The shape was here before anybody wrote the reason down, and a reviewer
     * reading the `catch` below — *"refuses rather than throws"* — reasonably
     * took the whole method for one that never raises. It is not: that sentence
     * describes the loop body, and {@see self::disk()} sits above it on purpose.
     *
     * **What `false` means**: the store would not give this account's objects
     * up right now — one prefix, one night, and `ExecuteTenantDeletions` moves
     * to the next account. **What raising means**: `WAREHOUSE_L0_DISK` names a
     * disk `config/filesystems.php` does not have, or the `s3` block carries a
     * value the client rejects at construction. That is true for every account
     * on the platform and has a one-line fix.
     *
     * ⛔ **THE DECIDING FACT IS THAT A REFUSAL RINGS NOTHING.**
     * `TenantDeletionOutcome::ObjectStoreRefused` is a deferral: counted,
     * printed once with `warn()`, no bell. Only the sweep's `catch` rings
     * [[\App\Enums\OperatorAlertKind::TenantErasureFailed]]. So folding a
     * configuration fault into `false` would stop every statutory erasure on
     * the platform against `28` §9.5's clock and put the only trace in the
     * console output of a scheduled command.
     *
     * ⚠️ **AND THE ARGUMENT IS NOT THE ONE ITS NEAREST SIBLING HAS.**
     * [[\App\Services\Campaigns\CampaignMedia::purgeAllFor]] hoists its
     * resolution because `disk()` there fails closed on the **tenant**, and a
     * missing tenant is a programming error. This `disk()` has no
     * `Tenancy::idOrFail()` and never needed one — L0 is partitioned on a
     * `business_id` argument — so it carried the shape with none of the reason
     * until this paragraph. **The shape is right; the reason was missing.**
     *
     * ⚠️ **`deleteDirectory()`'s ANSWER IS DISCARDED ON PURPOSE AND
     * `allFiles()` IS THE VERIFICATION.** A measured client answers
     * `deleteDirectory()` with `false` and `allFiles()` with a **throw** on an
     * unreachable store (9424), so the listing is the arm that cannot silently
     * agree that nothing is left.
     *
     * @throws \InvalidArgumentException when the configured disk cannot be built
     */
    public function purgeFor(int $businessId): bool
    {
        // ⛔ **OUTSIDE THE `try` DELIBERATELY — SEE THE DOCBLOCK.** Moving this
        // one line inside turns a broken deployment into "the bucket refused",
        // which defers every erasure on the platform and rings nothing.
        $disk = $this->disk();
        $clear = true;

        // Every non-PHI class, unbounded by date — see the interface docblock
        // for why this is the opposite shape from paths(). PHI is never walked:
        // store() above refuses a Phi batch outright, so no object under
        // class=phi/ for any business has ever existed to delete.
        foreach (DataClassification::cases() as $class) {
            if ($class === DataClassification::Phi) {
                continue;
            }

            $prefix = 'class='.$class->value.'/business='.$businessId;

            try {
                $disk->deleteDirectory($prefix);

                if ($disk->allFiles($prefix) !== []) {
                    $clear = false;
                }
            } catch (Throwable) {
                // ⚠️ Refuses rather than throws — ExportBuilder::purgeAllFor()'s
                // rule, for the same reason: this is called from a sweep that
                // walks every due deletion, and one unreachable prefix must not
                // abandon the rest of the queue.
                $clear = false;
            }
        }

        return $clear;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('warehouse.l0_disk'));
    }
}
