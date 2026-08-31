<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Consent\ProofHash;

/**
 * Which record a stored `ip_hash` belongs to — and therefore what it may be
 * compared against.
 *
 * ⛔ **THIS EXISTS BECAUSE 7888's RULING REACHED ONE TABLE OUT OF SIX.** That
 * decision pruned `magic_link_tokens` because `requested_ip_hash` is
 * `HashedIp::hash()` and `public_audits.ip_hash` *"is the same function of the
 * same address"*, so an un-pruned side kept a join alive indefinitely against a
 * table that expires. **The same value, for the same people, is written into
 * five `jsonb` proof blobs that nothing prunes** — and a sweep run as
 * `grep '_hash' database/migrations/` reports clean on every one of them,
 * because the value lives inside a column under a key rather than in a column
 * of its own.
 *
 * ⛔ **AND A PRUNER IS THE WRONG REMEDY HERE, WHICH IS WHY THIS IS AN ENUM AND
 * NOT A RETENTION KEY.** These five rows are consent and contract evidence.
 * 295's argument for `consent_records` — *"a record that can be edited proves
 * nothing whatever it says"* — applies with the same force to deleting one, and
 * `CLAUDE.md` is explicit that a consent record stores *"wording version,
 * timestamp, URL, IP hash and user agent — not a boolean"*. **Deleting the
 * proof of a consent is not the same act as deleting a sign-in token.**
 *
 * ✅ **DOMAIN SEPARATION BREAKS THE JOIN WITHOUT DELETING ANY EVIDENCE**, and
 * this schema already has the worked example: `TrialEligibility::identityHash()`
 * hashes `$kind->value.'|'.$value` *"so that this table's fingerprints cannot be
 * joined against `public_audits.ip_hash`"* — a hash of a hash, deliberately.
 * {@see ProofHash} is that construction, per table.
 *
 * ⚠️ **WHAT IT COSTS, SAID PLAINLY.** Two rows in *different* tables no longer
 * compare equal for the same address, and neither do two rows in the *same*
 * table written either side of the change. The first is the point. The second is
 * a real loss of comparability across that boundary, which is why every scoped
 * blob carries {@see ProofHash::DOMAIN_KEY} — a reader
 * looking at two hashes can tell whether they were built to be comparable, and
 * a row with no such key predates the change.
 *
 * ⚠️ **THE VALUE IS THE TABLE NAME AND A LINT HOLDS IT TO THAT** — the point of
 * separation is that one table's hashes stay comparable with each other and with
 * nothing else, so the domain is a fact about the table rather than about the
 * writer. `tests/Feature/Architecture/ProofHashTest.php` asserts each case names
 * a table that exists, that every model carrying a `proof` cast has a case, and
 * that a named writer references it.
 */
enum ProofHashDomain: string
{
    case ConsentRecords = 'consent_records';

    case TermsAcceptances = 'terms_acceptances';

    case AutoRenewalAcknowledgements = 'auto_renewal_acknowledgements';

    case ReviewPhiConsents = 'review_phi_consents';

    case CustomerImports = 'customer_imports';

    /**
     * The owner channel's own consent evidence (10540) — `terms_acceptances`'
     * shape, written by `App\Services\Consent\OwnerConsentService`.
     */
    case OwnerNotificationConsents = 'owner_notification_consents';
}
