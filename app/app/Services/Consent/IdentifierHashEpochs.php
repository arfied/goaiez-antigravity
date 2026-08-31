<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\IdentifierHashEpochStatus;
use App\Enums\OutreachChannel;
use App\Models\ComplianceSuppression;
use App\Models\IdentifierHashEpoch;
use App\Models\OptOut;
use App\Models\SuppressionLift;
use App\Support\Identifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The only reader and the only writer of `identifier_hash_epochs` — the answer
 * to *"can this install still read the hashes it has stored?"*
 *
 * ## ⛔ What this exists to stop happening quietly
 *
 * `Identifier::hash()` is `hash_hmac('sha256', $normalised, config('app.key'))`.
 * Every suppression store in this schema keeps that digest and nothing else, so
 * an `APP_KEY` rotation makes every one of them unmatchable at the same instant:
 *
 *   `opt_outs`                  a carrier STOP, with **no clear copy anywhere in
 *                               this schema** — `suppressFromCarrier()` writes
 *                               the hash and a `Log::info()` that deliberately
 *                               omits the identifier
 *   `compliance_suppressions`   federal DNC, litigator, reassigned numbers
 *   `suppression_lifts`         the reversal half, matched on the same digest
 *
 * ⛔ **AND NOTHING ABOUT IT LOOKS WRONG.** A post-rotation hash is still 64 hex
 * characters, so `compliance_suppressions_value_hash_is_sha256` and its siblings
 * are satisfied. `SuppressionRegistry::missingForMarketing()` counts rows by
 * list and channel and never touches `value_hash`, so the **fail-closed
 * marketing gate goes on reporting the registers loaded while they refuse
 * nobody** — `sending_health_windows`' shape with a compliance register in it.
 * And `ConsentService::decide()` asks suppression first and the consent record
 * second, so a STOP that no longer matches falls through to a consent record
 * **that exists, because that is why the person was texted in the first place**.
 * Measured on 2026-08-22: `refused opted_out` before the rotation, `GRANTED`
 * after it, with nothing raised and nothing logged.
 *
 * ⚠️ **`previous_keys` CANNOT COVER THIS AND ITS PRESENCE IS WHAT MAKES A
 * CATASTROPHIC ROTATION LOOK SUCCESSFUL.** Laravel's `app.previous_keys` is
 * **decryption-only** — the encrypter walks it, `hash_hmac()` reads
 * `config('app.key')` directly and has no notion of an older key. An operator
 * following Laravel's own documented graceful rotation therefore finds the
 * credential vault, the OAuth tokens and the unsubscribe links all still
 * working. **Every `Crypt` path screams; every `hash_hmac` path is silent.**
 *
 * ## The mechanism
 *
 * A **fingerprint of the hashing function**, not of the key: two fixed canaries
 * are run through `Identifier::hash()` itself and a digest of the pair is
 * stored. It moves when `APP_KEY` moves. It also moves when
 * `Identifier::PHONE_REGION` moves — which that file records as a coming hazard
 * (*"every hash written before that day was computed under a different
 * assumption"*) and which nothing enforced. One row answers both.
 *
 * ⚠️ **THE PHONE CANARY IS DELIBERATELY WRITTEN IN NATIONAL FORM.** An E.164
 * value is returned as-is by `Identifier::phone()` and would be blind to the
 * region; a ten-digit one takes the branch `PHONE_REGION` governs, so changing
 * the region turns it into `null` and the fingerprint changes.
 *
 * ⚠️ **STORING THE FINGERPRINT REVEALS NOTHING.** It is a SHA-256 of two
 * HMAC-SHA-256 tags over publicly-known inputs; recovering the key from it is
 * the known-plaintext attack HMAC is defined to resist, over a 32-byte random
 * secret. What it does buy is an offline check that a candidate key is the right
 * one, which is precisely what an operator holding a backup `.env` needs.
 *
 * ## ⚠️ The epoch is recorded on WRITE and never on read
 *
 * `observe()` is called by the paths that durably store a hash. Nothing records
 * an epoch on a read, and the ordering is the whole safety property: a reader
 * that recorded what it found would, on the first send after a rotation, file
 * the **new** key as the epoch and conclude everything was current — the
 * fail-open this class exists to remove, rebuilt inside it.
 *
 * ⛔ **AND IT IS RECORDED IN THE SAME TRANSACTION AS THE WRITE, WHICH IS THE
 * HALF THAT WAS MISSING UNTIL 2026-08-22** (8200). *On write* is a statement
 * about ordering; a crash between the two statements produced durable hashes
 * with **no** epoch, which is `Unattributed`, which refuses every send on the
 * platform until a person runs a command. **The write-only rule is untouched by
 * the fix** — the same three acts record, no reader records, and what changed
 * is only that the record cannot outlive or be outlived by the hash it
 * describes. {@see observe()} carries the whole argument.
 *
 * ⚠️ **AND `Unrecorded` REFUSES NOTHING, BECAUSE IT IS CHECKED RATHER THAN
 * ASSUMED.** On a fresh checkout no hash has ever been written, so there is
 * nothing to be unable to read. A detector that cried wolf on an empty install
 * is one an operator would switch off.
 *
 * ⛔ **THAT SENTENCE WAS TRUE OF THE STATE AND FALSE OF THE CODE UNTIL
 * 2026-08-22, AND THE GAP WAS THE ORIGINAL HAZARD IN FULL** (8183). `status()`
 * derived `Unrecorded` from **this table alone** — no live epoch meant *fresh
 * checkout*, whatever `opt_outs` held. So an install that upgraded onto this
 * guard with suppression rows already in it read as fresh and refused nothing,
 * a rotation in that window was undetected exactly as before, and the first
 * STOP afterwards filed the **new** key as the sole epoch, which then read
 * `Current` for ever over registers that refuse nobody. A supported command
 * sequence reached the same place from the other end. **An empty register is
 * readable only because it is empty, so this class now asks whether it is** —
 * {@see storesHoldADurableHash()} — and answers `Unattributed` when it is not.
 *
 * ⛔ **AND THE APPLICATION MAY NOT RESOLVE `Unattributed` BY ITSELF.** A
 * backfill in the migration, or a reader filing what it happens to find, would
 * assert *these rows are readable* on evidence nobody has — 8082's fail-open
 * with a different caller, and it would read `Current` for ever afterwards. The
 * way out is {@see adopt()}, reachable only through
 * `consent:hash-epoch --adopt`, because the fact it records is one only a person
 * can supply.
 *
 * ## ⚠️ Memoised per instance, and what that costs
 *
 * The live set is read once per resolved instance rather than once per send.
 * The consequence is that a **queue worker already running when an operator
 * retires an epoch keeps refusing until it is restarted** — `composer deploy`
 * ends with `queue:restart`, which is the sequence that clears it. The
 * alternative is a query on every send decision inside a campaign loop, and the
 * stale direction here is the safe one.
 */
final class IdentifierHashEpochs
{
    /**
     * ⚠️ NANP fictional range (555-0100 to 555-0199), in **national** form so
     * that `Identifier::PHONE_REGION` is part of what this fingerprint covers.
     *
     * ⛔ **DO NOT "TIDY" THIS INTO E.164.** `Identifier::phone()` returns an
     * already-international value as given and never consults the region, so
     * `+12025550100` here would make the fingerprint blind to the one hazard
     * that file already documents — *"every hash written before that day was
     * computed under a different assumption"*. On a diff the edit reads as
     * normalising a constant. `ConsentKeyRotationTest` pins it.
     *
     * ⚠️ **PUBLIC, AND IT IS NOT A SECRET.** It is a published constant whose
     * whole purpose is to be hashed; the test that proves the region
     * participates has to be able to name it.
     */
    public const string PHONE_CANARY = '2025550100';

    /**
     * ⚠️ `.invalid` is reserved by RFC 2606 and can never be a real mailbox.
     */
    public const string EMAIL_CANARY = 'hash-canary@goaiez.invalid';

    /**
     * ⚠️ **THE ACT THE CONSOLE PERFORMS, AND IT IS AN ACT AND NOT A PERSON.**
     * `first_seen_by` is enumerated in the migration as `carrier-stop`,
     * `compliance-register`, `suppression` and this — and it used to receive
     * `--actor` here, which put an operator's name in the one column documented
     * as never carrying one (8189).
     */
    private const string CONSOLE_ACT = 'console:consent:hash-epoch';

    /**
     * The live fingerprints, or null when they have not been read yet.
     *
     * @var ?list<string>
     */
    private ?array $live = null;

    /**
     * Whether any durable identifier hash is stored at all, or null when the
     * question has not been asked yet.
     *
     * ⚠️ **ASKED ONLY WHEN THERE IS NO LIVE EPOCH**, which on any install past
     * its first STOP is never. The memo is per resolved instance, exactly as
     * `$live` is, and `forget()` clears both.
     */
    private ?bool $stored = null;

    /**
     * The digest that identifies the hashing function this process is running.
     *
     * ⚠️ **A NULL CANARY IS A ROTATION-CLASS EVENT, NOT AN EXCEPTION.** If
     * either canary stops normalising, the normal form itself has changed and
     * every stored hash is as unmatchable as it would be after a key rotation.
     * Throwing here would hard-fail the send path over it — rule 43's surviving
     * half — so it produces a fingerprint that can never equal a recorded one,
     * which routes it to the same refusal by the same path.
     */
    public function fingerprint(): string
    {
        $phone = Identifier::hash(self::PHONE_CANARY, OutreachChannel::Sms);
        $email = Identifier::hash(self::EMAIL_CANARY, OutreachChannel::Email);

        if ($phone === null || $email === null) {
            return hash('sha256', 'unnormalisable|'.($phone ?? '-').'|'.($email ?? '-'));
        }

        return hash('sha256', $phone.'|'.$email);
    }

    /**
     * Whether this install can still read the hashes it has stored.
     *
     * ⛔ **THE EMPTY-SET ARM IS TWO ANSWERS AND NOT ONE** (8183). No live epoch
     * means one of two completely different things — *nothing has ever been
     * stored*, which is readable because there is nothing to read, or *something
     * is stored and nothing here says which key wrote it*, which is not. Reading
     * both as the first is what let an in-place upgrade sit at the original
     * fail-open with the guard installed.
     */
    public function status(): IdentifierHashEpochStatus
    {
        $live = $this->liveFingerprints();

        if ($live === []) {
            return $this->storesHoldADurableHash()
                ? IdentifierHashEpochStatus::Unattributed
                : IdentifierHashEpochStatus::Unrecorded;
        }

        // ⚠️ **ANY LIVE EPOCH THAT IS NOT OURS IS A ROTATION, EVEN IF OURS IS
        // ALSO LIVE.** Two live epochs means two keys have written durable
        // hashes and one set of them is unreadable — which is exactly the state
        // this class refuses, and a membership test alone would call it current.
        return $live === [$this->fingerprint()]
            ? IdentifierHashEpochStatus::Current
            : IdentifierHashEpochStatus::Rotated;
    }

    /**
     * Whether a stored identifier hash can still be compared against a fresh
     * one. The predicate every reader asks.
     */
    public function isReadable(): bool
    {
        return $this->status()->isReadable();
    }

    /**
     * Record that a durable identifier hash has been written under this key.
     *
     * ⚠️ **IDEMPOTENT AND CHEAP AFTER THE FIRST CALL.** Once the memo holds the
     * current fingerprint there is no query and no write, so a register import
     * looping over a million rows may call it per batch without thought — though
     * `SuppressionRegistry::load()` hoists it out of the loop anyway.
     *
     * ⛔ **IT NEVER RESURRECTS A RETIRED ERA, AND THAT IS THE HALF THAT USED TO
     * BE MISSING** (8184). It appends a live row scoped to `retired_at IS NULL`;
     * matching on the fingerprint alone found a **retired** row, changed
     * nothing, and left the live set empty — which used to read as a fresh
     * install and turn the detector off while hashes were being written. A
     * return to a retired key is now an appended era, and the retirement it
     * supersedes survives as its own row.
     *
     * ⚠️ **AND IT IS STILL AN OBSERVATION RATHER THAN A CLAIM.** Only a write
     * calls this, so the row it appends is evidence; a person asserting which
     * key wrote rows that were already there goes through {@see adopt()}, which
     * takes a name.
     *
     * ## ⛔ CALL IT INSIDE THE TRANSACTION THAT STORES THE HASH (8200)
     *
     * ⛔ **THE THREE CALL SITES DO, AND UNTIL 2026-08-22 NONE OF THEM DID.** The
     * durable write and this call were separate statements with nothing joining
     * them — `suppressFromCarrier()` had no transaction at all, and
     * `SuppressionRegistry::load()`'s call sat *after* the closing brace of its
     * own. **An ordinary crash, a webhook timeout or a deploy restart in that
     * window left a durable hash with no live epoch**, which is
     * {@see IdentifierHashEpochStatus::Unattributed} — `isReadable()` false and
     * `ConsentService::decide()` refusing **every send on the platform**, with
     * no key having changed, exiting only through a person running
     * `consent:hash-epoch --adopt`. **It arrives with the first carrier STOP
     * this platform ever receives**, so the window was ahead of us rather than
     * behind. Reproduced at two sites before it was fixed
     * (`ConsentKeyRotationTest`, *the window between the durable write and the
     * epoch*).
     *
     * ⚠️ **BOTH DIRECTIONS ARE BAD, WHICH IS WHY ORDERING IS NOT ENOUGH.**
     * Recording the epoch first would file a key as having written a
     * suppression that then failed to store — the mirror fault, and this class
     * would then read `Current` over nothing. One transaction is the only shape
     * that refuses both.
     *
     * ✅ **AND IT IS SAFE INSIDE ONE, WHICH WAS CHECKED RATHER THAN ASSUMED.**
     * {@see record()} is a `firstOrCreate` against a **partial** unique index,
     * so the concurrent case is a unique violation caught and re-queried — and
     * Eloquent's `createOrFirst()` wraps that insert in
     * `withSavepointIfNeeded()`, which opens a SAVEPOINT whenever
     * `transactionLevel() > 0`. Without it the caught violation would leave
     * Postgres' *current transaction is aborted* and take the suppression down
     * with it. 8194's era semantics are unaffected: the match is scoped to
     * `retired_at IS NULL`, so a return to a retired key still appends a row.
     *
     * ⛔ **IT IS A LINT AND NOT A CONVENTION** —
     * `Architecture/ConsentTest`'s *every recorded epoch is inside the
     * transaction that stores the hash*. A **runtime** check on
     * `DB::transactionLevel()` was refused: `RefreshDatabase` holds a
     * transaction for the whole suite, so the guard would be true in every test
     * that could ever drive it and could only fire in production — 256's
     * vacuous lint and 398's unfalsifiable guard at once, on the STOP path,
     * where a throw is a dropped STOP.
     *
     * @param  string  $by  What wrote — the act, never a person and never an
     *                      identifier.
     */
    public function observe(string $by): void
    {
        $fingerprint = $this->fingerprint();

        if (in_array($fingerprint, $this->liveFingerprints(), true)) {
            return;
        }

        $this->record($fingerprint, $by);

        $this->forget();
    }

    /**
     * Record that a person has asserted that the stored hashes on this install
     * were written under the key it is running now.
     *
     * ⛔ **IT IS A CLAIM AND NOT AN OBSERVATION, WHICH IS THE WHOLE REASON IT
     * TAKES A NAME** (8185). Every other row in this table was written by the
     * act that stored a hash, so the row is *evidence*. This one is written when
     * there is no evidence to be had — an install that upgraded onto this guard
     * with suppression rows already in it, where nothing anywhere records which
     * key wrote them. **Adopting the wrong key records that those rows are
     * readable when they are not, and this guard will never fire for them
     * again**, so the operator sentence names restoring the previous key first.
     *
     * ⚠️ **REFUSED IN EVERY OTHER STATE, AND EACH REFUSAL IS A DIFFERENT
     * SENTENCE.** `Current` has nothing to adopt, `Unrecorded` has nothing
     * stored to adopt it for, and `Rotated` is a *detected* rotation where the
     * answer is the previous key or `--accept-loss` — adopting there would file
     * a second live epoch beside the one already known to be superseded.
     *
     * @return string the fingerprint adopted
     *
     * @throws InvalidArgumentException when the status is not `Unattributed`
     */
    public function adopt(string $actor, string $reason): string
    {
        [$actor, $reason] = $this->attribution($actor, $reason);

        $status = $this->status();

        if ($status !== IdentifierHashEpochStatus::Unattributed) {
            throw new InvalidArgumentException(
                'There is nothing to adopt: the status is '.$status->value.'. An adoption records '
                .'that a person asserted which key wrote the hashes already stored here, and that '
                .'question is only open when something is stored and no live epoch claims it.'
            );
        }

        $fingerprint = $this->fingerprint();

        $this->record($fingerprint, self::CONSOLE_ACT, $actor, $reason);

        $this->forget();

        // ⚠️ THE LOG RATHER THAN `audit_log`, ON `suppressFromCarrier()`'s
        // CONSTRAINT. `AuditService::record()` opens with `Tenancy::idOrFail()`
        // and an epoch belongs to no tenant, so an audit call here throws. The
        // row is the durable record; this is what a reader of the logs sees.
        Log::info('An identifier hash epoch was adopted.', [
            'fingerprint' => $fingerprint,
            'actor' => $actor,
            'reason' => $reason,
        ]);

        return $fingerprint;
    }

    /**
     * Accept that every hash written under a superseded key is permanently
     * unmatchable, and let sending resume.
     *
     * ⛔ **THIS IS NOT A REPAIR AND MUST NEVER BE DESCRIBED AS ONE.** It records
     * a decision to lose every suppression this platform holds in hash form:
     * every carrier STOP on the shared Lane A number, every DNC and litigator
     * entry, every lift. `29` §2's *"honored instantly and globally"* stops being
     * true of those rows at the moment this runs. **Restoring the previous
     * `APP_KEY` is almost always the right answer instead**, and it is free.
     *
     * ⚠️ **IT IS REACHABLE ONLY THROUGH `consent:hash-epoch --accept-loss`**,
     * with an actor and a reason, because the retired row is the only record
     * that anybody ever decided this. 4319's lesson runs the other way here: an
     * escape hatch nothing can reach is a `tinker` session against production,
     * so it exists — and it is deliberately not a `composer deploy` step.
     *
     * ⛔ **IT IS THREE WRITES IN ONE TRANSACTION AND THE MIDDLE ONE IS NEW**
     * (8186). Retiring the epoch used to restore `isLoaded()` to **true** over
     * `compliance_suppressions` rows that can never match again — the fail-open
     * this whole slice is about, rebuilt by the command that resolves it, with
     * the operator warned about it only in prose. The registers are the
     * **recoverable** half (`source_reference` names the extract each row came
     * from), so they are superseded here rather than left to a warning: the gate
     * then reports every register missing, which is true, until they are
     * genuinely re-imported. ⚠️ **It supersedes rows written under the current
     * key too, and it cannot do otherwise** — 8081 refused a key-version column,
     * so nothing distinguishes them, and a re-import is cheap where a register
     * that silently refuses nobody is not.
     *
     * ⚠️ **THE THIRD WRITE ADOPTS THE CURRENT KEY, AND IT IS AN ADOPTION RATHER
     * THAN AN OBSERVATION** — nothing has written a hash under it; a person is
     * asserting it. Without it the install would drop straight back to
     * `Unattributed` (the `opt_outs` rows survive) and go on refusing, so the
     * command would report success over a platform that still sends nothing.
     *
     * ⚠️ **THE REGISTRY ARRIVES AS AN ARGUMENT AND NOT AS A CONSTRUCTOR
     * DEPENDENCY.** `SuppressionRegistry` already depends on this class for
     * `isLoaded()` and `refusalsFor()`, so the container cannot resolve the
     * reverse edge. Method injection keeps the two writes in one transaction and
     * makes it unspellable to retire an epoch without the registers in hand.
     *
     * @return array{retired: list<string>, register_entries_superseded: int}
     *
     * @throws InvalidArgumentException when nothing stored has become unreadable
     */
    public function retire(string $actor, string $reason, SuppressionRegistry $registry): array
    {
        [$actor, $reason] = $this->attribution($actor, $reason);

        $status = $this->status();

        if ($status === IdentifierHashEpochStatus::Unrecorded) {
            throw new InvalidArgumentException(
                'There is nothing to accept the loss of: no identifier hash is stored anywhere on '
                .'this install, so nothing has become unreadable.'
            );
        }

        $current = $this->fingerprint();

        $superseded = array_values(array_filter(
            $this->liveFingerprints(),
            static fn (string $fingerprint): bool => $fingerprint !== $current,
        ));

        // ⚠️ **`Unattributed` REACHES HERE WITH NOTHING TO RETIRE, AND THAT IS
        // A SUPPORTED CASE RATHER THAN A HOLE** (8187). An operator who upgraded
        // onto this guard and knows the old key is gone cannot adopt — that
        // would claim the stored rows are readable — and has nothing to retire
        // either, because no epoch was ever recorded for the key that wrote
        // them. Refusing here would leave them with no way out but `tinker`,
        // which is 4319 exactly.
        if ($superseded === [] && $status !== IdentifierHashEpochStatus::Unattributed) {
            throw new InvalidArgumentException(
                'There is no superseded hash epoch to retire: every live epoch is this install\'s '
                .'own key, so nothing stored is unreadable.'
            );
        }

        $entries = DB::transaction(function () use ($superseded, $current, $actor, $reason, $registry): int {
            if ($superseded !== []) {
                IdentifierHashEpoch::query()
                    ->whereIn('fingerprint', $superseded)
                    ->whereNull('retired_at')
                    ->update([
                        'retired_at' => now(),
                        'retired_by' => $actor,
                        'retired_reason' => $reason,
                    ]);
            }

            $entries = $registry->supersedeEveryEntry($actor, $reason);

            $this->record($current, self::CONSOLE_ACT, $actor, $reason);

            return $entries;
        });

        $this->forget();

        // ⚠️ THE LOG RATHER THAN `audit_log`, AND IT CARRIES NO IDENTIFIER —
        // `suppressFromCarrier()`'s constraint and its answer to it (8188).
        // `AuditService::record()` opens with `Tenancy::idOrFail()` and an epoch
        // belongs to no tenant, so an audit call here throws. This is the single
        // most consequential act an operator can perform against this platform's
        // suppression registers and it used to leave no line in the log at all.
        Log::info('Identifier hash epochs were retired and every compliance register entry superseded.', [
            'retired' => count($superseded),
            'register_entries_superseded' => $entries,
            'adopted' => $current,
            'actor' => $actor,
            'reason' => $reason,
        ]);

        return ['retired' => $superseded, 'register_entries_superseded' => $entries];
    }

    /**
     * Every epoch, newest first — what `consent:hash-epoch` prints.
     *
     * @return Collection<int, IdentifierHashEpoch>
     */
    public function all(): Collection
    {
        return IdentifierHashEpoch::query()->orderByDesc('id')->get();
    }

    /**
     * Drop the memos. For the command, which reads after it writes, and for
     * tests.
     */
    public function forget(): void
    {
        $this->live = null;
        $this->stored = null;
    }

    /**
     * Whether any durable identifier hash is stored on this install at all.
     *
     * ⛔ **THE THREE STORES THIS CLASS'S OWN DOCBLOCK NAMES, AND EXISTENCE
     * ONLY** (8183). It never reads a `value_hash`, never compares one and never
     * computes one for these tables — the question is *is there anything here
     * whose readability could be in doubt*, and one row of any kind in any of
     * them answers it. `suppression_list` is deliberately absent: it matches in
     * clear, so a key change cannot make it unreadable (8089).
     *
     * ⚠️ **A REMOVED REGISTER ROW STILL COUNTS**, which is the fail-closed
     * reading and costs an operator one adoption at most. The alternative —
     * ignoring removed rows — would let an install that had superseded a whole
     * register slide back to `Unrecorded` while `opt_outs`-shaped questions were
     * still being asked of it.
     *
     * ⚠️ **ASKED ONLY WHEN THERE IS NO LIVE EPOCH.** On any install past its
     * first carrier STOP this method is never reached, and where it is, the
     * answer is memoised for the life of the resolved instance.
     */
    public function storesHoldADurableHash(): bool
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        return $this->stored = OptOut::query()->exists()
            || ComplianceSuppression::query()->exists()
            || SuppressionLift::query()->exists();
    }

    /**
     * How many durable identifier hashes are stored, per store (8270–8289).
     *
     * ⛔ **EXISTENCE'S SIBLING, AND IT READS NO `value_hash` EITHER.**
     * {@see storesHoldADurableHash()} asks *is there anything here* because that
     * is all a status derivation needs. This asks *how much*, because an
     * operator standing in front of `--accept-loss` is being asked to destroy
     * something and the size of it is the fact that decides whether they spend
     * an hour hunting for the previous key. **On this platform the honest answer
     * may well be three** — production held zero rows in all five suppression
     * tables on 2026-08-22 (8193) — and a bell that says so is a bell that stops
     * an operator agonising over nothing.
     *
     * ⚠️ **DELIBERATELY NOT MEMOISED, WHICH IS THE OPPOSITE CHOICE FROM THE ONE
     * ABOVE IT.** `$stored` is memoised because `status()` asks it on every send
     * decision an `Unattributed` install makes. Nothing asks this except a
     * raiser that has already established the registers cannot be read — at most
     * once per platform-health sweep, and never at all on a healthy install — so
     * a memo here would buy nothing and would hand back a stale count to the
     * second bell in an incident, which is the one figure that is supposed to
     * have moved.
     *
     * ⚠️ **IT COUNTS ROWS AND NOT READABILITY, AND THE DIFFERENCE IS 8081.** No
     * column anywhere records which key wrote a row, so *"how many of these are
     * unreadable"* is not a question this schema can answer — which is the whole
     * reason `Unattributed` exists. The total is what is stored; whoever renders
     * it must not promote it to a count of what is lost.
     *
     * ⛔ **THE KEYS ARE OPERATOR LANGUAGE AND NOT TABLE NAMES, AND A LINT IS
     * WHY.** *"A suppression is only ever released through the consent
     * service"* forbids any file outside `Services/Consent/` from naming
     * `opt_outs`, `suppression_lifts` or `suppression_list` as a string at all —
     * bluntly, on purpose, because a raw `DB::table(…)->delete()` is what it
     * exists to catch and it cannot tell a delete from a JSON key. The first
     * draft of the bell that reads this put those names in an
     * `operator_alerts.context`, and the lint was right to fire even though
     * nothing was being released. ⚠️ **The rename is an improvement rather than
     * a concession**: the figures are rendered on a staff screen and read out in
     * an incident, where `22`'s outcome language wants *carrier stops* rather
     * than a table name anyway.
     *
     * @return array{carrier_stops: int, register_entries: int, lifts: int, total: int}
     */
    public function storedHashCounts(): array
    {
        $stops = OptOut::query()->count();
        $registers = ComplianceSuppression::query()->count();
        $lifts = SuppressionLift::query()->count();

        return [
            'carrier_stops' => $stops,
            'register_entries' => $registers,
            'lifts' => $lifts,
            'total' => $stops + $registers + $lifts,
        ];
    }

    /**
     * An actor and a reason, trimmed, or an exception naming why both are
     * needed.
     *
     * @return array{string, string}
     */
    private function attribution(string $actor, string $reason): array
    {
        $actor = trim($actor);
        $reason = trim($reason);

        if ($actor === '' || $reason === '') {
            throw new InvalidArgumentException(
                'Adopting or retiring a hash epoch needs an actor and a reason. The row is the only '
                .'record that anybody decided what this install believes about every stored '
                .'suppression on the platform.'
            );
        }

        return [$actor, $reason];
    }

    /**
     * Write one live era of a fingerprint.
     *
     * ⛔ **`retired_at => null` IS PART OF THE MATCH AND NOT PART OF THE
     * VALUES** (8184). Matching on the fingerprint alone would find a **retired**
     * row and change nothing — which is what a re-observation after an accepted
     * loss used to do, leaving the live set empty, the status `Unrecorded` and
     * the detector off with hashes being written the whole time. Scoped to live
     * rows it appends a new era instead, and the retirement it supersedes
     * survives as its own row. The partial unique index is what makes the
     * concurrent case a `firstOrCreate` retry rather than a second live row.
     */
    private function record(
        string $fingerprint,
        string $by,
        ?string $adoptedBy = null,
        ?string $adoptedReason = null,
    ): void {
        IdentifierHashEpoch::query()->firstOrCreate(
            ['fingerprint' => $fingerprint, 'retired_at' => null],
            [
                'first_seen_at' => now(),
                'first_seen_by' => $by,
                'adopted_by' => $adoptedBy,
                'adopted_reason' => $adoptedReason,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function liveFingerprints(): array
    {
        if ($this->live !== null) {
            return $this->live;
        }

        /** @var list<string> $fingerprints */
        $fingerprints = IdentifierHashEpoch::query()
            ->whereNull('retired_at')
            ->orderBy('fingerprint')
            ->toBase()
            ->pluck('fingerprint')
            ->all();

        return $this->live = $fingerprints;
    }

    /**
     * The sentence an operator is owed about this install's suppression
     * registers, whatever state they are in.
     *
     * ⚠️ **FOUR STATES REACH HERE AND THEY HAVE DIFFERENT WAYS OUT**, so they
     * get different sentences rather than one that covers them loosely.
     * `Rotated` is a *detected* key change and the cheap way out is the previous
     * key. `Unattributed` is *we do not know*, and putting a key back does not
     * clear it — somebody has to say which key wrote the rows (8187). `22`:
     * outcome language, and what the person controls.
     *
     * ⛔ **IT USED TO THROW ON THE TWO READABLE STATES AND THAT WAS A SWALLOWED
     * BELL WAITING TO HAPPEN** (8202). `OperatorAlerts::raise()` catches
     * `Throwable`, logs it and returns null — deliberately, because its caller
     * is a webhook or a sweep whose real work has nothing to do with alerting —
     * so the first raiser to compose a summary from this method **without
     * checking `isReadable()` first** would have produced no alert and no
     * exception on exactly the arm where nothing is wrong, and then, on the day
     * something was, would have looked like it had been working all along.
     * 8093(a) and 8197(a) both leave that raiser owed to a later lane, so the
     * hazard was ahead of us rather than behind.
     *
     * ⚠️ **THE FIX IS TO MAKE THE METHOD TOTAL RATHER THAN TO GUARD ITS
     * CALLERS.** A `?string` would have traded a swallowed exception for an
     * empty string in an alert body, which is worse; a check the caller has to
     * remember is what 8082 has already been bitten by twice. Every arm now
     * returns a true sentence, so there is no wrong way to ask.
     *
     * ⚠️ **AND THE COMMAND READS ITS READABLE-STATE COPY FROM HERE**, so the
     * sentence an operator sees and the sentence an alert would carry cannot
     * drift apart.
     */
    public function operatorSentence(): string
    {
        $status = $this->status();

        return match ($status) {
            IdentifierHashEpochStatus::Rotated => <<<'TEXT'
            The identifier hashing key has changed.

            Every suppression this platform stores is a keyed hash of a phone number or an
            address, and they were all written under a key this install no longer has. Nothing
            in opt_outs, compliance_suppressions or suppression_lifts can refuse anybody, so
            every send is refused until this is resolved. That is deliberate: a register that
            cannot answer must not answer "no".

            Two ways out, and they are not equivalent.

              1. Put the previous APP_KEY back in .env and run `php artisan config:clear`.
                 Nothing is lost. This is almost always the right answer, and a rotation
                 usually reaches here by accident — `composer setup` runs `key:generate`
                 unconditionally.

              2. Accept the loss:
                 php artisan consent:hash-epoch --accept-loss --actor=… --reason=…

                 Every carrier STOP ever recorded stops refusing anybody, on a shared number
                 every tenant sends from. Every compliance register entry is superseded in the
                 same act and has to be re-imported from its source before a marketing send
                 can pass again. This cannot be undone by putting the key back.
            TEXT,
            IdentifierHashEpochStatus::Unattributed => <<<'TEXT'
            Nothing records which key wrote the suppression hashes this install is holding.

            There are rows in opt_outs, compliance_suppressions or suppression_lifts, and no
            live epoch claims them — the ordinary way to arrive here is upgrading an install
            that already had suppressions onto this check. This application cannot tell
            whether the APP_KEY in .env is the one those rows were written under, so every
            send is refused. That is deliberate: a register that cannot answer must not answer
            "no".

            Two ways out, and both need you to know which key wrote them.

              1. If APP_KEY has not changed since those rows were written, adopt it:
                 php artisan consent:hash-epoch --adopt --actor=… --reason=…

                 Nothing is lost. It records that a person asserted a fact this application
                 has no way to check, which is why it takes a name and a reason.

              2. If APP_KEY HAS changed, put the previous one back in .env, run
                 `php artisan config:clear`, and then adopt. Adopting the wrong key records
                 that these rows are readable when they are not, and nothing will ever say so
                 again.

            If the previous key is genuinely gone, `--accept-loss` is the honest answer and it
            is not a repair: every carrier STOP stops refusing anybody and every compliance
            register entry is superseded and must be re-imported from its source.
            TEXT,
            IdentifierHashEpochStatus::Current => 'Stored suppression hashes are readable by this install.',
            // ⚠️ **IT NAMES THE THREE STORES IT READ RATHER THAN ASSERTING
            // NOTHING WAS EVER WRITTEN** — 8183's correction, and the part a
            // reader can go and check for themselves.
            IdentifierHashEpochStatus::Unrecorded => 'No epoch is recorded and nothing is stored — opt_outs, '
                .'compliance_suppressions and suppression_lifts are all empty — so there is no stored hash '
                .'that could have become unreadable. The first carrier STOP or register import records an '
                .'epoch and this stops being true.',
        };
    }

    /**
     * Everything an Ops screen needs to say whether this platform is sending —
     * one call, one status, three consistent answers (9645).
     *
     * ⛔ **IT EXISTS BECAUSE THREE SEPARATE VIEW VARIABLES COULD DISAGREE, AND
     * A MUTATION PROVED IT.** {@see SuppressionReadability} carries the whole
     * argument. **This is the method a screen calls**; the three below are what
     * it is built from and what the console and the pager call directly.
     *
     * ⚠️ **THREE CALLS AND ONE QUERY.** {@see status()} memoises the live
     * fingerprints per resolved instance and the service is `scoped()`, so a
     * render costs one `SELECT` — and on any install past its first carrier
     * STOP, {@see storesHoldADurableHash()} is never reached at all.
     *
     * ⛔ **IT IS A READ AND RECORDS NOTHING**, which is the whole safety
     * property of this class: an epoch is recorded when a durable hash is
     * *written* and never when one is read. `Architecture/ConsentTest`'s *only
     * the consent write paths observe an identifier hash epoch* is what keeps a
     * screen from acquiring an `observe()` call, and
     * `SendingRefusalSurfaceTest` drives the table's row count across a render
     * of both screens in the one state where filing an epoch would be both
     * catastrophic and permanent.
     */
    public function readability(): SuppressionReadability
    {
        return new SuppressionReadability(
            $this->status(),
            $this->sendingHeadline(),
            $this->operatorSentence(),
        );
    }

    /**
     * The same fact in the length a screen heading can carry — one line, no
     * command, no newline (9640).
     *
     * ⛔ **A THIRD MEDIUM, NOT A THIRD OPINION.** {@see operatorSentence()} is a
     * console page — several paragraphs, numbered options, unbounded — and
     * {@see pagerSentence()} is what fits on a handset with nothing else around
     * it. This is the line an Ops screen puts **above** the console page, which
     * it then renders in full three inches below. Splitting the heredoc in a
     * template to reach its first line is the shape that breaks the moment
     * somebody rewraps a paragraph, and rendering the pager sentence as a
     * heading would put `--accept-loss is permanent and is not a repair` in a
     * heading, above a body that says it again at length — **the destructive
     * verb twice, and the first time with no context around it.**
     *
     * ⚠️ **ITS SUBJECT IS *WHAT IS TRUE* AND THE PAGER'S IS *WHAT TO DO*, AND
     * THAT IS THE REASON THEY ARE NOT ONE STRING.** A pager reaches somebody
     * with no screen, so it has to carry the remedy or carry nothing. A
     * headline has the remedy underneath it, so its whole job is the state —
     * and a remedy in a heading is a remedy an operator acts on before reading
     * the two paragraphs that say which of the two is safe.
     *
     * ⛔ **THE TWO READABLE ARMS SAY *NOT FOR THIS REASON* AND MAY NEVER BE
     * WIDENED TO *SENDING IS FINE*.** This class knows one thing: whether the
     * stored suppression hashes can be read. It knows nothing about
     * `messaging.global_halt`, a `sending_pauses` row, `businesses.paused_at`
     * or a spent credit balance, and a green sentence claiming any of those is
     * `sending_health_windows`' shape on a remedy screen. **Narrowness is the
     * safety property here, not modesty.**
     *
     * ⚠️ **THE TWO UNREADABLE ARMS DIFFER IN THEIR CAUSE CLAUSE ON PURPOSE**
     * (8272). The fact is identical and the **first move is not**: `Rotated` is
     * answered by putting the previous key back and `Unattributed` is not
     * answered by putting any key back at all. Two arms holding one identical
     * string is an invitation to merge them, and the merge would erase the only
     * thing on the heading that tells an operator which incident they are in.
     *
     * ⚠️ **NO COUNT, DELIBERATELY.** {@see storedHashCounts()} is three
     * `COUNT(*)`s and is the pager's, which is asked at most once per sweep.
     * This is asked on every render of two Ops screens, and the figure that
     * sizes the `--accept-loss` decision belongs beside that decision rather
     * than in a heading.
     *
     * ⚠️ **TOTAL, ON 8202's RULE AND FOR 8202's REASON** — a `match` with no
     * `default`, so a fifth state is a compile-time conversation rather than a
     * screen quietly rendering the wrong half of a boolean.
     */
    public function sendingHeadline(): string
    {
        return match ($this->status()) {
            IdentifierHashEpochStatus::Rotated => 'Every send on this platform is being refused, on every channel '
                .'and for every business, because the identifier hashing key has changed.',
            IdentifierHashEpochStatus::Unattributed => 'Every send on this platform is being refused, on every '
                .'channel and for every business, because nothing records which key wrote the suppression hashes '
                .'stored here.',
            IdentifierHashEpochStatus::Current => 'Sending is not being stopped for this reason: the suppression '
                .'hashes stored here are readable by this install.',
            IdentifierHashEpochStatus::Unrecorded => 'Sending is not being stopped for this reason: nothing is '
                .'stored that could have become unreadable.',
        };
    }

    /**
     * The same fact in the length a text message can carry — what
     * `OperatorAlertKind::SuppressionsUnreadable` says on a handset at 3am
     * (8270–8289).
     *
     * ⚠️ **THE KIND IS NAMED IN BACKTICKS AND NEVER IN A `{@see}`**, on
     * `OperatorAlerts::probeChannels()`' rule: Pint's
     * `fully_qualified_strict_types` turns a docblock reference into a real
     * `use` statement, which survives comment stripping — and an import added
     * only to name a class in prose is how a chokepoint lint acquires an entry.
     *
     * ⛔ **A SECOND METHOD RATHER THAN A SECOND CALLER OF
     * {@see operatorSentence()}, BECAUSE THAT ONE IS A CONSOLE PAGE AND
     * `OperatorAlerts::clamp()` BOUNDS A SUMMARY AT 300 CHARACTERS.** Its
     * `Rotated` arm is a heredoc with two numbered options and a blank line
     * between them; cut at 300 characters it loses the middle of option 1,
     * which on an SMS is worse than no message — the operator is left holding
     * half of *"put the previous APP_KEY back"*. **Clamping was already the
     * documented behaviour** (*"a summary two characters over the column is not
     * a reason to swallow an outage alert"*), so nothing would have failed and
     * nothing would have looked wrong. ⚠️ **THAT CUT USED TO TAKE THE TAIL AND
     * NOW TAKES THE MIDDLE** (9274), so the *last* sentence would survive today
     * — which does not restore the reasoning here, because this arm's action is
     * two numbered options and only one of them is last.
     *
     * ⚠️ **TOTAL, ON 8202's RULE AND FOR 8202's REASON.** That row made
     * `operatorSentence()` total rather than guarding its callers, because
     * `OperatorAlerts::raise()` catches `Throwable` into a log line — so a
     * raiser composing from a method that threw on the readable arms would
     * produce **no alert and no exception** on exactly the arm where nothing is
     * wrong, and then look like it had been working all along on the day
     * something was. Both readable arms return a true sentence here; no caller
     * has anything to remember.
     *
     * ⛔ **THE ORDER OF THE THREE CLAUSES IS THE WHOLE DESIGN, AND IT IS THE
     * ANSWER TO *WHY WOULD THEY TYPE `--adopt` RATHER THAN `--accept-loss`*.**
     * What is broken, then **the lossless move by name with its exact command**,
     * then the destructive one described as permanent and explicitly not a
     * repair. `--accept-loss` is the verb an operator reaches for under pressure
     * because it is the one that visibly *does something*, and the only defence
     * against that is the cheap move arriving first and fitting on the screen
     * they are already looking at.
     *
     * ⚠️ **AND THE TWO UNREADABLE ARMS NAME DIFFERENT COMMANDS, WHICH IS WHY
     * THIS IS NOT ONE SENTENCE WITH A PLACEHOLDER.** `Rotated` is fixed in
     * `.env`; `Unattributed` is fixed by `--adopt` and is **not** fixed by
     * putting a key back (8187). An operator who ran the wrong one of those has
     * either lost an hour or recorded, permanently and unfalsifiably, that rows
     * are readable when they are not.
     *
     * ⚠️ **THE COUNT IS `storedHashCounts()`' TOTAL AND IT IS DESCRIBED AS
     * STORED, NEVER AS LOST.** Nothing records which key wrote a row (8081), so
     * *"how many are unreadable"* is unanswerable; what is true is how many are
     * held, and that is the number that sizes the decision. It is re-read on
     * every bell, so the second text in an incident is not a copy of the first —
     * inbound STOPs keep landing while a rotation is unresolved.
     */
    public function pagerSentence(): string
    {
        return match ($this->status()) {
            // ⚠️ EACH OF THESE IS PINNED UNDER 300 CHARACTERS WITH AN
            // IMPLAUSIBLY LARGE COUNT, BY A TEST, because the clamp that would
            // cut them is silent.
            IdentifierHashEpochStatus::Rotated => 'The identifier hashing key has changed, so this install cannot read the '
                .number_format($this->storedHashCounts()['total']).' suppressions it stores and every send is '
                .'refused. Put the previous APP_KEY back in .env and run php artisan config:clear — nothing is '
                .'lost. --accept-loss is permanent and is not a repair.',
            IdentifierHashEpochStatus::Unattributed => 'Nothing records which key wrote the '
                .number_format($this->storedHashCounts()['total']).' suppression hashes stored here, so every '
                .'send is refused. If APP_KEY has not changed, php artisan consent:hash-epoch --adopt loses '
                .'nothing. --accept-loss is permanent and is not a repair.',
            // ⚠️ NEITHER OF THESE IS EVER SENT — the raiser asks
            // `isReadable()` first — and both say something true rather than
            // an empty string, which is 8202's refusal of a `?string` exactly.
            IdentifierHashEpochStatus::Current => 'Stored suppression hashes are readable by this install.',
            IdentifierHashEpochStatus::Unrecorded => 'Nothing is stored that could have become unreadable.',
        };
    }
}
