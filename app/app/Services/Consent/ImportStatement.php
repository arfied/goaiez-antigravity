<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Services\Legal\LegalDocuments;

/**
 * The published wording a tenant attests to when they upload a customer list.
 *
 * ⚠️ **THIS CLASS EXISTS TO REFUSE.** `ImportAttestation` documents its
 * `$statementVersion` as *"the published wording the tenant saw, exactly as
 * published"*, and when that contract was written there was nothing publishing
 * any such wording — no `LegalDocumentType` case, nothing in `app/` that could
 * produce one. So the version was whatever a caller typed, which is an
 * attestation to a string rather than to a document. This resolves it from the
 * one store that versions text immutably, and fails closed when that store is
 * empty.
 *
 * ## What it does not do
 *
 * **It does not supply the words.** Decisions 549 and 475 stand exactly as
 * written: the owner's EBR ruling is his, counsel owns the wording, and `29`
 * §12.2's review gate is a human gate this code cannot verify — what a build can
 * check is that a *recorded* reviewer published a *non-placeholder* version, and
 * that is all this checks. An unpublished statement is not a bug to route
 * around; it is the system correctly declining to put words in a tenant's mouth.
 *
 * **It does not seed a draft.** A seeded placeholder is worse than nothing,
 * because the refusal below would lift the moment somebody published it to clear
 * a red screen, and the first attested import would then cite wording that was
 * never meant to be final. `is_placeholder` is checked for that exact reason.
 *
 * ## Where the guard sits
 *
 * On the **write path**, in `CustomerImports::import()` — never only on the
 * screen. Decision 398's finding is the one to avoid: a check that lives in the
 * controller is a check the next caller bypasses, and row 4's senders reading a
 * list from a queue payload are exactly that caller. `BaaRecords` settled the
 * shape one table over: `refuseUnusableVersion()` is public so a screen can
 * refuse *before* it writes anything, and the service asks again regardless.
 */
final class ImportStatement
{
    public function __construct(private readonly LegalDocuments $documents) {}

    /**
     * The version a tenant should be shown right now, or null when none is
     * usable.
     *
     * ⚠️ **A PUBLISHED PLACEHOLDER COUNTS AS NOTHING, AND DOES NOT FALL BACK TO
     * AN OLDER FINAL VERSION.** Falling back would mean showing a tenant wording
     * that counsel has already superseded, at the moment counsel is mid-revision
     * — the newest published text is the platform's current position whether or
     * not it is finished, so if it is unfinished the honest state is "not ready",
     * not "here is last month's".
     */
    public function current(): ?LegalDocument
    {
        $version = $this->documents->current(LegalDocumentType::ImportAttestation);

        if ($version === null || $version->is_placeholder) {
            return null;
        }

        return $version;
    }

    /**
     * Whether an import may be offered at all.
     *
     * The screen's question. `current()` answers it too, but a caller that only
     * wants the yes/no reads better for it and cannot accidentally render a
     * placeholder it forgot to guard.
     */
    public function isAvailable(): bool
    {
        return $this->current() !== null;
    }

    /**
     * The version a tenant must be shown, or refuse.
     *
     * @throws ImportStatementUnavailable
     */
    public function requireCurrent(): LegalDocument
    {
        $version = $this->current();

        if ($version === null) {
            throw new ImportStatementUnavailable(
                'No Customer List Attestation has been published, so no list can be imported '
                .'yet. The wording is counsel\'s to write and an admin publishes it at '
                .'/admin/legal/import-attestation — a final version with a recorded reviewer, '
                .'not a working draft.',
            );
        }

        return $version;
    }

    /**
     * Refuse a version that a tenant cannot meaningfully have attested to.
     *
     * ⚠️ PUBLIC SO A CALLER CAN REFUSE **BEFORE** IT WRITES, which is
     * `BaaRecords::refuseUnusableVersion()`'s own reason, and a courtesy rather
     * than the boundary — `CustomerImports::import()` asks again regardless.
     *
     * ⚠️ **IT DOES NOT REQUIRE THE NEWEST VERSION**, deliberately. A tenant who
     * opened the import screen a minute before counsel published a revision saw
     * the older words and honestly attested to those; refusing them would be a
     * failure at a moment nothing is wrong, and the record names exactly which
     * version they saw, which is the whole point of storing a version rather
     * than a boolean. What it refuses is a version that never existed, one still
     * in draft, one marked as unfinished text, and one belonging to a different
     * document altogether.
     *
     * @throws ImportStatementUnavailable
     */
    public function refuseUnusableVersion(string $version): void
    {
        $document = $this->documents->version(LegalDocumentType::ImportAttestation, $version);

        if ($document === null) {
            throw new ImportStatementUnavailable(
                "No version {$version} of the Customer List Attestation exists. An attestation "
                .'names the exact published wording the tenant was shown, so it cannot name a '
                .'version that was never published.',
            );
        }

        if (! $document->isPublished()) {
            throw new ImportStatementUnavailable(
                "Version {$version} of the Customer List Attestation is still a draft. A draft "
                .'can still change, so nothing attested against it would stay true — publish it '
                .'first, which also requires a named reviewer.',
            );
        }

        if ($document->is_placeholder) {
            throw new ImportStatementUnavailable(
                "Version {$version} of the Customer List Attestation is marked as a working "
                .'draft, not final text. Decision 549 rests the whole reactivation position on '
                .'what tenants affirmed, so record a reviewer and publish a final version before '
                .'anyone attests to it.',
            );
        }
    }
}
