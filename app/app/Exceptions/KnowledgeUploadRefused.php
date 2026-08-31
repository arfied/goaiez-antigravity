<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Knowledge\KnowledgeUploads;
use RuntimeException;

/**
 * The bytes of an uploaded document did not land, so no row was written for it.
 *
 * ⛔ **THIS TYPE EXISTS BECAUSE `config/filesystems.php` SETS `'throw' => false`
 * ON EVERY DISK** (9406). A store that refuses a write answers `false` rather
 * than raising, and {@see KnowledgeUploads::accept()}
 * used to discard that answer and create the `knowledge_sources` row anyway —
 * with `file_path` naming an object that is not there and `byte_size` reporting
 * a length nothing on any disk has. The screen then said *"Added. We are reading
 * it now."*
 *
 * ⚠️ **AND THE ROW DID NOT STAY SILENT — IT SAID SOMETHING FALSE.**
 * `KnowledgeIngestor::ingest()` finds the object missing and answers
 * `unreadable`, whose whole meaning in this application is *"waiting will not
 * help"* — so the one place an owner could have learned anything told them their
 * own file was the problem, when the file was fine and re-uploading was the fix.
 * ⛔ **A wrong cause is worse than no cause**: it sends the person away from the
 * action that would have worked.
 *
 * ⚠️ **THROWN BEFORE THE ROW, NEVER AFTER.** The order is the whole remedy. A
 * refusal raised after the insert would leave exactly the row this type exists
 * to prevent, and `StorageFootprint` sums `byte_size`, so it would also leave an
 * operator's total larger than the bucket.
 *
 * ⚠️ **THE MESSAGE HERE IS FOR THE LOG AND THE OPERATOR, NOT FOR THE SCREEN.**
 * `App\Livewire\Account\Knowledge` supplies the owner's sentence itself —
 * `SiteChanges`' pattern — because a store failure is this platform's fault and
 * an owner-facing string assembled from an engineering one reads as a stack
 * trace with manners.
 */
final class KnowledgeUploadRefused extends RuntimeException
{
    public static function notStored(string $path, string $disk): self
    {
        return new self(sprintf(
            'The uploaded document could not be written to [%s] on the [%s] disk, so no knowledge_sources '
            .'row was created for it. That disk is configured \'throw\' => false, so the failure arrived as '
            .'a false return rather than an exception; the store itself is what to look at — a full volume, '
            .'a permission, or an object-store credential.',
            $path,
            $disk,
        ));
    }
}
