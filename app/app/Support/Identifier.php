<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OutreachChannel;
use App\Services\Consent\IdentifierHashEpochs;
use Illuminate\Support\Str;

/**
 * One normal form per identifier, applied at every boundary that stores or
 * compares one.
 *
 * ⚠️ THIS IS A PREREQUISITE FOR `opt_outs`, NOT A TIDINESS PASS. That table
 * stores a **hash** of the identifier rather than the identifier, which is what
 * stops a platform-wide opt-out list from being a marketing list. A hash only
 * answers exact matches — so if a carrier STOP writes `+15551234567` and a
 * feedback form later checks `(555) 123-4567`, the two hashes differ, the
 * opt-out does not apply, and we text somebody who said STOP. **The silent
 * failure is the entire exposure the table exists to remove.**
 *
 * Decision 293 put normalisation "at the inbound boundary, applied once to both
 * sides", and 332 shipped the email half while leaving the phone half open for
 * want of a country context. This closes it, with the country stated rather
 * than guessed — see `PHONE_REGION`.
 *
 * IDEMPOTENT BY CONSTRUCTION. `normalise(normalise($x))` must equal
 * `normalise($x)` for every input, because these values are compared across
 * time: one written at a STOP in March and one computed at a send in June. A
 * non-idempotent normaliser produces a boundary that works until somebody
 * re-saves a row.
 */
final class Identifier
{
    /**
     * ⚠️ US, AND STATED RATHER THAN INFERRED.
     *
     * A ten-digit number with no country code is ambiguous on its face — 332
     * refused to guess and was right to. What makes a default defensible here is
     * that it is not a guess about the *number*, it is a fact about the
     * *product*: 10DLC brand registration, toll-free verification and TCPA are
     * all US regimes, `24` is written entirely against US law, and there is no
     * non-US tenant nor any way to onboard one.
     *
     * ⚠️ **The first non-US tenant invalidates this and must change it**, and
     * the change is not this constant — it is deriving the region from the
     * location, at which point every hash written before that day was computed
     * under a different assumption. Recorded so that day is a decision rather
     * than a discovery.
     *
     * ✅ **AND SINCE 8080 IT IS ALSO DETECTED RATHER THAN ONLY RECORDED.**
     * `IdentifierHashEpochs::PHONE_CANARY` is a **national-form** number, so it
     * takes the branch this constant governs: change the region and the canary
     * stops normalising, the epoch fingerprint changes, and the send gate
     * refuses instead of silently comparing hashes computed under two
     * assumptions. ⚠️ It is the same detector as the key rotation's and it does
     * not make the change safe either — it makes it loud.
     */
    private const string PHONE_REGION = 'US';

    private const int NANP_NATIONAL_DIGITS = 10;

    private const string NANP_COUNTRY_CODE = '1';

    /**
     * The normal form for a channel, or null when the input cannot be trusted.
     *
     * ⚠️ NULL MEANS "DO NOT SEND", AND CALLERS MUST TREAT IT THAT WAY. Returning
     * the raw input on failure would be the dangerous choice: it would hash to
     * something no opt-out could ever match, so an unparseable number would be
     * permanently un-suppressible while looking perfectly sendable. Failing
     * closed costs a message to a badly formatted number; failing open costs a
     * message to somebody who opted out.
     */
    public static function normalise(?string $value, OutreachChannel $channel): ?string
    {
        // Asks the channel rather than enumerating cases here — see
        // OutreachChannel::usesPhoneIdentifier() for why that is not merely
        // tidier.
        return $channel->usesPhoneIdentifier()
            ? self::phone($value)
            : self::email($value);
    }

    /**
     * A bare `local@domain`, lower-cased and trimmed — or null.
     *
     * The local part of an email address is technically case-sensitive per RFC
     * 5321, and in practice no mail provider treats it that way. 332 already
     * made this call on the submission path; this is the same rule in the place
     * every caller can reach.
     *
     * ⛔ **`str_contains($value, '@')` WAS THE WHOLE TEST UNTIL 2026-08-16 AND
     * IT ACCEPTED EVERY WRAPPED FORM OF AN ADDRESS** (4445). `"Jane Doe"
     * <gone@example.test>`, `<gone@example.test>` and `rfc822;
     * gone@example.test` all contain an `@`, so all three normalised to
     * themselves and hashed to something non-null — which is the one failure
     * this class exists to prevent, arrived at from the other end. Every guard
     * downstream tests the hash for null and none of them can tell a hash of an
     * address from a hash of a *string mentioning* an address, so a suppression
     * written from any of those forms reports success, files a row, and never
     * matches the bare address the send gate hashes. **The address stays fully
     * sendable while every record says it was suppressed.**
     *
     * ⚠️ **THE CALLER THAT MADE IT REACHABLE WAS AWS'S OWN FIELD FORMAT.** SES's
     * `bouncedRecipients[].emailAddress` is *"the value of the `Final-Recipient`
     * field from the DSN"* when a DSN is available (docs.aws.amazon.com,
     * *Amazon SES notification contents for Amazon SNS*, re-read 2026-08-16),
     * and RFC 3464 §2.3.2 defines that field as
     * `address-type ";" generic-address` — so the ordinary, specification-
     * conformant value is `rfc822; user@example.com`. `MailFeedback` now
     * extracts the address before it gets here; this is the second layer,
     * because extraction is a boundary concern and **every other caller of this
     * method has no extractor at all**.
     *
     * ⚠️ **`filter_var()` RATHER THAN A HAND-ROLLED PATTERN**, and not for
     * brevity: the failure mode is a form somebody did not think of, so the
     * check that matters is the one that was not written by the person
     * enumerating forms. It refuses every wrapped shape above, refuses
     * `a@b@c` — which Symfony's `Address` returns **unchanged**, so this is the
     * only layer that ever sees it — and refuses `user@localhost` and a bare
     * `a@b` for want of a dotted domain. All of them fail closed, which for an
     * identifier means "not sendable and not suppressible", never "sendable".
     * ⛔ **AN RFC 822 SOURCE ROUTE WAS NAMED HERE AS A SECOND THING `Address`
     * LETS THROUGH AND IT IS NOT** (4532): `Address::create()` throws
     * `RfcComplianceException` on one, measured rather than read. Both layers
     * are still load-bearing — `a@b@c` is the case that carries it — but the
     * claim came from the same unmeasured reading as 4447's, which is why that
     * lesson is restated here rather than assumed learnt.
     *
     * ⚠️ **IT ALSO REFUSES AN INTERNATIONALISED ADDRESS** (`user@exämple.de`).
     * That is a real narrowing and is accepted on the same grounds
     * `PHONE_REGION` is: there is no non-US tenant and no way to onboard one.
     * The first one makes this a decision rather than a discovery.
     *
     * Idempotent, as the class docblock requires: lower-casing a valid address
     * leaves a valid address, so `email(email($x)) === email($x)`.
     */
    public static function email(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return Str::lower($value);
    }

    /**
     * E.164, or null.
     *
     * Three shapes are accepted and nothing else:
     *
     *   +15551234567   already E.164 — returned as-is
     *   15551234567    NANP with country code, no plus
     *   5551234567     NANP national, PHONE_REGION supplies the country code
     *
     * ⚠️ Everything else is null on purpose, including a plausible-looking
     * 11-digit number that does not start with 1 and any international number
     * typed without its plus. Accepting those would mean inventing a country
     * code, and an invented country code produces a hash that silently matches
     * nothing — the failure mode this whole class exists to prevent, arrived at
     * by being helpful.
     */
    public static function phone(?string $value, string $region = self::PHONE_REGION): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            // Already international. Trusted as given: re-deriving a country
            // code from a number that already carries one is how a correct
            // value becomes a wrong one.
            return '+'.$digits;
        }

        // ⚠️ A PARAMETER RATHER THAN A CONSTANT READ INLINE, and the reason is
        // the change this class already knows is coming. The docblock on
        // PHONE_REGION says the first non-US tenant must derive the region from
        // the location; a constant compared inline makes that a rewrite, and
        // PHPStan correctly called it dead code besides. As a defaulted
        // parameter the future change is a caller passing a region.
        if ($region === 'US') {
            if (strlen($digits) === self::NANP_NATIONAL_DIGITS) {
                return '+'.self::NANP_COUNTRY_CODE.$digits;
            }

            if (strlen($digits) === self::NANP_NATIONAL_DIGITS + 1
                && str_starts_with($digits, self::NANP_COUNTRY_CODE)) {
                return '+'.$digits;
            }
        }

        return null;
    }

    /**
     * The keyed hash stored in `opt_outs`.
     *
     * KEYED WITH THE APPLICATION KEY, the same construction as `HashedIp` and
     * for the same reason: a bare SHA-256 of a phone number is reversible by
     * anybody willing to hash the ten-digit space, which is minutes of compute.
     * A keyed hash is only reversible by somebody who already has the key, and
     * somebody with the key has the database.
     *
     * ## ⛔ AND THE KEY IS `APP_KEY`, WHICH MEANS ROTATING IT DISARMS EVERY
     * ## SUPPRESSION REGISTER ON THE PLATFORM AT ONCE
     *
     * This is the single most consequential fact about this method and nothing
     * in the tree said so until 2026-08-22 (8080). A stored digest and a fresh
     * one are only comparable while the key is the same, so after a rotation:
     * `opt_outs` refuses nobody — including every carrier STOP, which has **no
     * clear copy anywhere in this schema**; `compliance_suppressions` refuses
     * nobody while `SuppressionRegistry::missingForMarketing()` goes on counting
     * the rows and reporting the registers loaded; and no `suppression_lifts`
     * row can be paired with the refusal it clears.
     *
     * ⛔ **AND IT IS SILENT.** A post-rotation hash is still 64 hex characters,
     * so every `_value_hash_is_sha256` CHECK is satisfied.
     * `ConsentService::decide()` asks suppression *before* consent, so a STOP
     * that stops matching falls through to a consent record that exists —
     * because that is why the person was texted in the first place — and grants.
     * ⚠️ **`config/app.php`'s `previous_keys` CANNOT COVER IT AND IS WHAT MAKES
     * THE ROTATION LOOK SUCCESSFUL**: it is decryption-only, `hash_hmac()` reads
     * `config('app.key')` directly, and every `Crypt` column, OAuth token and
     * unsubscribe link goes on working. **Every `Crypt` path screams; every
     * `hash_hmac` path is silent.**
     *
     * ✅ **WHAT NOW STOPS IT BEING SILENT** is {@see IdentifierHashEpochs},
     * which records the fingerprint of this function whenever a durable hash is
     * written and refuses `SendRefusalReason::SuppressionUnreadable` when a live
     * epoch names a key this install no longer has. ⚠️ **It does not make a
     * rotation safe** — the stored digests are still unmatchable and the only
     * repair is the previous key — it makes it impossible to have quietly.
     *
     * ⚠️ **THE SAME HAZARD REACHES `HashedIp::hash()`, `ProofHash::hash()`,
     * `TrialEligibility::identityHash()` AND `ExportBuilder`'s download token**,
     * which are this construction with a different input and are **not** covered
     * by that epoch. 3236 scoped the blast radius to `trial_claims` alone;
     * `Architecture/ConsentTest`'s `identifierHashCallSiteCensus()` is the census
     * of this function's own callers and 8091 names the rest.
     *
     * ⚠️ Normalises first, always. A hash of an un-normalised value is the bug
     * this class exists to prevent, and taking the normal form as a parameter
     * would let a caller skip it.
     */
    public static function hash(?string $value, OutreachChannel $channel): ?string
    {
        $normalised = self::normalise($value, $channel);

        if ($normalised === null) {
            return null;
        }

        return hash_hmac('sha256', $normalised, (string) config('app.key'));
    }
}
