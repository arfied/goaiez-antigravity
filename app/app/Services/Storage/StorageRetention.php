<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Console\Commands\PruneStoredObjects;
use App\Enums\InboundMediaOutcome;
use App\Enums\StoredObjectKind;
use App\Enums\VoicemailAudioState;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Jobs\Voice\FetchVoicemailRecordingJob;
use App\Models\CampaignRecipient;
use App\Models\InboundMedia;
use App\Models\KnowledgeSource;
use App\Models\Voicemail;
use App\Services\Config\DefaultsRegistry;
use App\Services\Conversations\ConversationThreads;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes stored objects that are older than the period an operator has stated
 * for their kind — decisions 4940–4945, answering 4768.
 *
 * ## ⛔ THE ONE PROPERTY THAT MATTERS MORE THAN EVERY OTHER
 *
 * **AN UNSET PERIOD DELETES NOTHING.** Not "nothing much", not "nothing
 * important" — nothing, for that kind, on every run, for ever, until somebody
 * types a number. {@see self::periodFor()} answers `null` for a kind with no
 * stated period and {@see self::prune()} returns {@see StorageSweep::skipped()}
 * without opening a query.
 *
 * The alternative — reading a missing period as `0` days — is not a mild bug. It
 * would delete **every object of that kind, for every tenant, on the first
 * scheduled run**: other people's photographs, other people's voicemail audio,
 * and the documents an owner uploaded, destroyed irreversibly by a cron entry,
 * against a suite that stayed green because every test seeds its own period. So
 * the empty state is asserted rather than believed:
 * `tests/Feature/Storage/StorageRetentionTest.php` drives it red by mutation,
 * and the manifest entry beside the keys carries the same warning at the point
 * somebody would be tempted to add a seed.
 *
 * ⚠️ **AND THAT IS THE OPPOSITE DIRECTION FROM THE SENDING CEILING** (4604,
 * 4942). An unstated mail ceiling refuses every send, because the conservative
 * direction on a limit is to do less. The conservative direction on a *deletion
 * schedule* is also to do less, and "less" here means **not deleting**. The two
 * keys fail closed in opposite directions because "closed" is a statement about
 * consequence, never about a value being zero.
 *
 * ## The period is the owner's, the mechanism is ours
 *
 * 2409's posture, used repeatedly here. Nobody has ruled on how long this
 * platform keeps a customer's inbound photograph, and this class may not invent
 * one — for a deletion schedule the conservative answer is refusing to quote a
 * number, exactly as it is for a price (502). What is buildable without the
 * ruling is everything else, and it is built: the keys exist, the sweep exists,
 * the row states exist, `storage:prune` is scheduled, and the day a period is
 * set it starts working with no deploy.
 *
 * ⚠️ **KEY AND READER SHIP TOGETHER, WHICH IS 272's RULE AND NOT A PREFERENCE.**
 * A retention key with no pruner is the seventeenth instance of the failure
 * `CLAUDE.md` opens with: an operator sets it, the screen says a number, and
 * nothing deletes anything. The keys in `DefaultsManifest::declaredWithoutSeed()`
 * and this class were written in one change and neither is useful alone.
 *
 * ## What the sweep does to a row
 *
 * ⛔ **THE OBJECT GOES AND THE ROW STAYS.** Deleting the row would destroy the
 * record that a customer sent a photograph or that somebody left a voicemail,
 * which is the fact `inbound_media`'s whole design exists to keep answerable a
 * year later. Deleting the object and leaving the row untouched is worse in a
 * different way: {@see StorageFootprint} counts *rows that name an object*, so a
 * row still naming a deleted file makes the footprint report bytes that are not
 * there — a metric that lies in the direction of "we still hold your data".
 *
 * So both halves move: the path and the size column go null, and where the table
 * has a vocabulary for what happened to the object, it gains a `Pruned` case
 * saying so ({@see InboundMediaOutcome::Pruned},
 * {@see VoicemailAudioState::Pruned}). The footprint then falls **with no change
 * to `StorageFootprint` at all**, because its predicate was already "a row that
 * names an object" — 4764's correction, which turns out to have been the right
 * predicate for a reason nobody had yet needed.
 *
 * ⚠️ **THIS IS NOT ERASURE AND MUST NOT BE DESCRIBED AS IT.** `29` §2 requires
 * erasure to be crypto-shred; `TenantDeletion`'s docblock records that **there
 * is no per-identity DEK in this application** and that what ships is *delete
 * what cascades*. Pruning removes bytes from a bucket. It does not touch a
 * voicemail's transcript, a message's text, or a contact — a caller's words
 * survive their recording on purpose ({@see VoicemailAudioState::Pruned}), and
 * an erasure request is still `DataRequests`' path and not this one. What
 * pruning genuinely does is reduce how much there is to shred; with no DEK, an
 * object deleted is the only sense in which any of these bytes ever go away.
 *
 * ## The tenant boundary
 *
 * ⛔ **FAIL-CLOSED ON A TENANT, WITH NO BUSINESS ID PARAMETER**, exactly as
 * {@see StorageFootprint} — and the argument is stronger here, because this one
 * deletes. Every one of the four tables is RLS `ENABLE`+`FORCE`d on
 * `app.business_id`, so there is no cross-tenant query to be had and no version
 * of this that sweeps the platform in one statement. A signature taking an id
 * would invite a caller to loop it; {@see PruneStoredObjects} instead walks
 * owners and wraps each account in `Tenancy::actingAs()`, which is the sixth
 * copy of `RefreshOauthTokens`' shape and the second one that deletes.
 *
 * ⚠️ **A SWEEP THAT COULD READ ACROSS TENANTS WOULD BE A LEAK; ONE THAT COULD
 * *DELETE* ACROSS TENANTS WOULD BE WORSE THAN A LEAK.** That is why the
 * enumeration is not extracted or generalised here: `PruneTenantExports` already
 * argues that a deleting sweep is the worst possible first customer for a shared
 * abstraction, and this is the second one.
 */
final class StorageRetention
{
    /**
     * The registry key family, one row per kind — see
     * {@see StoredObjectKind::retentionKey()}, which appends the backing value.
     */
    public const string KEY_PREFIX = 'storage.retention_days.';

    /**
     * Rows per batch. Matches `PrunePublicAudits::CHUNK`'s reasoning at a tenth
     * of the size, because each row here costs a network round trip to the
     * object store rather than a share of one `DELETE`.
     */
    public const int CHUNK = 50;

    private function chunk(): int
    {
        return $this->defaults->int('storage.retention.chunk');
    }

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * How many days this kind is kept, or null when nobody has said.
     *
     * ⛔ **NULL IS A REAL ANSWER AND IT MEANS "DELETE NOTHING".** See the class
     * docblock; this is the method the whole design rests on.
     *
     * ⚠️ **A ROW OF `0` OR LESS READS AS UNSET RATHER THAN AS "DELETE
     * EVERYTHING"**, and that is deliberate on the same ground
     * `DefaultsRegistry::value()` states for a blanked budget — *an operator who
     * blanked a figure meant to remove a number, not to remove a limit*. Here
     * the stakes are one-directional: an operator who typed `0` into a retention
     * box, meaning "off", would otherwise have every object of that kind deleted
     * on the next run. There is no legitimate reading of `0` days that this
     * application should act on, so it is not acted on.
     *
     * ⚠️ **`intOr($key, 0)` READS THE STORED ROW AND NOTHING ELSE**, which is
     * `MailQuota::ceiling()`'s split and for the same reason: `int()` throws on a
     * key with no integer seed, and "this kind has no seed" is precisely the
     * state that has to be answerable. No `seedOf()` fallback follows it, because
     * unlike the mail ceiling there is no seed to fall back to on any kind — by
     * 4942, deliberately and permanently.
     */
    public function periodFor(StoredObjectKind $kind): ?int
    {
        $key = $kind->retentionKey();

        if ($key === null) {
            // Export. Its seven days are published and fixed in ExportBuilder;
            // `exports:prune` is its sweep and this class is not (4941).
            return null;
        }

        $stated = $this->defaults->intOr($key, 0);

        return $stated > 0 ? $stated : null;
    }

    /**
     * Every kind's stated period, for an operator to read.
     *
     * @return array<string, int|null> the kind's backing value => days, or null
     */
    public function periods(): array
    {
        $periods = [];

        foreach (StoredObjectKind::cases() as $kind) {
            $periods[$kind->value] = $this->periodFor($kind);
        }

        return $periods;
    }

    /**
     * Delete this kind's objects past their period, for the tenant in context.
     *
     * ⚠️ **A FAILED DELETE IS NOT A FAILED RUN**, and the row is left exactly as
     * it was so tomorrow tries again — `PruneTenantExports`' rule, and the reason
     * the object is deleted *before* the row is rewritten. The other ordering
     * loses the only pointer to the file: nothing would ever look for it again,
     * and it would sit in a bucket that no footprint can see, since an orphan is
     * by construction invisible to {@see StorageFootprint}.
     */
    public function prune(StoredObjectKind $kind): StorageSweep
    {
        // Fail-closed. No argument, and this throws rather than sweeping
        // whatever the process happened to be looking at.
        Tenancy::idOrFail();

        $days = $this->periodFor($kind);

        if ($days === null) {
            // ⛔ THE LINE THE WHOLE LANE IS ABOUT. No query is opened, so there
            // is no path from here to a delete at all when a period is unset.
            return StorageSweep::skipped($kind);
        }

        $cutoff = now()->subDays($days);

        return match ($kind) {
            StoredObjectKind::Export => StorageSweep::skipped($kind),
            StoredObjectKind::InboundMedia => $this->pruneInboundMedia($cutoff),
            StoredObjectKind::VoicemailRecording => $this->pruneVoicemails($cutoff),
            StoredObjectKind::KnowledgeUpload => $this->pruneKnowledgeUploads($cutoff),
            StoredObjectKind::CampaignMedia => $this->pruneCampaignMedia($cutoff),
            StoredObjectKind::WhatsappMedia => $this->pruneWhatsappMedia($cutoff),
        };
    }

    private function pruneWhatsappMedia(CarbonInterface $cutoff): StorageSweep
    {
        $r = app(ConversationThreads::class)->pruneStoredMedia($cutoff, $this->chunk());

        return new StorageSweep(
            kind: StoredObjectKind::WhatsappMedia,
            pruned: $r['pruned'],
            refused: $r['refused'],
            skipped: false
        );
    }

    /**
     * A customer's photograph, and the strongest case in the set.
     *
     * ⚠️ **THE DISK COMES OFF THE ROW.** `inbound_media` records the disk it
     * wrote to, and a sweep that assumed `CaptureInboundMediaJob::DISK` would
     * silently fail to delete anything written before that constant last moved —
     * counting them as refused for ever while the file stayed.
     *
     * ⚠️ **EVERY BYTE COLUMN GOES, THE CHECKSUM INCLUDED.** The creating
     * migration's CHECK requires exactly this shape of a non-`stored` row, and
     * {@see InboundMediaOutcome::Pruned} carries the argument for why keeping the
     * checksum was refused rather than accommodated.
     */
    private function pruneInboundMedia(CarbonInterface $cutoff): StorageSweep
    {
        return $this->sweep(
            StoredObjectKind::InboundMedia,
            InboundMedia::query()
                ->whereNotNull('storage_path')
                ->where('created_at', '<', $cutoff),
            pathColumn: 'storage_path',
            // The only kind whose disk comes off the row rather than out of a
            // constant, and the only one where that matters.
            diskColumn: 'storage_disk',
            diskFallback: CaptureInboundMediaJob::DISK,
            cleared: [
                'outcome' => InboundMediaOutcome::Pruned,
                'storage_path' => null,
                'storage_disk' => null,
                'content_type' => null,
                'byte_size' => null,
                'checksum' => null,
            ],
        );
    }

    /**
     * Voicemail audio. The transcript is not touched — see
     * {@see VoicemailAudioState::Pruned}.
     */
    private function pruneVoicemails(CarbonInterface $cutoff): StorageSweep
    {
        return $this->sweep(
            StoredObjectKind::VoicemailRecording,
            Voicemail::query()
                ->whereNotNull('recording_path')
                ->where('created_at', '<', $cutoff),
            pathColumn: 'recording_path',
            // `voicemails` records no disk, so this is the constant the writer
            // uses — named through the job rather than repeated as a literal, so
            // moving the bucket moves both halves at once.
            diskColumn: null,
            diskFallback: FetchVoicemailRecordingJob::DISK,
            cleared: [
                'audio_state' => VoicemailAudioState::Pruned,
                'recording_path' => null,
                'recording_bytes' => null,
            ],
        );
    }

    /**
     * A document the owner uploaded to the Business Brain.
     *
     * ⚠️ **THE ROW, ITS CHUNKS AND ITS EMBEDDINGS ALL SURVIVE**, so the Business
     * Brain still answers from this source after its file is gone — the file is
     * what was ingested, the chunks are the product, and only the first is a
     * stored object. There is no `Pruned` state to write because
     * `knowledge_sources` has no vocabulary for one: a `Url` source legitimately
     * carries no `file_path` at all, which is 4764's finding, so a null path is
     * already an ordinary shape on this table rather than a new one.
     *
     * ⚠️ **IT IS ALSO THE WEAKEST CASE FOR PRUNING AT ALL**, and it is the
     * owner's to decline: this is the tenant's own material rather than somebody
     * else's personal data, and deleting it on a timer takes away something they
     * put there deliberately. Leaving the key unset is a supported, permanent
     * answer for any kind (4942), and this is the kind most likely to want it.
     *
     * ✅ **NOTHING RE-READS THE FILE, AND THAT WAS CHECKED RATHER THAN ASSUMED.**
     * `KnowledgeIngestor::ingest()` is the only reader of `file_path` in `app/`,
     * `IngestKnowledgeSourceJob` is its only caller, and that job has exactly one
     * dispatch site — `KnowledgeUploads::accept()`, at upload. There is no
     * scheduled re-ingest, so a pruned source is never re-read and its chunks and
     * embeddings are never rebuilt from a file that is gone. ⚠️ **And the
     * ingestor already anticipates both shapes**: a null path answers
     * `IngestOutcome::unreadable('no_file')` and a missing object answers
     * `unreadable('file_missing')`, whose own comment says *"the object is
     * gone"*. So this is a state that path was already written for, not one this
     * sweep introduces.
     */
    private function pruneKnowledgeUploads(CarbonInterface $cutoff): StorageSweep
    {
        return $this->sweep(
            StoredObjectKind::KnowledgeUpload,
            KnowledgeSource::query()
                ->whereNotNull('file_path')
                ->where('created_at', '<', $cutoff),
            pathColumn: 'file_path',
            diskColumn: null,
            // `KnowledgeUploads::accept()` writes to the default disk by name, so
            // this reads the same config rather than a second spelling of it.
            diskFallback: (string) config('filesystems.default'),
            cleared: ['file_path' => null, 'byte_size' => null],
        );
    }

    /**
     * A rendered per-recipient MMS image.
     *
     * ⚠️ **THE SEND RECORD IS UNTOUCHED** — status, provider message id and
     * `sent_at` are what three CHECK constraints on this table are about, and
     * they have nothing to do with the picture. Only the two media columns move.
     *
     * ⚠️ **AND THIS IS THE KIND WHOSE ARGUMENT IS PUREST COST**, which
     * `CLAUDE.md`'s tiebreaker ranks last of three. It is also the largest writer
     * in the application — one object per recipient (4762) — so it is the kind
     * where a period would recover the most bytes and destroy the least.
     *
     * ✅ **NO TWO RECIPIENTS SHARE AN OBJECT, AND THAT WAS CHECKED RATHER THAN
     * ASSUMED** — it is the one hazard that would make a per-row sweep delete
     * somebody else's picture. `CampaignMedia::renderFor()` writes
     * `campaign-media/{campaign}/{Str::random(40)}.jpg`, a fresh path per render,
     * and re-renders only when the recipient's own path is missing from the disk.
     * ⚠️ **The campaign's `base_image_path` is a different object, is NOT
     * touched here, and belongs to no `StoredObjectKind` — so it is outside both
     * the footprint and this sweep.** ⛔ **THAT IS SAFE TODAY ONLY BECAUSE
     * NOTHING WRITES IT** (4956): `Campaigns::create()` takes
     * `?string $baseImagePath = null` and **no caller in `app/` ever supplies
     * one** — no controller, no Livewire component, no `UploadedFile::store()`
     * anywhere in the application. It is 272's shape, and it is why
     * `StorageTest`'s write lint honestly matches five files. **Whoever builds
     * the uploader adds a sixth `StoredObjectKind`, its size column, its
     * retention key and its arm here, in the same change** — and should know
     * that `->store()` on an `UploadedFile` is a write that lint's pattern
     * cannot see, because the receiver is not a filesystem.
     */
    private function pruneCampaignMedia(CarbonInterface $cutoff): StorageSweep
    {
        return $this->sweep(
            StoredObjectKind::CampaignMedia,
            CampaignRecipient::query()
                ->whereNotNull('media_path')
                ->where('created_at', '<', $cutoff),
            pathColumn: 'media_path',
            diskColumn: null,
            // `CampaignMedia::disk()` is private and resolves to `local`. The
            // literal is here rather than reflected out of that class, and
            // `StorageTest` is what keeps the two in step.
            diskFallback: 'local',
            cleared: ['media_path' => null, 'media_bytes' => null],
        );
    }

    /**
     * Object first, row after — and the row only if the object went.
     *
     * ⚠️ **`chunkById` RATHER THAN `chunk`**, because this loop rewrites the very
     * column the query filters on: an offset-paged sweep would skip half its
     * subject as the result set shrank underneath it. Keyset paging by id is
     * unaffected, and a row the store refused keeps its path and simply falls
     * behind the cursor, which is what makes "tomorrow tries again" true.
     *
     * ⚠️ **COLUMN NAMES RATHER THAN CLOSURES OVER THE MODEL.** Two accessor
     * callbacks read better at the call site and cost a generic signature that
     * PHPStan cannot narrow, so every caller would have been passing a closure
     * typed against a subclass into a parameter typed against `Model`. Strings
     * are also what `$cleared` already is, so one mechanism reads and writes the
     * same columns and a typo in either half fails the same way.
     *
     * @param  Builder<covariant Model>  $query
     * @param  ?string  $diskColumn  the row's own disk column, where it has one
     * @param  string  $diskFallback  the writer's constant, for the three kinds
     *                                that record no disk
     * @param  array<string, mixed>  $cleared  columns to null, plus any state
     *                                         column's `Pruned` case
     */
    private function sweep(
        StoredObjectKind $kind,
        Builder $query,
        string $pathColumn,
        ?string $diskColumn,
        string $diskFallback,
        array $cleared,
    ): StorageSweep {
        $pruned = 0;
        $refused = 0;

        $sweepBatch = function (Collection $rows) use (
            &$pruned,
            &$refused,
            $pathColumn,
            $diskColumn,
            $diskFallback,
            $cleared,
        ): void {
            foreach ($rows as $row) {
                $objectPath = $row->getAttribute($pathColumn);

                $objectDisk = $diskColumn === null
                    ? $diskFallback
                    // ⚠️ FALLING BACK RATHER THAN REFUSING when the row's own
                    // disk column is null: the CHECK makes that shape
                    // unreachable on `inbound_media` today, and a sweep that
                    // silently skipped it for ever if it ever became reachable
                    // would leave the object behind with nothing pointing at it.
                    : ($row->getAttribute($diskColumn) ?? $diskFallback);

                if (! is_string($objectPath) || $objectPath === ''
                    || ! is_string($objectDisk) || $objectDisk === '') {
                    // A row that names no reachable object. Nothing to delete
                    // and nothing to claim: leaving it alone is the only honest
                    // move, since rewriting it would say we deleted something we
                    // never found.
                    $refused++;

                    continue;
                }

                if (! $this->deleteObject($objectDisk, $objectPath)) {
                    $refused++;

                    continue;
                }

                $row->forceFill($cleared)->save();

                $pruned++;
            }
        };

        $query->orderBy('id')->chunkById($this->chunk(), $sweepBatch);

        return new StorageSweep(kind: $kind, pruned: $pruned, refused: $refused, skipped: false);
    }

    /**
     * True when the object is gone — including when it was already gone.
     *
     * ⚠️ **A MISSING OBJECT COUNTS AS DELETED, AND THAT IS NOT LENIENCY.** The
     * bytes are not there; refusing to update the row would leave it naming a
     * file that does not exist for ever, so the footprint would keep reporting
     * it and every future run would keep failing on it. Laravel's `delete()`
     * answers true for a path that is already absent, which is the behaviour
     * this relies on.
     *
     * ⚠️ **EVERY `Throwable` IS SWALLOWED INTO `false`**, which is
     * `ExportBuilder::deleteObject()`'s posture: an unreachable bucket must not
     * turn a scheduled sweep into a red queue, and the count of refusals is what
     * carries the signal instead. It is warned on rather than logged silently —
     * 1993's finding was that a silent refusal printed an affirmative "nothing to
     * do".
     */
    private function deleteObject(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->delete($path);
        } catch (Throwable) {
            return false;
        }
    }
}
