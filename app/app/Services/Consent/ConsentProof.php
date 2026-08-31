<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CaptureSurface;
use App\Enums\ProofHashDomain;
use InvalidArgumentException;

/**
 * What makes a recorded agreement defensible, checked once for every kind.
 *
 * ⚠️ EXTRACTED FROM `ConsentCapture` RATHER THAN COPIED OUT OF IT (2933). These
 * four guards were private methods there, which was right while consent_records
 * was the only table storing a proof blob. `review_phi_consents` now stores one
 * too (2079-2081) and is deliberately *not* a consent record — no channel, no
 * customer, no messaging lane — so it cannot reach those guards through
 * `ConsentCapture` without claiming a `ConsentType`, and `ConsentType`'s own
 * docblock is explicit that its cases are messaging strengths and that writing a
 * false one puts an untrue row in the table whose entire job is to be true.
 *
 * The alternative was a second `rejectRawIp()`. `29` §2 rule 21 is absolute and
 * a second implementation of an absolute rule is one that will diverge from the
 * first — this one already had to be rewritten once, from a denylist of key
 * names to a walk of every string at every depth, and the rewrite would have
 * reached one copy.
 *
 * `ConsentCapture`'s public constructor is unchanged; it builds one of these.
 *
 * ⛔ **AND IT IS NO LONGER CONSTRUCTED AND THROWN AWAY.** Every caller used to
 * build one for its guards and store the array it had passed in — *"the object
 * is built for its constructor and deliberately not kept"*, in four docblocks.
 * That was true while this class only ever refused things. It now also
 * **transforms**: `$proof` is the blob as it is allowed to be stored, with its
 * `ip_hash` scoped to the record type by {@see ProofHash}, so a caller that
 * stores its own copy stores an unscoped hash and nothing would say so. **The
 * domain is a required constructor argument for exactly that reason** — there is
 * no way to reach these guards without naming the table the proof is going into.
 * See {@see ProofHashDomain} for why the join matters and 7888 for the ruling
 * that reached one table out of six.
 */
final readonly class ConsentProof
{
    /**
     * What `29` §2 rule 7 requires of a surface we render ourselves.
     *
     * The wording version and the timestamp are columns — `disclosure_version`
     * and `created_at` — so what has to be in the proof blob is the rest: where
     * it happened, from what, and hashed rather than raw.
     *
     * @var list<string>
     */
    private const array REQUIRED_PROOF = ['url', 'ip_hash', 'user_agent'];

    /**
     * The blob as it is allowed to be stored — guarded, then scoped.
     *
     * ⚠️ **NOT THE ARRAY THAT WAS PASSED IN.** `ip_hash` is re-hashed under the
     * record's own domain and {@see ProofHash::DOMAIN_KEY} is stamped beside it.
     * Everything else is untouched, and a blob with no `ip_hash` comes through
     * exactly as it arrived.
     *
     * @var array<string, mixed>
     */
    public array $proof;

    /**
     * @param  array<string, mixed>  $proof  As captured — a raw `HashedIp` value,
     *                                       never a scoped one.
     */
    public function __construct(
        array $proof,
        public CaptureSurface $captureSurface,
        public string $method,
        public ProofHashDomain $domain,
    ) {
        if ($proof === []) {
            throw new InvalidArgumentException(
                'A consent record needs proof. An unproved record looks defensible and is not.',
            );
        }

        // ⚠️ EVERY GUARD RUNS AGAINST WHAT THE CALLER PASSED, not against the
        // scoped copy. The raw-IP walk in particular has to see the value the
        // caller actually built: scoping hashes it, and a hashed address passes
        // `FILTER_VALIDATE_IP` no more than any other digest does — so guarding
        // the transformed blob would be an outer guard refusing nothing (398).
        self::refuseRawAddress($proof);
        $this->requireProvableProof($proof);
        $this->rejectPreCheckedBox($proof);

        $this->proof = ProofHash::scope($domain, $proof);
    }

    /**
     * `29` §2 rule 21: never store raw IP, anywhere, including here.
     *
     * A denylist of key names was the first version of this and it was the wrong
     * shape for a rule stated as absolute: `client_ip`, `ipv4`,
     * `x_forwarded_for` and anything nested one level down all walked past it,
     * into a jsonb column where nothing downstream would notice. So the names are
     * still checked — they give the clearer error — and then every string value
     * at every depth is checked for being an address.
     *
     * A URL containing an IP host survives, because the whole string is what is
     * validated and `http://192.0.2.1/x` is not an address. That is the intended
     * line: this rule is about storing somebody's IP, not about never writing one
     * down anywhere.
     *
     * ⚠️ **PUBLIC AND STATIC SINCE 2026-08-22, FOR 2933's OWN REASON.**
     * `ImportAttestation` carried a second implementation of this absolute rule
     * and it had already diverged — two key names where this walks every string
     * at every depth — so a raw address under any other key reached
     * `customer_imports.proof` unchallenged. It calls this now. That class is not
     * a `CaptureSurface` and cannot build a `ConsentProof`, which is why the walk
     * is exposed rather than the object reused.
     *
     * @param  array<array-key, mixed>  $proof
     */
    public static function refuseRawAddress(array $proof): void
    {
        foreach ($proof as $key => $value) {
            if (is_string($key) && preg_match('/(^|_)ips?(_|$)/i', $key) === 1 && $key !== 'ip_hash') {
                throw new InvalidArgumentException(
                    "Proof carries ip_hash, never {$key}. Raw IP is never stored.",
                );
            }

            if (is_array($value)) {
                self::refuseRawAddress($value);

                continue;
            }

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false) {
                throw new InvalidArgumentException(
                    "Proof carries ip_hash, never a raw address; found one under '{$key}'. "
                    .'Raw IP is never stored.',
                );
            }
        }
    }

    /**
     * A surface we render has to prove itself; an attestation cannot.
     *
     * `CaptureSurface` already draws this line and, until now, only in prose:
     * FeedbackPage, Chat, Booking and Qr are pages this application serves, so
     * the URL, the hashed IP and the user agent are all in hand at the moment of
     * capture and their absence means somebody skipped a step. Import, Pos, Call
     * and Manual are a tenant asserting something that happened elsewhere — there
     * is no URL and no user agent to record, and demanding them would only teach
     * callers to invent values.
     *
     * `ConsentCapture`'s docblock claims an unproved record is "unrepresentable
     * rather than merely discouraged". A non-empty array check did not make that
     * true: `['foo' => 'bar']` satisfied it and produced a Lane A record with no
     * URL, no hash and no agent — undefendable in exactly the way the sentence
     * says is impossible.
     *
     * @param  array<string, mixed>  $proof
     */
    private function requireProvableProof(array $proof): void
    {
        if (! $this->captureSurface->isSelfRendered()) {
            return;
        }

        $missing = array_values(array_diff(self::REQUIRED_PROOF, array_keys($proof)));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Consent captured on %s must carry %s in its proof; missing %s. `29` §2 '
                    .'rule 7 lists what makes a record defensible, and a surface we render '
                    .'ourselves has every one of them in hand.',
                    $this->captureSurface->value,
                    implode(', ', self::REQUIRED_PROOF),
                    implode(', ', $missing),
                ),
            );
        }
    }

    /**
     * `29` §12.1 puts "a pre-checked consent box fails validation" on the
     * build-failing list, and the form that renders the box is a later slice.
     *
     * The check lives here anyway, because this is the chokepoint every record
     * passes through and the form is not. A box the person did not tick is not
     * consent in any jurisdiction that matters, and the value recording that is
     * the one a hurried implementation copies from a fixture.
     *
     * @param  array<string, mixed>  $proof
     */
    private function rejectPreCheckedBox(array $proof): void
    {
        $state = $proof['checkbox_state'] ?? null;

        if ($this->method !== 'checkbox' && $state === null) {
            return;
        }

        if ($state !== 'checked_by_user') {
            throw new InvalidArgumentException(
                'A checkbox consent record must carry checkbox_state = checked_by_user. '
                .'`29` §2: every consent checkbox is unchecked by default, so a box in any '
                .'other state is not consent.',
            );
        }
    }
}
