<?php

declare(strict_types=1);

namespace App\Services\Consent;

use InvalidArgumentException;

/**
 * A tenant's statement that an uploaded list is their own existing customers.
 *
 * ⚠️ **READ DECISION 549 BEFORE 551 BEFORE THIS CLASS.** The owner approved
 * reactivation messaging on the basis that the businesses have an existing
 * relationship with these people and indemnify us. The argument recorded against
 * that is in 549 and is not restated here. **This class does not make that
 * argument true; it makes it evidenced** — an EBR claim and a hold-harmless are
 * worth close to nothing without a record of who asserted what, when, and in
 * what words, which is exactly the first thing a challenge asks for.
 *
 * ## Why it is a required parameter rather than a nullable field
 *
 * `CustomerImports::import()` takes one of these by type. **There is no import
 * path that does not begin with an attestation**, so forgetting to record one is
 * a compile-time error rather than a missing line in a service somebody wrote in
 * a hurry. That is decision 285's `SendPermit` pattern applied one table over,
 * and it is the whole reason this is a class instead of four nullable columns.
 *
 * ## Why the validation is strict about padding
 *
 * `ConsentCapture` refuses a padded disclosure version rather than trimming it,
 * because two records of the same wording have to compare equal and silently
 * fixing one hides the caller bug that produced it. The same applies with more
 * force here: an attestation is only useful if you can group every tenant who
 * agreed to a given statement, and `' v1 '` and `'v1'` do not group.
 */
final readonly class ImportAttestation
{
    /**
     * @param  string  $statementVersion  The published wording the tenant saw,
     *                                    exactly as published.
     * @param  string  $attestedBy  The actor — a user identifier or a system
     *                              actor string, matching what every other
     *                              service in this codebase takes.
     * @param  array<string, mixed>  $proof  `ip_hash` and `user_agent`. ⚠️ A raw
     *                                       IP is forbidden by `29` §2; hash it
     *                                       with App\Support\HashedIp first.
     */
    public function __construct(
        public string $statementVersion,
        public string $attestedBy,
        public array $proof,
    ) {
        if (trim($this->statementVersion) === '') {
            throw new InvalidArgumentException(
                'An import attestation needs the statement version the tenant actually saw.',
            );
        }

        if (trim($this->statementVersion) !== $this->statementVersion) {
            throw new InvalidArgumentException(
                'The statement version is padded with whitespace. Pass it exactly as it is '
                .'published, so two attestations of the same wording compare equal.',
            );
        }

        if (trim($this->attestedBy) === '') {
            throw new InvalidArgumentException(
                'An import attestation needs to name who made it. An anonymous attestation is '
                .'not evidence — the one question it exists to answer is who said this.',
            );
        }

        if ($this->proof === []) {
            throw new InvalidArgumentException(
                'An import attestation needs proof. An unproved attestation looks defensible '
                .'and is not.',
            );
        }

        // ⚠️ The one check that is about a rule rather than about tidiness. `29`
        // §2: never store a raw IP. A caller reaching for `$request->ip()`
        // instead of HashedIp is the likely mistake, it produces something that
        // looks correct in the column, and nothing downstream would ever notice.
        //
        // ⛔ **THIS WAS A SECOND IMPLEMENTATION OF AN ABSOLUTE RULE AND IT HAD
        // ALREADY DIVERGED FROM THE FIRST — CORRECTED 2026-08-22.** It read
        // `isset($this->proof['ip']) || isset($this->proof['ip_address'])`:
        // two key names, no walk. `ConsentProof` was rewritten from exactly that
        // denylist to a walk of every string at every depth *because the
        // denylist did not work* — `client_ip`, `ipv4`, `x_forwarded_for` and
        // anything nested all went past it — and its own docblock says the
        // rewrite "would have reached one copy". This was the copy. A raw
        // address under any other key reached `customer_imports.proof`
        // unchallenged, which is 2933's prediction arriving on schedule.
        ConsentProof::refuseRawAddress($this->proof);
    }
}
