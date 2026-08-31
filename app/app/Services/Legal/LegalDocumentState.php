<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;

/**
 * Where one legal document stands: what is published, what is in draft, and
 * whether the text a reader would be served is still filler.
 *
 * `SendPermit` and `ModerationVerdict`'s shape — a value object returned by the
 * one service that may build it, so a screen renders a decision rather than
 * making one. `LegalDocuments::library()` is its only factory.
 *
 * ⚠️ IT EXISTS BECAUSE `is_placeholder` HAD NO READER. The column has been on
 * the table since decisions 417–420 with `39`'s checklist step 5 written above
 * it — *"wide launch stays locked while any served doc is a placeholder"* — and
 * the only thing that has ever consulted it is `BaaRecords`, for the BAA alone.
 * A flag whose whole purpose is to gate a launch, read by nothing that can see
 * more than one document, is decision 403's dead column with a slower fuse.
 */
final readonly class LegalDocumentState
{
    public function __construct(
        public LegalDocumentType $type,
        public ?LegalDocument $published,
        public ?LegalDocument $draft,
        public int $versions,
    ) {}

    /**
     * Whether a reader asking for this document today would get filler.
     *
     * ⚠️ TWO CAUSES, AND CONFLATING THEM IS THE MISTAKE THIS METHOD PREVENTS.
     * Nothing published means `LegalDocumentController` renders its placeholder
     * notice; a published version still flagged `is_placeholder` means the
     * document is live and the words are `39`'s drafting text. Both serve
     * placeholder text and the second is the more dangerous, because the screen
     * and the URL both look finished.
     */
    public function servesPlaceholder(): bool
    {
        return $this->published === null || $this->published->is_placeholder;
    }

    /**
     * Whether the published SMS terms have fallen behind the opt-in notice.
     *
     * ⚠️ NOT REDUNDANT WITH THE PUBLISH GUARD, AND THIS IS THE HALF THAT GUARD
     * CANNOT REACH. `LegalDocuments::publish()` refuses a version that does not
     * quote the live notice, so a misaligned row cannot be *created* — but a row
     * that quoted it correctly on the day it was published goes stale the moment
     * somebody edits `lang/en/feedback.php` and bumps
     * `ConsentDisclosure::SMS_VERSION`, and published text is frozen, so nothing
     * can repair that row in place. The only honest response is to stop calling
     * the document set finished until a new version is published.
     *
     * ⚠️ AND IT ANSWERS FOR ONE TYPE RATHER THAN ASKING A GENERAL QUESTION. One
     * document is filed with a 10DLC campaign beside the opt-in page and read
     * against it by a carrier reviewer. The Terms of Service and the Privacy
     * Policy are not that document, and a check that swept them in would fail on
     * twelve documents that never quoted the notice and never should.
     */
    public function servesDriftedSmsTerms(): bool
    {
        return $this->type === LegalDocumentType::SmsTerms
            && $this->published !== null
            && ! SmsTermsAlignment::quotesNotice($this->published->body);
    }

    /**
     * Whether this document blocks a public launch.
     *
     * ⚠️ SCOPED TO THE PUBLIC SET, WHICH IS `39`'s WORD *"SERVED"* AND NOT
     * "EVERY DOCUMENT". The BAA is never served at `/legal/{doc}`
     * (`LegalDocumentType::isPublic()`), so a placeholder BAA does not block a
     * launch to ordinary customers — it blocks the first PHI tenant, which
     * `BaaRecords::recordExecution()` already refuses on its own and which the
     * index names separately. Folding the two together would report one number
     * that is wrong for both audiences.
     */
    public function blocksLaunch(): bool
    {
        return $this->type->isPublic()
            && ($this->servesPlaceholder() || $this->servesDriftedSmsTerms());
    }

    /**
     * The state in words, because colour is never the sole indicator (`22`).
     */
    public function label(): string
    {
        if ($this->published === null) {
            return $this->draft === null
                ? 'Not started'
                : "Draft {$this->draft->version}, unpublished";
        }

        $published = "Published {$this->published->version}";

        if ($this->published->is_placeholder) {
            $published .= ' — placeholder text';
        }

        // Named separately from the placeholder note rather than folded into it:
        // a published SMS Terms that has fallen behind the opt-in notice is
        // finished text that has gone out of date, and telling counsel it is
        // "placeholder text" would send them to rewrite a document whose problem
        // is one paragraph and whose cause is a change somewhere else entirely.
        if ($this->servesDriftedSmsTerms()) {
            $published .= ' — behind the live opt-in notice';
        }

        return $this->draft === null
            ? $published
            : "{$published}; draft {$this->draft->version} open";
    }

    /**
     * What has to happen next, phrased as the act rather than the state.
     *
     * Outcome language (`22`): every string names something a person does. A
     * counsel reviewer opening this index should be able to read one column and
     * know whether the document is theirs to touch.
     */
    public function nextStep(): string
    {
        $pending = $this->draft;

        if ($pending === null) {
            return $this->servesPlaceholder() || $this->servesDriftedSmsTerms()
                ? 'Start a version'
                : 'Nothing outstanding';
        }

        if ($pending->reviewed_at === null) {
            return 'Awaiting counsel review';
        }

        return "Reviewed by {$pending->reviewed_by} — ready to publish";
    }
}
