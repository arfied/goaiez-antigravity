<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The legal documents this platform publishes.
 *
 * THE SLUGS ARE THE DRAFTING DOCUMENT'S, NOT OURS. Every case value is the
 * `doc_type` written beside that document in the pack it was drafted in, so the
 * drafts seed into this table without a translation step and a reviewer reading
 * either file finds the same word. Changing one of these values orphans whatever
 * is already published under it. ⚠️ **The pack is not always `39`** — see
 * `LegalDraftManifest::draftSource()`, which names the drafting document per
 * case, because R53's L-9 arrived in a later drop than `39`'s set.
 *
 * A CLOSED SET RATHER THAN A FREE STRING, for the same reason
 * `LegalDocumentController` used an allowlist before this existed: a route
 * parameter that reaches storage or a view name is a surface, and the set of
 * documents that exist is small and known. Adding one is a case here plus a
 * draft — never a row somebody types into admin.
 *
 * ⚠️ TWO CASES ARE IN THIS ENUM AND ARE NOT PUBLISHED LIKE THE REST — `Baa` and
 * `InternalRefunds`. Both are stored and versioned here, counsel reviews and
 * publishes a version exactly as with the others, and `isPublic()` keeps both
 * off `/legal/{doc}`. Storing them elsewhere would mean two version histories
 * and two review workflows for the documents where an unreviewed version is
 * most expensive. ⚠️ **The two are withheld for unrelated reasons and neither
 * implies the other** — the arms of `isPublic()` say which.
 */
enum LegalDocumentType: string
{
    case Terms = 'terms';
    case SmsTerms = 'sms-terms';
    case Privacy = 'privacy';
    case AcceptableUse = 'acceptable-use';
    case Dpa = 'dpa';
    case Baa = 'baa';
    case Sla = 'sla';
    case Cookies = 'cookies';
    case BillingRefunds = 'billing-refunds';
    case ReferralAffiliate = 'referral-affiliate';
    case Agency = 'agency';
    case Dmca = 'dmca';
    case Esign = 'esign';

    /**
     * ⚠️ **THE ONE DOCUMENT WRITTEN FOR STAFF RATHER THAN FOR A READER OUTSIDE
     * THIS COMPANY.** R53's L-9 — the internal refund and goodwill position —
     * and its own source says *"never marketed; never printed on customer
     * surfaces (standing law)."*
     *
     * It is a `legal_documents` row rather than a note in a runbook for the
     * reason the `Baa` case gives: this table is the one store that versions a
     * text immutably, records who reviewed it and who published it, and hands
     * an admin a drafting screen for free. A staff policy about when money is
     * returned is exactly the kind of text somebody later needs to prove the
     * wording of on a given date.
     *
     * ⛔ **`isPublic()` IS WHAT KEEPS IT OFF EVERY PUBLIC ROUTE, AND IT IS THE
     * SAME MECHANISM THE BAA ALREADY USES.** CC-4's brief asked for a boolean
     * `internal` column on the table; that column is refused and this case
     * answers instead — a per-row flag would let one version of a document be
     * internal and the next public, and a published row is frozen by this
     * schema's first trigger, so the mistake would be unrepairable. Whether a
     * document may be read by the public is a fact about the document, not
     * about one of its versions. Decision 5166.
     *
     * ⚠️ **PUBLIC INVISIBILITY IS NOT CONFIDENTIALITY.** Everything here is
     * readable by any staff member who can reach `/admin/legal`, and the row is
     * not encrypted. What the exclusion buys is that the policy is never served
     * at `/legal/{doc}` and never quoted on a customer surface — which is the
     * standing law it carries, and nothing more than that.
     */
    case InternalRefunds = 'internal-refunds';

    /**
     * ⚠️ THE ONE CASE THIS PLATFORM DOES NOT WRITE THE WORDS FOR. Every case
     * above is a document we hand *to* somebody — most of them to the public,
     * the BAA to one named covered entity, the internal refund policy to our own
     * staff. This one is the statement a tenant makes *to us* — that an uploaded
     * list is their own existing customers — and decision 549 rests the entire
     * reactivation position on it.
     *
     * ⚠️ **IT USED TO OPEN "THE FOURTEENTH CASE" AND THE COUNT WENT STALE THE
     * MOMENT ONE WAS ADDED** (5180). It also read *"every case above is a
     * document we publish"*, which stopped being true in the same commit — the
     * case declared above this one is deliberately never published to anybody.
     *
     * It is a `LegalDocumentType` rather than a constant, a config key or a
     * registry row for one reason: `customer_imports.statement_version` has to
     * resolve to exact words forever, the same way
     * `consent_records.disclosure_version` does. Anything editable in place
     * silently changes what every past attestation claims a tenant agreed to,
     * which is what this table's publish trigger exists to make impossible.
     *
     * ⚠️ **NOTHING SEEDS IT, AND THAT IS THE POINT.** The wording is counsel's,
     * not ours — decisions 549 and 475 are unchanged by this case existing. What
     * the code can do is refuse: `ImportStatement` fails closed until a
     * non-placeholder version is published here, and the import screen says so
     * on its face rather than quoting words nobody approved. Inventing a default
     * would be decision 502's rejected move on something far more expensive than
     * a price.
     */
    case ImportAttestation = 'import-attestation';

    /**
     * The heading a reader sees.
     *
     * Here rather than in a language file because these are the names of legal
     * instruments — "Data Processing Addendum" is what the document is called,
     * not a label chosen for a screen. Localisation translates the body, and a
     * translated body still carries the instrument's own name.
     */
    public function title(): string
    {
        return match ($this) {
            self::Terms => 'Terms of Service',
            self::SmsTerms => 'SMS & Communications Terms',
            self::Privacy => 'Privacy Policy',
            self::AcceptableUse => 'Acceptable Use Policy',
            self::Dpa => 'Data Processing Addendum',
            self::Baa => 'Business Associate Agreement',
            self::Sla => 'Service Level Agreement',
            self::Cookies => 'Cookie & Tracking Notice',
            self::BillingRefunds => 'Billing & Refund Policy',
            self::ReferralAffiliate => 'Referral & Affiliate Program Terms',
            self::Agency => 'Agency / Reseller Agreement',
            self::Dmca => 'Copyright / DMCA Policy',
            self::Esign => 'Electronic Communications & E-SIGN Consent',
            self::InternalRefunds => 'Internal Refund Policy (staff only)',
            self::ImportAttestation => 'Customer List Attestation',
        };
    }

    /**
     * Whether this document is served at `/legal/{doc}` to anyone who asks.
     *
     * ⚠️ **The BAA is not**, and the distinction is not cosmetic.
     * `/legal/{doc}` is unauthenticated and uncached-by-tenant; a BAA is an
     * agreement between us and one named Covered Entity, executed with a
     * signature record. Publishing the template at a public URL invites it being
     * cited as though it were in force for somebody who never signed it.
     *
     * ⚠️ **The internal refund policy is not either, and for a different
     * reason** — R53's L-9 carries a standing law on its own face: never
     * marketed, never printed on a customer surface. `InternalLegalDocumentTest`'s
     * *"a document this enum withholds is unreachable at every public address"*
     * drives that against a **published, non-placeholder** row, so the refusal is
     * proven by this method rather than by an empty table (398).
     *
     * Written as a match with no default so a further case cannot inherit an
     * answer nobody chose — the same reason `ConsentDisclosure::versionFor()`
     * has none.
     */
    public function isPublic(): bool
    {
        return match ($this) {
            // ⚠️ TWO FALSE ARMS NOW, AND THEY ARE FALSE FOR DIFFERENT REASONS.
            // The BAA is withheld because publishing a template invites it
            // being cited as in force for somebody who never signed it. The
            // internal refund policy is withheld because R53's L-9 carries a
            // standing law on its face — never marketed, never printed on a
            // customer surface. Neither reason implies the other.
            self::Baa,
            self::InternalRefunds => false,
            self::Terms,
            self::SmsTerms,
            self::Privacy,
            self::AcceptableUse,
            self::Dpa,
            self::Sla,
            self::Cookies,
            self::BillingRefunds,
            self::ReferralAffiliate,
            self::Agency,
            self::Dmca,
            self::Esign,
            // ⚠️ PUBLIC, THOUGH IT IS THE ONE DOCUMENT A TENANT AGREES *TO* US.
            // It reads like the BAA's shape and is the opposite: a BAA is an
            // executed agreement with one named Covered Entity, so publishing
            // the template invites it being cited as in force for somebody who
            // never signed. This is a single standing statement every importing
            // tenant is shown, like the SMS terms — and
            // `customer_imports.statement_version` has to resolve to readable
            // words forever, for a reader who may well not have an account here.
            // A challenge to decision 549's EBR basis is answered by showing
            // exactly what tenants were asked to affirm, which is easier to do
            // when the words are at a stable public address.
            self::ImportAttestation => true,
        };
    }

    /**
     * Whether this application authors the draft words, and therefore seeds one.
     *
     * ⚠️ A DIFFERENT AXIS FROM `isPublic()`, AND THE TWO DISAGREE IN BOTH
     * DIRECTIONS. The BAA is not public and is seeded; the import attestation is
     * public and must never be. `isPublic()` asks *who may read it*; this asks
     * *whose words they are*. Using either to answer the other looks correct on
     * twelve of the fourteen cases, which is what makes it worth separating.
     *
     * The attestation is the one statement a tenant makes *to us*, its wording is
     * counsel's, and `ImportStatement` fails closed until a non-placeholder
     * version is published. Seeding a draft would invent those words — decision
     * 502's rejected move on something far more expensive than a price — and it
     * would arrive looking like a fix for a red build, which is how it nearly
     * did when `39`'s seeds met Import v1.
     *
     * A `match` with no default, so a further case forces an answer instead of
     * inheriting one, for the same reason `isPublic()` has none.
     */
    public function hasSeededDraft(): bool
    {
        return match ($this) {
            self::ImportAttestation => false,
            self::Terms,
            self::SmsTerms,
            self::Privacy,
            self::AcceptableUse,
            self::Dpa,
            self::Baa,
            self::Sla,
            self::Cookies,
            self::BillingRefunds,
            self::ReferralAffiliate,
            self::Agency,
            self::Dmca,
            self::Esign,
            // ⚠️ SEEDED FROM A DIFFERENT DRAFTING DOCUMENT FROM THE REST,
            // AND `LegalDraftManifest::draftSource()` IS WHERE THAT IS
            // DECLARED. The words are the owner's own R53 set rather than
            // `39`'s, so the lint that pins every draft to its source pins this
            // one to R53 instead. That is the opposite of the import
            // attestation's problem: the objection there is that nobody has
            // written the words, and here the owner has.
            self::InternalRefunds => true,
        };
    }
}
