<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\ShowStorageFootprint;
use App\Services\Storage\StorageFootprint;
use App\Services\Storage\StorageRetention;

/**
 * The kinds of stored object {@see StorageFootprint} can count — the subject of
 * decision 4687.
 *
 * ⛔ **THIS OPENED BY CALLING ITSELF A CENSUS OF WHAT THIS APPLICATION STORES,
 * AND IT IS NOT ONE — BOTH READINGS KEPT AND DATED, 2026-08-23.** The first
 * sentence read *"the five kinds of object this application puts in a bucket on
 * a tenant's behalf"*, and two separate things are wrong with it.
 *
 * ⚠️ **(a) TWO OF THE FIVE DO NOT REACH A BUCKET ON THE SHIPPED
 * CONFIGURATION.** `KnowledgeUploads::accept()` writes to
 * `config('filesystems.default')` and `CampaignMedia::disk()` is the literal
 * `local`; `.env.example` ships `FILESYSTEM_DISK=local`. What every case here
 * really shares is a **byte column on a tenant-owned row**, which is the only
 * thing a column-sum footprint can add up — not a bucket. (What a running
 * install has bound is not derivable from this repository; `CLAUDE.md`'s
 * seed-is-not-a-deployment rule.)
 *
 * ⛔ **(b) THE PIXEL L0 ARCHIVE IS A SIXTH STORE, IT IS WRITTEN PER BUSINESS,
 * AND IT IS DELIBERATELY NOT A CASE** — see the ruling below. 8863 already
 * refused to *generate* the erasure note from this enum for exactly that
 * reason, and 8875 raised the omission; **what neither says is that adding the
 * case is the wrong remedy**, which is what this docblock now records.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE PIXEL L0 ARCHIVE IS PERMANENTLY OUTSIDE THIS VOCABULARY, AND 4902's
 * "EXEMPTION WITH A DEADLINE" IS CLOSED THE OTHER WAY (2026-08-23)
 * ---------------------------------------------------------------------------
 * 4902 deferred the question on the ground that *"nothing in `app/` calls
 * `L0Archive::store()` outside the replay command and the tests, so the sum is
 * currently short by zero"*, and left the debt to *"whoever builds the
 * collector"*. **The collector landed on a parallel branch the same night and
 * the debt was never paid**; the sentence has been false ever since. Three
 * reasons a case is the wrong way to pay it:
 *
 *  1. **There is no per-object row to sum, and the one row that names an L0
 *     object cannot stand in for one.** `l1_events.l0_path` is tenant-owned and
 *     names the object — but it is many rows per object, it is **zero** rows
 *     for an object whose payload derives nothing (`ArchivePixelBatchJob`:
 *     *"an empty derivation is not an error"*), and the derived layer is pruned
 *     on a horizon while the object it names is kept for ever. **A count taken
 *     from it would FALL while the archive GREW** — a metric that moves the
 *     wrong way is worse than the omission this enum's own rule is about.
 *  2. **Listing the bucket is unbounded and paid.** `Filesystem::allFiles()`
 *     answers paths, so a byte total needs a metadata call per object, and the
 *     archive is **one gzipped object per beacon per business**. That is an
 *     unbounded vendor call inside an Ops command with no counter on it, which
 *     is 3297's rule pointed at the instrument built to satisfy 3297.
 *  3. ⛔ **A CASE WOULD MANUFACTURE AN OPERATOR-SETTABLE RETENTION PERIOD ON
 *     THE RECORD OF TRUTH, AND THIS IS THE ONE THAT SETTLES IT.** Every
 *     non-`Export` case gets a `storage.retention_days.<value>` key from
 *     {@see self::retentionKey()}, and `StorageTest` then requires that key
 *     declared in `DefaultsManifest` **and** requires
 *     {@see StorageRetention::prune()} to name the kind. Both arms are wrong
 *     here: one that deletes destroys the only copy of somebody's traffic and
 *     makes every range before the cut permanently unreplayable, and one that
 *     returns `skipped` is a period an operator can set that deletes nothing —
 *     a live-looking control on a dead sweep. **L0's period is an owner ruling
 *     with a bucket lifecycle rule behind it (7706), not a `match` arm.**
 *
 * ✅ **SO THE COST IS PAID AT THE READERS INSTEAD, AND BOTH OF THEM NAME IT.**
 * {@see ShowStorageFootprint} prints that the archive is not in the total and
 * cannot be sized from there, and [[\App\Console\Commands\PruneStoredObjects]]
 * prints that it is outside the sweep entirely — and
 * `tests/Feature/Architecture/StorageTest.php` fails the build if either stops
 * saying so. The omission stays; what ends is its being **quiet**, which is the
 * half this enum's bolded rule below is actually about.
 *
 * ⚠️ **THIS IS A MEASUREMENT VOCABULARY AND NOT A CEILING VOCABULARY, WHICH IS
 * THE WHOLE POINT OF 4760.** No case here can refuse a write. Three of the five
 * *may not* be refused on a quota by rules already written — an export because
 * `28` §3.7 forbids delaying, gating or degrading one, and the two inbound kinds
 * because nobody at the tenant chose to spend them and the person whose data
 * would be dropped is not the person who filled the bucket. The cases exist so
 * an operator can see which kind is growing, not so anything can say no.
 *
 * ⛔ **EVERY CASE MUST HAVE A BYTE COLUMN OR IT SILENTLY UNDER-COUNTS.** A metric
 * that quietly omits a kind is worse than no metric: it reads as complete and is
 * not. Two of these had no size column at all when this enum was written —
 * `knowledge_sources` and `campaign_recipients` — and 4762 is the record of why
 * the campaign one mattered most. Adding a sixth kind here means adding its
 * column and its writer in the same change, which
 * `tests/Feature/Architecture/StorageTest.php` makes structural rather than
 * remembered.
 *
 * A string cast to a PHP backed enum, never a database enum — `CLAUDE.md`. It
 * backs no column today; the backing values exist so the Ops command's output
 * and any later row keyed on a kind agree on one spelling.
 */
enum StoredObjectKind: string
{
    /**
     * A complete ZIP of the account, built by `ExportBuilder`.
     *
     * ⛔ **THE ONE KIND THAT DELETES ITSELF ON A PERIOD NOBODY MAY MOVE.**
     * `exports:prune` sweeps every row past `ExportBuilder::LINK_EXPIRY_DAYS`,
     * object first and row after, so this line falls back to zero on its own. It
     * is also the one kind a ceiling may never touch (4761) and the one kind
     * with no retention key (4941) — see {@see self::retentionKey()}. ⚠️ **This
     * used to read "the one kind that already deletes itself" and the other four
     * can now delete too** (4940); what stays unique is that its seven days are
     * fixed in code because they were published.
     */
    case Export = 'export';

    /** A document an owner uploaded to the Business Brain, through `KnowledgeUploads`. */
    case KnowledgeUpload = 'knowledge_upload';

    /** A per-recipient rendered MMS image, written by `CampaignMedia`. */
    case CampaignMedia = 'campaign_media';

    /** A photograph or clip a customer texted in, captured by `CaptureInboundMediaJob`. */
    case InboundMedia = 'inbound_media';

    /** Voicemail audio pulled off the carrier by `FetchVoicemailRecordingJob`. */
    case VoicemailRecording = 'voicemail_recording';

    case WhatsappMedia = 'whatsapp_media';

    /**
     * What an operator reads on the Ops command's output.
     *
     * Outcome language (`22`): the name of the thing an operator is looking at,
     * never the table it came from.
     */
    public function label(): string
    {
        return match ($this) {
            self::Export => 'Account exports',
            self::KnowledgeUpload => 'Knowledge uploads',
            self::CampaignMedia => 'Campaign images',
            self::InboundMedia => 'Inbound media',
            self::VoicemailRecording => 'Voicemail audio',
            self::WhatsappMedia => 'WhatsApp media',
        };
    }

    /**
     * The registry key holding how many days an object of this kind is kept —
     * or null for the one kind whose period is not an operator's to move.
     *
     * ⛔ **`isPruned()` STOOD HERE AND IS GONE, BECAUSE IT STOPPED BEING
     * ANSWERABLE AT COMPILE TIME** (4940). It read `$this === self::Export` and
     * meant *"does anything in this application ever delete one of these"*. 4763
     * left that at one of five and called the other four a retention question;
     * {@see StorageRetention} is the answer, and it makes the question a
     * **runtime** one — a kind is pruned if and when an operator has stated a
     * period for it, so no `match` in an enum can say. Keeping the old method
     * would have left it answering `false` for four kinds a live period was
     * deleting, which is 2505's shape in a method signature. The replacement is
     * this key plus {@see StorageRetention::periodFor()}, and
     * {@see ShowStorageFootprint} prints what that pair answers rather than what
     * this file assumes.
     *
     * ⛔ **EXPORT ANSWERS NULL AND THAT IS NOT AN OVERSIGHT** (4941). An export
     * already deletes itself at `ExportBuilder::LINK_EXPIRY_DAYS`, and that
     * seven days is the window `28` §3.7 and the Terms both promise the person
     * who asked for it. Giving it a key would let an operator shorten a
     * published promise from an Ops screen — and `28` §3.7 forbids an export
     * being delayed, gated or degraded at all (4761). So the one kind that has a
     * period today is the one kind whose period is fixed in code, which reads
     * backwards and is exactly right: it is the only one anybody was told.
     *
     * ⚠️ **THE BACKING VALUE IS THE KEY SUFFIX**, which is what this enum's
     * backing values were reserved for — see the class docblock, which already
     * said they exist so that "any later row keyed on a kind" agrees on one
     * spelling. A sixth kind therefore gets its key for free, and
     * `tests/Feature/Architecture/StorageTest.php` fails the build until the
     * pruner that reads it exists.
     */
    public function retentionKey(): ?string
    {
        return match ($this) {
            self::Export => null,
            self::KnowledgeUpload,
            self::CampaignMedia,
            self::InboundMedia,
            self::VoicemailRecording,
            self::WhatsappMedia => StorageRetention::KEY_PREFIX.$this->value,
        };
    }
}
