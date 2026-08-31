<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Console\Commands\AttestRecordingAnnouncement;
use App\Services\Consent\ConsentProof;
use App\Services\Consent\ImportAttestation;
use InvalidArgumentException;

/**
 * An operator's statement that every caller hears the recording announcement
 * before they can speak (4505).
 *
 * ⚠️ **`ImportAttestation`'S SHAPE, DELIBERATELY, AND FOR THE SAME REASON.** That
 * class exists because *"an EBR claim and a hold-harmless are worth close to
 * nothing without a record of who asserted what, when, and in what words, which
 * is exactly the first thing a challenge asks for"*. Substitute *"the
 * announcement was configured"* for the EBR claim and the sentence is unchanged
 * — with the difference that here the challenge is a §632 complaint and **the
 * penalty lands on the tenant** rather than on the party who attested.
 *
 * ## Why it is a value object with a private validation rather than four columns
 *
 * {@see RecordingAnnouncement::attest()} takes one of these by type, so there is
 * no path that records an attestation without one, and a malformed one is a
 * `TypeError` or an `InvalidArgumentException` rather than a row that reads as
 * evidence and is not. Decision 285's `SendPermit` pattern, third instance.
 *
 * ⚠️ **THE CLIP ID IS PART OF THE STATEMENT AND NOT METADATA.** *"Somebody
 * attested"* is worth nothing if it does not say **which** clip they put first;
 * a re-configured number with a different announcement is a different claim, and
 * the whole point is being able to answer "what did the caller hear" a year
 * later.
 *
 * ## Rule 21 is borrowed, not re-implemented
 *
 * ⛔ **THE RAW-ADDRESS CHECK BELOW IS {@see ConsentProof::refuseRawAddress()}
 * AND MUST STAY THAT WAY (2933, 7954(c)).** This class carried its own two-key
 * denylist until 2026-08-22 — `ip` and `ip_address`, no recursion, no look at a
 * value — which is the implementation `ConsentProof` had already been rewritten
 * away from because it did not work. `29` §2 rule 21 is stated as absolute, and
 * **a second implementation of an absolute rule is one that diverges from the
 * first**; this one had, and had been diverged for the whole life of the class.
 *
 * ⚠️ **NOTHING ATTACKER-CONTROLLED REACHED IT, AND THAT IS WHY IT SURVIVED SO
 * LONG — CHECKED RATHER THAN ASSUMED, 2026-08-22.** The only constructor caller
 * in `app/` is {@see AttestRecordingAnnouncement}, which
 * passes `['channel' => 'console', 'host' => gethostname()]`; the only other
 * entry is {@see self::fromStored()}, reading a `platform_settings` row that
 * `Support\Admin\OperatedElsewhere` refuses to edit from the Ops screen and that
 * `DefaultsManifest` deliberately does not seed. **So the exposure was latent —
 * a host whose name is a bare address, or a direct edit of the row by somebody
 * who already has the database.** ⛔ **Latent is not the same as absent, and it
 * is the wrong test for an absolute rule**: what makes this worth fixing is that
 * the next caller inherits the guard rather than the argument for why the guard
 * did not matter.
 */
final readonly class RecordingAnnouncementAttestation
{
    /**
     * @param  string  $statementVersion  The published wording the operator saw,
     *                                    exactly as published.
     * @param  string  $attestedBy  `audit_log`'s actor vocabulary — `support:9`.
     * @param  string  $announcementMediaId  The clip they placed at index 0 of
     *                                       the number's Calls configuration.
     * @param  array<string, mixed>  $proof  ⚠️ A raw IP is forbidden by `29` §2
     *                                       — hash it with App\Support\HashedIp
     *                                       and pass it as `ip_hash`. The check
     *                                       is a walk of every string at every
     *                                       depth, not a list of key names.
     */
    public function __construct(
        public string $statementVersion,
        public string $attestedBy,
        public string $announcementMediaId,
        public array $proof,
    ) {
        if (trim($this->statementVersion) === '' || trim($this->statementVersion) !== $this->statementVersion) {
            throw new InvalidArgumentException(
                'An announcement attestation needs the statement version the operator actually '
                .'saw, unpadded. `ImportAttestation` refuses padding for the reason that applies '
                .'here too: two attestations of the same wording have to compare equal.',
            );
        }

        if (trim($this->attestedBy) === '') {
            throw new InvalidArgumentException(
                'An announcement attestation needs to name who made it. An anonymous '
                .'attestation is not evidence — the one question it exists to answer is who '
                .'said this.',
            );
        }

        if (trim($this->announcementMediaId) === '') {
            throw new InvalidArgumentException(
                'An announcement attestation needs the clip that was placed first on the '
                .'number. "Somebody confirmed it" cannot answer what a caller heard.',
            );
        }

        if ($this->proof === []) {
            throw new InvalidArgumentException(
                'An announcement attestation needs proof. An unproved attestation looks '
                .'defensible and is not — '.ImportAttestation::class.'\'s own words.',
            );
        }

        // ⚠️ `29` §2 rule 21: never store a raw IP. The likely mistake is
        // reaching for `$request->ip()`, it produces something that looks
        // correct in the column, and nothing downstream would notice.
        //
        // ⛔ **THIS WAS A THIRD IMPLEMENTATION OF AN ABSOLUTE RULE AND IT WAS
        // THE PRE-CORRECTION `ImportAttestation` WORD FOR WORD — CORRECTED
        // 2026-08-22 (7954(c)).** It read `isset($this->proof['ip']) ||
        // isset($this->proof['ip_address'])`: two top-level key names, no walk
        // and no look at a single value. `ConsentProof` was rewritten away from
        // exactly that denylist *because the denylist did not work* —
        // `client_ip`, `ipv4`, `x_forwarded_for` and anything nested one level
        // down all went past it — and `ImportAttestation` was moved onto the
        // walk on 2026-08-22 for the same reason. This was the copy that
        // 2933 predicted and 7954(c) named, left standing for one more wave.
        ConsentProof::refuseRawAddress($this->proof);
    }

    /**
     * The stored form.
     *
     * @return array{statement_version: string, attested_by: string, announcement_media_id: string, proof: array<string, mixed>, attested_at: string}
     */
    public function toArray(string $attestedAt): array
    {
        return [
            'statement_version' => $this->statementVersion,
            'attested_by' => $this->attestedBy,
            'announcement_media_id' => $this->announcementMediaId,
            'proof' => $this->proof,
            'attested_at' => $attestedAt,
        ];
    }

    /**
     * Rebuild one from the stored form, or null when the stored value is not an
     * attestation at all.
     *
     * ⛔ **NULL RATHER THAN A THROW, AND IT IS THE FAIL-CLOSED DIRECTION.** This
     * reads a `platform_settings` row, which an operator can edit by hand and
     * which a future manifest could seed. A blob that does not reconstruct is
     * therefore *"nobody has attested"* — the state that refuses recording —
     * rather than an exception on the ingest path, which would be an unhandled
     * error where a refusal was wanted.
     *
     * ⚠️ **SO A STORED PROOF CARRYING A RAW ADDRESS NOW READS BACK AS NO
     * ATTESTATION AT ALL**, since 2026-08-22 put rule 21's walk in the
     * constructor this re-enters. That is the fail-closed direction and it is
     * worth saying out loud, because the consequence is that **call recording
     * stops** rather than that a row is quietly cleaned. It cannot happen from
     * any path in `app/` today: no manifest seeds this key, `OperatedElsewhere`
     * refuses it on the Ops screen, and the one command that writes it cannot
     * produce an address.
     *
     * @param  mixed  $stored
     */
    public static function fromStored($stored): ?self
    {
        if (! is_array($stored)) {
            return null;
        }

        $version = $stored['statement_version'] ?? null;
        $by = $stored['attested_by'] ?? null;
        $clip = $stored['announcement_media_id'] ?? null;
        $proof = $stored['proof'] ?? null;

        if (! is_string($version) || ! is_string($by) || ! is_string($clip) || ! is_array($proof)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $proof */
            return new self($version, $by, $clip, $proof);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
