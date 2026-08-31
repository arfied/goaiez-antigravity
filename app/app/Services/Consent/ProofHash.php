<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ProofHashDomain;
use App\Support\HashedIp;
use InvalidArgumentException;

/**
 * The `ip_hash` a proof blob is allowed to keep — scoped to the record it
 * belongs to, so it cannot be joined against anybody else's copy.
 *
 * ## What this is for
 *
 * {@see HashedIp::hash()} is `hash_hmac('sha256', $ip, config('app.key'))` with
 * **no domain separation and no per-table salt**, so every table storing it
 * stores the *same* value for the same address. 7888 is the ruling that noticed
 * what that means: `magic_link_tokens.requested_ip_hash` was joinable against
 * `public_audits.ip_hash` for ever, because one side expired and the other did
 * not. Five consent and contract records store the same value the same way, in
 * a `jsonb` key rather than a column, and **the side that carries a name is the
 * expensive one** — a `consent_records` row names a customer and a
 * `terms_acceptances` row names a business, so a join from either to a visitor
 * table turns anonymous traffic into a named person.
 *
 * ## Why a hash of a hash, and why that is not sloppy
 *
 * `TrialEligibility::identityHash()` already does this and says why: *"the
 * signup-origin input is already a keyed hash when it arrives, so this is a hash
 * of a hash. That is deliberate rather than sloppy."* It is also what makes the
 * construction reachable at all here — **the raw address is gone by the time any
 * of this runs**, correctly and permanently, so the only input available is the
 * hash somebody already took.
 *
 * ## What survives and what does not
 *
 * ✅ **Within one table nothing changes.** `scope()` is a pure function of the
 * incoming hash, so two rows of the same table recording the same address still
 * compare equal — which is the whole evidential content of the field: *these
 * four hundred consents came from one place*, or *these two did not*. Nothing in
 * `app/` queries `proof->>'ip_hash'` at all, so no read is broken; the reader is
 * a person answering a carrier or a subpoena, and the comparison they make is
 * within a table.
 *
 * ⛔ **Across tables the comparison is gone, and that is the deliberate act.**
 *
 * ⚠️ **And across the change itself.** A row written before this shipped holds
 * the unscoped value, so it does not compare equal to one written after, for the
 * same address. **That is why {@see self::DOMAIN_KEY} is stamped**: a reader can
 * tell a scoped hash from an unscoped one instead of reading a false *different
 * network*. ⛔ **No back-fill is attempted and that is a ruling rather than an
 * omission** — see the decisions for wave 14 lane B. These rows are append-only
 * by construction (295, and `TermsAcceptance`/`ConsentRecord` throw on
 * `updating`), copies have already left the building through `ExportBuilder` and
 * `DataRequests`, and rewriting evidence to improve it is the act 295 exists to
 * prevent.
 *
 * ## Not a validator
 *
 * ⚠️ **The raw-IP rule is `29` §2 rule 21 and lives in `ConsentProof`, once.**
 * 2933's whole argument is that a second implementation of an absolute rule
 * diverges from the first, and it already had: `ImportAttestation` checked two
 * key names where `ConsentProof` walks every string at every depth. Nothing here
 * re-checks. A raw address reaching `scope()` would be hashed rather than
 * stored — a consequence of the construction, never a guarantee, and never a
 * reason to skip the guard.
 */
final class ProofHash
{
    /**
     * The key that records which construction a stored `ip_hash` was built with.
     *
     * ⚠️ **NOT `ip_hash_domain`, AND THE REASON IS A GUARD RATHER THAN TASTE.**
     * `ConsentProof::rejectRawIp()` refuses any key matching `/(^|_)ips?(_|$)/i`
     * that is not `ip_hash` exactly, so `ip_hash_domain` and
     * `ip_hash_construction` both throw on the way into the very blobs this
     * stamps.
     */
    public const string DOMAIN_KEY = 'hash_domain';

    /**
     * The proof blob as it is allowed to be stored.
     *
     * ⚠️ **A BLOB WITH NO `ip_hash` KEY COMES BACK UNTOUCHED AND UNSTAMPED.**
     * `CaptureSurface`'s attestation surfaces — Import, Pos, Call, Manual — are
     * a tenant asserting something that happened elsewhere, so `ConsentProof`
     * does not require the three self-rendered keys of them and a proof with no
     * hash in it is ordinary. Stamping a scope onto a blob with nothing scoped
     * would name a construction that was never applied.
     *
     * ⚠️ **A NULL `ip_hash` IS STAMPED**, because the key being present is the
     * caller saying *"there should be a hash here"* and `HashedIp::of()`
     * answering null means *"we could not tell"* — the row was still written
     * under this construction, and a reader comparing it with a pre-change row
     * needs to know that.
     *
     * @param  array<string, mixed>  $proof
     * @return array<string, mixed>
     */
    public static function scope(ProofHashDomain $domain, array $proof): array
    {
        if (! array_key_exists('ip_hash', $proof)) {
            return $proof;
        }

        // ⛔ RUNNING TWICE WOULD BE SILENT AND WRONG. A second pass hashes the
        // scoped value again, so the row stops comparing equal to every other
        // row of its own table — the one property this is built to preserve —
        // and nothing about the stored blob would look different afterwards. It
        // is also how a caller would smuggle in a scope for a hash it did not
        // compute.
        if (array_key_exists(self::DOMAIN_KEY, $proof)) {
            throw new InvalidArgumentException(
                'This proof already carries '.self::DOMAIN_KEY.', so either it has been scoped '
                .'once already or a caller supplied the key. Scoping runs exactly once, at the '
                .'write, and the domain is the table\'s to state rather than the caller\'s.',
            );
        }

        $hash = $proof['ip_hash'];

        return [
            ...$proof,
            'ip_hash' => is_string($hash) && $hash !== '' ? self::hash($domain, $hash) : $hash,
            self::DOMAIN_KEY => $domain->value,
        ];
    }

    /**
     * One already-hashed address, scoped to one record type.
     *
     * `TrialEligibility::identityHash()`'s construction, `$domain|$value`, and
     * keyed for its reason: an unkeyed hash of an IPv4 is the address with an
     * extra step, because a laptop enumerates 2^32 in minutes. The separator is
     * a character no hex digest contains, so no two domains can collide by
     * concatenation.
     *
     * ⛔ **AND THE KEY IS `APP_KEY`, SO ROTATING IT MAKES EVERY STORED PROOF
     * HASH INCOMPARABLE WITH EVERY LATER ONE** — 3236's property, inherited.
     * `HashedIp` already had it, so rotation was always this destructive here;
     * what changes is nothing. No test can catch it, because the suite hashes
     * with the same key it compares against.
     */
    public static function hash(ProofHashDomain $domain, string $hashedIp): string
    {
        return hash_hmac('sha256', $domain->value.'|'.$hashedIp, (string) config('app.key'));
    }
}
