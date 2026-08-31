<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Enums\SupportWriteSubject;
use App\Exceptions\KnowledgeUploadRefused;
use App\Http\Middleware\Impersonating;
use App\Jobs\IngestKnowledgeSourceJob;
use App\Models\KnowledgeSource;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Takes a file off the upload screen, stores it, and queues the read.
 *
 * ⚠️ **THE STORAGE KEY IS BUILT HERE AND NEVER TAKEN FROM THE CALLER.** The
 * multi-tenancy skill is explicit that an object key is a naming convention
 * rather than a boundary — anything holding the bucket credentials can read any
 * object — so the key is derived server-side from the tenant in context and the
 * random component is a UUID rather than the uploaded filename. Two consequences
 * follow, and both are the point:
 *
 *   - **The original filename never reaches the key.** It is attacker-controlled
 *     on a path that becomes a filesystem path, and it is also *customer data*:
 *     "Patient-Handout-Smith.txt" in an object key surfaces in logs, in presigned
 *     URLs and in error traces, which is three more places than the title column.
 *   - **The key is opaque.** Nothing can be guessed from a neighbouring tenant's.
 *
 * ⚠️ **THE TITLE IS THE OWNER'S FILENAME AND THAT IS DELIBERATE.** They need to
 * recognise their own document on the screen, and `knowledge_sources.title` is a
 * tenant-scoped column with RLS under it — which the object key is not.
 *
 * ⚠️ **NO VENDOR CALL HAPPENS HERE.** This class stores bytes, writes a row and
 * dispatches; every model call is inside the queued job. That is `29` §2's
 * synchronous-path rule, and `ArchitectureTest` makes it structural rather than
 * remembered by refusing any mention of `AiRouter` under `Livewire`.
 */
final class KnowledgeUploads
{
    /**
     * The largest file the screen accepts, in kilobytes.
     *
     * Shared with the Livewire validation rule so the two cannot drift — a
     * screen that accepts a file the service then refuses is a support ticket
     * with no error message attached to it.
     */
    public const int MAX_KILOBYTES = 2_048;

    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * Store the file and queue it to be read.
     *
     * Returns the source row so the screen can show it immediately, in its
     * `pending` state, rather than leaving the owner wondering whether the
     * upload landed.
     *
     * @throws KnowledgeUploadRefused when the store would not take the bytes —
     *                                raised before any row is written, so an
     *                                upload that did not land leaves no record
     *                                claiming it did
     */
    public function accept(UploadedFile $file, ?int $locationId = null): KnowledgeSource
    {
        $businessId = Tenancy::idOrFail();

        // Opaque, tenant-prefixed, and built here. See the class docblock for
        // why none of those three is negotiable.
        $path = 'knowledge/'.$businessId.'/'.Str::uuid()->toString();

        $bytes = (string) file_get_contents($file->getRealPath());

        $disk = (string) config('filesystems.default');

        // ⛔ **THE RETURN IS CHECKED, AND IT WAS NOT** (9406). Every disk in
        // `config/filesystems.php` is `'throw' => false`, so a store that
        // refuses this write answers `false` — and the `create()` below used to
        // run regardless, writing `file_path` for an object that is not there
        // and `byte_size` for bytes nothing holds. It is 1903's shape on the
        // upload screen: the row is the promise, and the promise was made
        // whether or not the bytes landed.
        //
        // ⛔ **REFUSED BEFORE THE ROW, WHICH IS THE WHOLE OF THE REMEDY.** A
        // check after the insert would leave the row it exists to prevent —
        // and `StorageFootprint` sums `byte_size`, so an operator's total would
        // be larger than the bucket by exactly the files that never landed.
        //
        // ⚠️ **AND THE OLD BEHAVIOUR WAS NOT MERELY SILENT.**
        // `KnowledgeIngestor::ingest()` meets the missing object and answers
        // `unreadable` — the status whose meaning here is *"waiting will not
        // help"* — so the owner was told their own file was the problem when
        // re-uploading was the fix.
        if (Storage::disk($disk)->put($path, $bytes) === false) {
            throw KnowledgeUploadRefused::notStored($path, $disk);
        }

        $source = KnowledgeSource::query()->create([
            'location_id' => $locationId,
            'type' => KnowledgeSourceType::Upload,
            'file_path' => $path,
            // ⚠️ **MEASURED FROM WHAT WAS WRITTEN, NOT FROM THE UPLOAD'S REPORTED
            // SIZE** (4762). `UploadedFile::getSize()` is a header a browser
            // supplies, and this column is the one {@see StorageFootprint} sums;
            // a footprint assembled from client-declared numbers is a metric an
            // uploader can move. `strlen()` on the same string the disk was given
            // cannot disagree with the object.
            'byte_size' => strlen($bytes),
            // Trimmed rather than trusted at full length: `title` is a
            // `varchar(255)` and a 400-character filename is a database error
            // rather than a validation message.
            'title' => Str::limit($this->displayName($file), 200, ''),
            'status' => KnowledgeSourceStatus::Pending,
            'is_active' => true,
            'version' => 1,
        ]);

        IngestKnowledgeSourceJob::dispatch($businessId, $locationId, (int) $source->id);

        $this->recordSupportWrite((int) $source->id);

        return $source;
    }

    /**
     * Add support's own audit row and owner-facing feed entry when this
     * upload happened inside an act-as session —
     * {@see Impersonation::recordWrite()}.
     *
     * ⚠️ **THE FIRST AUDIT ROW THIS CLASS HAS EVER WRITTEN.** Nothing above
     * calls `AuditService` — an ordinary owner upload is not audited at all —
     * so this is inert unless support did it, and it is the only record of a
     * support-authored knowledge upload anywhere in the platform.
     *
     * ⚠️ **INERT FOR AN ORDINARY OWNER UPLOAD, AND FOR A VIEW-ONLY SESSION.**
     * `current()` returns null the moment nobody is impersonating. A
     * view-only session cannot reach here at all: the `knowledge_sources`
     * INSERT above is refused by the read-only connection
     * {@see Impersonating} engages before this line
     * runs, so a non-null session here is always act-as.
     */
    private function recordSupportWrite(int $sourceId): void
    {
        $session = $this->impersonation->current();

        if ($session === null) {
            return;
        }

        $this->impersonation->recordWrite(
            $session,
            SupportWriteSubject::AssistantKnowledge,
            ['knowledge_source_id' => $sourceId],
        );
    }

    /**
     * Destroy every document this account uploaded to the Business Brain.
     *
     * ⛔ **CALLED BY `TenantDeletion::execute()` AND BY NOTHING ELSE**, before
     * the cascade, which is the last moment `knowledge_sources` can be read.
     * It is not `StorageRetention`'s prune with the period removed: that one
     * deletes an object and keeps the row for an account that still exists, and
     * this one deletes a prefix for an account that is about to stop existing.
     *
     * ⛔ **THE ERASURE USED TO MAKE THIS DOCUMENT PERMANENT** (8871). After the
     * account is destroyed there is no business for `PruneStoredObjects` to
     * walk and no row naming a path, so the operator's period reached a live
     * tenant's uploads and never an erased tenant's. ⚠️ **This is the one kind
     * of the four that is the owner's own material rather than somebody else's
     * personal data**, which is why `StorageRetention` calls it the weakest case
     * for pruning — and it is the strongest case for erasing, because the person
     * asked us to destroy their account and this is a file they put in it.
     *
     * ⚠️ **A PREFIX, NOT A ROW SWEEP** — `ExportBuilder::purgeAllFor()`'s
     * reasoning, and it is not hypothetical here: `accept()` puts the bytes and
     * *then* writes the row, so a failure between the two leaves an object no
     * row names.
     *
     * ⚠️ **THE DISK IS THE SAME EXPRESSION `accept()` WRITES WITH**, four
     * methods above, rather than a second spelling of it. `KnowledgeSource` has
     * no disk column, so a purge that guessed would silently miss everything.
     *
     * ⚠️ **NO UPLOAD, NO OBJECT-STORE CALL** — `ExportBuilder::purgeAllFor()`'s
     * guard, for its reason: an account that never uploaded a document must
     * not be able to fail its own erasure on a store it had no reason to touch.
     * The signal is the **kind of source** rather than the path, because pruning
     * nulls `file_path` and keeps the row, and a `Url` source legitimately never
     * had one.
     *
     * @return bool false when anything may still be there, which refuses the
     *              whole deletion rather than completing it with the document
     *              surviving
     */
    public function purgeAllFor(int $businessId): bool
    {
        $everUploaded = KnowledgeSource::query()
            ->where('type', KnowledgeSourceType::Upload)
            ->exists();

        if (! $everUploaded) {
            return true;
        }

        $prefix = 'knowledge/'.$businessId;

        try {
            $disk = Storage::disk((string) config('filesystems.default'));
            $disk->deleteDirectory($prefix);

            return $disk->allFiles($prefix) === [];
        } catch (Throwable) {
            // ⚠️ Refuses rather than throws, decision 823's rule: this is
            // reached from a sweep that walks every due deletion, and one
            // unreachable store must not abandon the rest of the queue.
            return false;
        }
    }

    /**
     * What the owner will recognise, with the path components removed.
     *
     * `basename()` rather than the raw client name: a browser can send
     * `../../etc/passwd` as a filename, and while this value never becomes a
     * path here, a column that holds one invites a future caller to treat it as
     * one.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = trim(basename($file->getClientOriginalName()));

        return $name === '' ? 'Uploaded document' : $name;
    }
}
