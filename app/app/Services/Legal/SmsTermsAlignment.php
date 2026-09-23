<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Services\Config\DefaultsRegistry;
use App\Services\Feedback\ConsentDisclosure;

/**
 * The binding between the opt-in notice this application shows and the SMS &
 * Communications Terms it publishes.
 *
 * ## The gap this closes
 *
 * There were two version spaces and no edge between them. The live opt-in
 * string is versioned by `ConsentDisclosure::SMS_VERSION`, lives in
 * `lang/en/feedback.php`, and is guarded by an architecture lint that stops it
 * being copied anywhere else. The published SMS Terms are versioned by
 * `legal_documents.version`, frozen by this schema's first trigger, and
 * published by a recorded reviewer. Nothing connected the two, so counsel could
 * publish terms describing one set of consent elements while the capture
 * surface went on shipping another sentence, and no test reddened.
 *
 * `database/seeders/legal/sms-terms.md` carries the instruction twice — §2's
 * *"align this text with the exact checkout/opt-in language shipped in the CMP
 * and opt-in pages"* and §10's *"the two must match verbatim"* — as a note in a
 * markdown file, which is to say as nothing at all. This class is what turns
 * the second of those into a refusal.
 *
 * ## Why it is not on the render path
 *
 * ⛔ **The public feedback page must never depend on a published document.**
 * Resolving the disclosure version out of `legal_documents` the way
 * `ImportStatement` resolves the attestation is the stronger shape for a write
 * that nobody is waiting on, and it is the wrong shape here: `GET /f/{slug}` is
 * public and unauthenticated, no document is published on a fresh install, and
 * a fail-closed resolver there would stop every tenant collecting reviews until
 * an admin published — a worse failure than the drift, and the same trap
 * `ConsentDisclosure::html()` already refused when it stopped throwing on a
 * missing anchor phrase. So the check lives where a person is already acting,
 * and a page that renders is never held hostage to a row.
 *
 * ## Where it does sit
 *
 * At **publish** — `LegalDocuments::publish()`, the service rather than the
 * screen, because decision 398's finding is that a guard in the controller is a
 * guard the next caller skips. `refuseUnquotedNotice()` is public so the admin
 * screen can refuse before it writes anything, exactly as
 * `BaaRecords::refuseUnusableVersion()` and `ImportStatement` are, and the
 * service asks again regardless.
 *
 * And at **read**, through `LegalDocumentState::servesDriftedSmsTerms()`, which
 * is not redundant with the publish guard: a version that quoted the notice
 * correctly on the day it was published goes stale the moment somebody edits
 * `lang/en/feedback.php` and bumps `SMS_VERSION`. Published text is frozen, so
 * nothing can repair that row — what the launch gate can do is refuse to call
 * the document set finished until a new version is published.
 *
 * ## Verbatim, not "the same elements"
 *
 * The external review's finding was that the live consent language must match
 * the terms **verbatim**, and an element-by-element check cannot deliver that:
 * two texts can each mention automation, rates, frequency, purchase and
 * STOP/HELP and still describe different programmes. So this asks the one
 * question that has an unambiguous answer — is the paragraph we actually show
 * present, word for word, inside the document we actually publish. The element
 * check exists too, as a build-time lint over the drafts in
 * `tests/Feature/Architecture/ConsentTest.php`; it is the weaker check in the
 * place the stronger one cannot reach, because the draft bodies are counsel's
 * words and quoting the notice into them would be a builder writing legal text.
 *
 * ⚠️ **NOTHING HERE SPELLS THE DISCLOSURE OUT, INCLUDING IN A COMMENT.** The
 * lint *"the consent disclosure is never written out anywhere but its own
 * source"* reads whole file contents under `app/`, comments included, and it is
 * right to: a paraphrase in a docblock is the copy that a later reader edits
 * instead of the original. Every needle below is built at runtime from
 * `ConsentDisclosure`, so this file has nothing to drift.
 */
final class SmsTermsAlignment
{
    /**
     * What stands where a business's own name goes.
     *
     * The notice names the business a consumer is giving their number to; a
     * standing legal document is not about one business, so the quotation
     * inside it carries a token instead. This value is only ever used to *split*
     * the notice into the parts that must appear verbatim — a document may use
     * `{Business Name}`, `[Business]` or the tenant's actual name and still
     * match, because what is checked is the wording either side of it.
     */
    public const string BUSINESS_TOKEN = '{Business Name}';

    /**
     * How much text may stand where the business name goes.
     *
     * Long enough for a legal name with a suffix, short enough that a clause
     * cannot be spliced into the middle of the notice and still pass.
     */
    public const int MAX_NAME_LENGTH = 64;

    public static function maxNameLength(): int
    {
        return app(DefaultsRegistry::class)->int('legal.sms_terms.max_name_length');
    }

    /**
     * The paragraph a published SMS Terms must contain, ready to paste.
     *
     * Built from `ConsentDisclosure`, never retyped, for the reason
     * `SmsOptInController` gives about the carrier-facing page: a hand-copied
     * paragraph drifts silently, and this one exists precisely to prove that
     * two texts have not.
     */
    public static function notice(): string
    {
        return ConsentDisclosure::smsText(self::BUSINESS_TOKEN);
    }

    /**
     * Whether a document body quotes the live opt-in notice.
     *
     * ⚠️ WHITESPACE IS NORMALISED AND NOTHING ELSE IS. A markdown document
     * wraps its lines wherever the editor did, so a line break inside the quoted
     * paragraph is a formatting difference and not a change to the words. Added
     * emphasis is a change to the words: a legal quotation that bolds a clause
     * is no longer verbatim, and the refusal below shows exactly what to paste,
     * so the failure is legible rather than mysterious.
     */
    public static function quotesNotice(string $body): bool
    {
        return preg_match(self::pattern(), self::normalise($body)) === 1;
    }

    /**
     * Refuse a body that does not quote the live opt-in notice.
     *
     * ⚠️ PUBLIC SO A SCREEN CAN REFUSE BEFORE IT WRITES, and a courtesy rather
     * than the boundary — `LegalDocuments::publish()` asks again regardless.
     *
     * @throws SmsTermsMisaligned
     */
    public static function refuseUnquotedNotice(string $version, string $body): void
    {
        if (self::quotesNotice($body)) {
            return;
        }

        throw new SmsTermsMisaligned(
            "Version {$version} of the SMS & Communications Terms does not quote the opt-in "
            .'notice this application shows, so the two would describe different programmes. '
            .'A 10DLC campaign registration files this document and the opt-in page as two '
            .'public URLs and a carrier reviewer reads them side by side. Paste this '
            .'paragraph into the document, unaltered, and publish again — it is disclosure '
            .'version '.ConsentDisclosure::SMS_VERSION.', and every consent record stored '
            ."against that version points at these exact words:\n\n".self::notice(),
        );
    }

    /**
     * The notice as a pattern: every segment verbatim, in order, with room for
     * a name between them.
     *
     * `explode()` over *every* occurrence rather than a two-part split, so a
     * future wording that names the business twice — or not at all — still
     * produces a pattern that means what this method says it means, instead of
     * quietly demanding the literal token in counsel's document.
     */
    private static function pattern(): string
    {
        $segments = array_map(
            static fn (string $segment): string => preg_quote(trim($segment), '/'),
            explode(self::BUSINESS_TOKEN, self::normalise(self::notice())),
        );

        return '/'.implode('.{1,'.self::maxNameLength().'}', $segments).'/u';
    }

    /**
     * Every run of whitespace becomes one space.
     */
    private static function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
