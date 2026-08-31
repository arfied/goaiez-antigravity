<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LegalDocumentType;
use RuntimeException;

/**
 * THE LEGAL DRAFT MANIFEST — doc `38` Part 9 / D-157, and `39`'s seed checklist
 * step 1: *"Load Docs 1–13 as versions `0.9`, `is_placeholder=true`, doc_types
 * as marked."*
 *
 * `CredentialManifest` and `DefaultsManifest`'s sibling, and the third artifact
 * of the same shape: one reviewed declaration of what a fresh install starts
 * with, loaded by one idempotent command. What makes this one different is that
 * the seeded value is thousands of words rather than a number, so the bodies sit
 * beside it as files instead of inline.
 *
 * ## Why the bodies are copied out of the drafting pack rather than read from it
 *
 * Reading the drafting document at seed time is the shape that makes drift
 * impossible by construction, and it is refused for one reason: **it would make
 * a documentation directory load-bearing for a production install.** This
 * project has already archived four documents out from under their own citations
 * (decisions 135–138), and `39` is a v0.9 draft whose entire purpose is to be
 * superseded by counsel — so the file an installer would depend on is one of the
 * more movable things in this repository.
 *
 * The drift is closed the way decision 501 closed it for the commercial-model
 * table instead: **`LegalTest` parses each pack's sections and compares them
 * against these files on every run.** A rebuild that was correct once stays
 * correct, and a brittle markdown parse fails the build loudly rather than
 * failing an install quietly. Decision 720.
 *
 * ⚠️ **THE PACK IS NOT ALWAYS `39`, AND {@see self::draftSource()} IS WHERE
 * THAT IS DECLARED.** R53's L-9 arrived in a later drop than `39`'s set, so
 * each document names the document its words were written in rather than the
 * whole set sharing one. Decision 5165.
 *
 * ## What this manifest is not
 *
 * ⚠️ **It is not the source of truth for a published document.** The moment
 * counsel publishes, `legal_documents` holds the text and this file is
 * historical — `SeedLegalDrafts` skips any document that has a version, so
 * editing a body here after publication changes nothing anywhere. That is
 * deliberate: a seed that could overwrite a reviewed document would be a way to
 * change the terms every tenant is bound by with a file edit and no reviewer.
 */
final class LegalDraftManifest
{
    /**
     * The version every draft is seeded at.
     *
     * ⚠️ ONE CONSTANT RATHER THAN A VALUE PER DOCUMENT, because `39` ships all
     * of them at `0.9` and nothing here has any business inventing a different
     * number for one of them. A revision is counsel's act and happens through
     * the admin screen, which mints the version the reviewer asks for — this
     * constant moves only if a whole new pack arrives.
     */
    public const string VERSION = '0.9';

    /**
     * `39`'s legal pack — the drafting document most of these came from.
     *
     * Repository-relative, because the only reader is a lint that resolves it
     * against `base_path()`. Nothing in `app/` opens it at runtime; see the
     * docblock above for why that is deliberate.
     */
    public const string PACK_39 = 'docs/39-LEGAL-PACK-DRAFTS.md';

    /**
     * R53's draft set — the owner's own L-1…L-10, delivered 2026-08-18.
     *
     * ⚠️ **THERE ARE TWO DRAFTING DOCUMENTS NOW, AND PRETENDING OTHERWISE WAS
     * THE ALTERNATIVE.** The cheap move was to paste R53's L-9 into `39` as a
     * fourteenth section so the existing lint kept working unchanged. That
     * makes a **second copy of counsel-bound text** whose only job is to satisfy
     * a test, in a repository whose recurring-failure list opens with drift
     * between two copies of one string. Declaring the source per document costs
     * one `match` and keeps every draft pinned to the document it was actually
     * written in. Decision 5165.
     */
    public const string PACK_R53 = 'docs/incoming-2026-08-18/pack/09-code-patch/source/R53-LEGAL-DRAFT-SET-T279.md';

    /**
     * Where one document's draft body lives.
     *
     * `database/seeders/` rather than `resources/`: these are seed data for a
     * fresh install and nothing renders them as an asset. The command that reads
     * them is the only reader.
     */
    public static function path(LegalDocumentType $type): string
    {
        return database_path("seeders/legal/{$type->value}.md");
    }

    /**
     * One document's draft body.
     *
     * ⚠️ THROWS RATHER THAN SEEDING AN EMPTY DOCUMENT. A missing file is a
     * fourteenth enum case whose draft nobody wrote, and the quiet failure — an
     * empty `terms` row that looks seeded on the admin index — is worse than a
     * refused install, because the state it produces is indistinguishable from a
     * document counsel simply has not filled in yet.
     *
     * @throws RuntimeException when the declared draft file is missing or empty
     */
    public static function body(LegalDocumentType $type): string
    {
        $path = self::path($type);

        $body = is_file($path) ? trim((string) file_get_contents($path)) : '';

        if ($body === '') {
            throw new RuntimeException(
                "No draft body for {$type->title()}. Expected {$path}, rebuilt from the "
                .'drafting pack draftSource() names for it — see LegalDraftManifest and '
                .'decision 720.'
            );
        }

        return $body;
    }

    /**
     * Every document this application seeds, in `39`'s own order.
     *
     * The enum is the list, so a further case cannot be added without either
     * a draft file or a failing build. That is the same closure `isPublic()`'s
     * `match` has and it is here for the same reason.
     *
     * ⚠️ THE ENUM IS NO LONGER THE WHOLE LIST, AND THE ONE EXCLUSION IS NOT AN
     * OVERSIGHT. `ImportAttestation` is the statement a tenant makes *to us*
     * rather than a document we publish to them, and `LegalDocumentType` says
     * on that case that **nothing seeds it, and that is the point**: the wording
     * is counsel's, `ImportStatement` fails closed until a non-placeholder
     * version is published, and the import screen says so on its face rather
     * than quoting words nobody approved. Seeding a draft here would invent
     * those words — decision 502's rejected move, on something far more
     * expensive than a price, and it would do it while looking like a fix for a
     * red build.
     *
     * ⚠️ THE PREDICATE LIVES ON THE ENUM, NOT HERE, AND NOT BY PREFERENCE.
     * `the attested wording is resolved in exactly one place` forbids any file
     * under `app/` but `ImportStatement` from naming that case, and it refused
     * this method when the `match` was written inline. Widening that allowlist
     * would have been a chokepoint weakened to make a seeder compile — decision
     * 624's shape, reasonable on its own diff and invisible as a security
     * change. `hasSeededDraft()` answers it from inside the enum instead, which
     * is where `isPublic()`'s identical `match` already lives.
     *
     * @return list<LegalDocumentType>
     */
    public static function documents(): array
    {
        return array_values(array_filter(
            LegalDocumentType::cases(),
            static fn (LegalDocumentType $type): bool => $type->hasSeededDraft(),
        ));
    }

    /**
     * Which drafting document one body was rebuilt from, and the heading inside
     * it that holds the text.
     *
     * ⚠️ **THE HEADING IS DECLARED RATHER THAN DERIVED FROM THE SLUG, BECAUSE
     * THE TWO PACKS INDEX THEIR SECTIONS DIFFERENTLY.** `39` heads each section
     * with the `doc_type` itself — *"(doc_type: `terms`)"* — so the slug is the
     * key there. R53 heads its sections `L-1` … `L-10` and never writes a
     * `doc_type` at all, so a slug lookup against it would find nothing and the
     * lint would compare an empty section against a real body. That failure is
     * the one worth avoiding: it reads as a drift finding when it is a parse
     * bug.
     *
     * ⚠️ **THE `default` ARM THROWS RATHER THAN FALLING BACK TO `39`, AND THE
     * UNSEEDED CASE IS REFUSED BEFORE THE `match` IS REACHED AT ALL.** A silent
     * fallback would give a new document `39` as its source and the lint would
     * then report drift — a parse miss dressed up as a text mismatch, which is
     * the most misleading failure this file could produce.
     *
     * ⚠️ **AND THE GUARD IS `hasSeededDraft()` RATHER THAN AN ARM NAMING THE
     * IMPORT ATTESTATION**, deliberately: `ConsentTest`'s *"the attested wording
     * is resolved in exactly one place"* forbids any file under `app/` but
     * `ImportStatement` from writing `LegalDocumentType::ImportAttestation`, and
     * it reads code with the comments stripped, so an arm would redden it. That
     * lint is a chokepoint on a compliance record; widening its allowlist to let
     * a manifest compile is decision 624's shape. The predicate on the enum
     * answers the same question without naming the case — the identical dodge
     * `documents()` already makes two methods above.
     *
     * @return array{document: string, heading: string}
     *
     * @throws RuntimeException when the document is not seeded, or is seeded and
     *                          nobody has declared where its words came from
     */
    public static function draftSource(LegalDocumentType $type): array
    {
        if (! $type->hasSeededDraft()) {
            throw new RuntimeException(
                "{$type->title()} is deliberately not seeded, so it has no drafting document. "
                .'See LegalDocumentType::hasSeededDraft() for whose words they are.'
            );
        }

        return match ($type) {
            LegalDocumentType::Terms,
            LegalDocumentType::SmsTerms,
            LegalDocumentType::Privacy,
            LegalDocumentType::AcceptableUse,
            LegalDocumentType::Dpa,
            LegalDocumentType::Baa,
            LegalDocumentType::Sla,
            LegalDocumentType::Cookies,
            LegalDocumentType::BillingRefunds,
            LegalDocumentType::ReferralAffiliate,
            LegalDocumentType::Agency,
            LegalDocumentType::Dmca,
            LegalDocumentType::Esign => [
                'document' => self::PACK_39,
                'heading' => $type->value,
            ],

            LegalDocumentType::InternalRefunds => [
                'document' => self::PACK_R53,
                'heading' => 'L-9',
            ],

            LegalDocumentType::ImportAttestation => throw new RuntimeException(
                "{$type->value} is seeded but no drafting document is declared for it. "
                .'Add an arm to LegalDraftManifest::draftSource() naming the pack its words '
                .'were written in, or the lint that pins every draft to its source cannot run.'
            ),
        };
    }

    /**
     * Every seeded document that was drafted in one particular document.
     *
     * The lint's subject, grouped so each pack is parsed once and each body is
     * compared against the pack it belongs to.
     *
     * @return list<LegalDocumentType>
     */
    public static function documentsDraftedIn(string $document): array
    {
        return array_values(array_filter(
            self::documents(),
            static fn (LegalDocumentType $type): bool => self::draftSource($type)['document'] === $document,
        ));
    }
}
