<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\UsState;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The owner's edits to a contact's header — name, tags and state (`34` §1.2).
 *
 * ⚠️ **AND THE FIRST WRITER `customers.region_code` HAS EVER HAD** (1594). The
 * column shipped in row 3 with a reader that runs on every send and nothing at
 * all able to fill it, so `ConsentService` refused every `Marketing` message
 * with `StateUnknown` — decision 487's shape, correct while it lasted (1568) and
 * invisible from every screen. `setRegion()` below is the writer; a lint in
 * `tests/Feature/Architecture/CrmTest.php` holds the column to this file and to
 * `CustomerImports`, which is the only other path a state may arrive on.
 *
 * ⚠️ **THE FIRST WRITER `customers.tags` HAS EVER HAD**, and single-contact
 * only: `44` §11 puts bulk tag in Advanced, behind the broadcast composer that
 * does not exist. A lint in `tests/Feature/Architecture/CrmTest.php` holds the
 * tags write to this file, because the column is a `jsonb` list and the next
 * screen that wants to add one tag would otherwise write the whole array from
 * whatever stale copy it was rendered with.
 *
 * `name` is deliberately not lint-held the same way — the feedback page and
 * the import both create contacts with a name, and holding creation-time
 * writes to an editor would be wrong. What this class adds for the name is the
 * one place a human *changes* it.
 *
 * ⚠️ **THE TENANT CHECK IS EXPLICIT, NOT INHERITED** — the wrong-tenant case
 * RLS cannot catch (`CLAUDE.md`): the model in hand was resolved somewhere,
 * and if that somewhere ever hands this service another tenant's contact, the
 * global scope has already been passed. `ConsentService::record()` refuses the
 * same case for the same reason.
 */
final class CustomerEditor
{
    /** A name is a line, not a paragraph. */
    public const int MAX_NAME_LENGTH = 120;

    /** `34` keeps the CRM "a rolodex with a memory" — a tag set, not a taxonomy. */
    public const int MAX_TAGS = 20;

    /**
     * How long a deleted contact can be brought back (`34` §1.2's *"tombstone
     * with restore (7 days)"*).
     *
     * A constant rather than a `platform_settings` key, on 1531's rule: this
     * bounds an action the owner is told is final, and a figure an operator can
     * move in Ops is a rule an operator can defeat silently.
     */
    public const int RESTORE_WINDOW_DAYS = 7;

    public const int MAX_TAG_LENGTH = 40;

    public function __construct(
        private readonly AuditService $audit,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function maxNameLength(): int
    {
        return $this->registry->int('crm.customer.max_name_length');
    }

    public function maxTags(): int
    {
        return $this->registry->int('crm.customer.max_tags');
    }

    public function maxTagLength(): int
    {
        return $this->registry->int('crm.customer.max_tag_length');
    }

    /**
     * Archive — the third contact state (`44` §8, decision 1327): hidden from
     * the default list and pickers, excluded from sends, restorable, timeline
     * intact.
     *
     * ⚠️ **THE SEND EXCLUSION DOES NOT LIVE HERE.** This writes the column;
     * `ConsentService::decide()` is what refuses the send, because that is
     * where every send decision already goes (285) and a send path hydrating a
     * customer id from a queue payload never runs a list query (1327, 398's
     * caller). And it is deliberately NOT a suppression: the audit row below
     * says the owner hid the contact — `crm.customer_archived`, never
     * `consent.withdrawn`, which would be a statement we authored about a
     * customer who said nothing (1225's reasoning, 1327's ruling).
     *
     * Idempotent: archiving an archived contact keeps the first moment, which
     * is when the owner actually hid them.
     */
    public function archive(Customer $customer, User $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        if ($customer->archived_at !== null) {
            return $customer;
        }

        $customer->archived_at = now();
        $customer->save();

        $this->audit->record('crm.customer_archived', 'user:'.$actor->getKey(), $customer);

        return $customer;
    }

    /**
     * Bring an archived contact back. Everything they had — timeline, notes,
     * consent — was never touched; only the shade comes off.
     */
    public function restore(Customer $customer, User $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        if ($customer->archived_at === null) {
            return $customer;
        }

        $customer->archived_at = null;
        $customer->save();

        $this->audit->record('crm.customer_restored', 'user:'.$actor->getKey(), $customer);

        return $customer;
    }

    /**
     * Delete — the last of D-205's three contact states (`34` §1.2, 1540).
     *
     * ⚠️ **A TOMBSTONE, NOT A PURGE, AND NOTHING EVER COLLECTS IT.** There is no
     * `customers:purge` command and there must not be one:
     * `consent_records.customer_id` is `cascadeOnDelete`, as are `crm_notes`,
     * `crm_tasks`, `crm_timeline` and both of `customer_merges`' keys, so a real
     * DELETE destroys the tenant's only proof that this person agreed to be
     * messaged — for a claim carrying a private right of action, seven days
     * after a click that said nothing about evidence. Decision 555 refused the
     * same thing for an attested import, and for the same reason: the remedy
     * for "stop contacting them" is suppression, and deletion would take the
     * artefact an investigator would want along with it.
     *
     * What expires is the owner's undo, never the row. See `undelete()`.
     *
     * ⚠️ **THE SEND EXCLUSION DOES NOT LIVE HERE**, exactly as it does not for
     * `archive()`: `ConsentService::decide()` asks this column at step 0 and
     * refuses with `SendRefusalReason::Deleted` — a fourth case, never folded
     * into `Archived`, because the two mean different things to whoever reads
     * the refusal.
     *
     * Idempotent, and the first moment is the one kept: re-deleting must not
     * restart a window that is already running, which is the difference between
     * an owner clicking twice and an owner getting another week they did not
     * ask for.
     */
    public function delete(Customer $customer, User $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        // A merged-away row is not a contact any more (1526) — it is a former
        // spelling of one who lives at another id, and it is already absent
        // from every view of the list. Deleting it would write a tombstone
        // nobody can see, on a row whose own undo lives somewhere else.
        if ($customer->merged_into_id !== null) {
            throw new InvalidArgumentException(
                'That contact was merged into another one, so it is already gone from '
                .'every list. Undo the merge first, or delete the contact it was '
                .'merged into.',
            );
        }

        if ($customer->deleted_at !== null) {
            return $customer;
        }

        $customer->deleted_at = now();
        $customer->save();

        $this->audit->record('crm.customer_deleted', 'user:'.$actor->getKey(), $customer);

        return $customer;
    }

    /**
     * Undo a delete, inside the seven days.
     *
     * ⚠️ **THE WINDOW IS A CONSTANT AND NOT A REGISTRY SEED** (1531's rule, held
     * a second time): a figure an operator can move in Ops is a rule an operator
     * can defeat silently, and this one bounds an action the owner is told is
     * final. It is expressed twice on purpose — as SQL in
     * `CustomerDirectory::applyView()`, which decides what the Recently deleted
     * room contains, and as the guard below, which decides what may be written.
     * A test drives both at day 6 and day 8 and asserts they agree.
     *
     * ⚠️ **THE GUARD IS NOT THE SCREEN'S** (391). The room stops listing a
     * contact at day seven and the button goes with it, but a bookmarked
     * profile still renders the tombstone — so the refusal has to be here, or
     * the door the page closes is one a kept URL walks straight through.
     *
     * `archived_at` is deliberately untouched: a contact archived and then
     * deleted returns to the archived room, not to the list. Only the later
     * shade comes off (1541).
     */
    public function undelete(Customer $customer, User $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        if ($customer->deleted_at === null) {
            return $customer;
        }

        if ($customer->deleted_at->lessThanOrEqualTo(self::restorableSince())) {
            throw new InvalidArgumentException(
                'This contact was deleted more than '.self::RESTORE_WINDOW_DAYS.' days '
                .'ago, so it can no longer be brought back. Nothing was destroyed — '
                .'their history and their consent records are intact — but the undo '
                .'has expired.',
            );
        }

        $customer->deleted_at = null;
        $customer->save();

        $this->audit->record('crm.customer_undeleted', 'user:'.$actor->getKey(), $customer);

        return $customer;
    }

    /**
     * A deleted contact came back on their own — they submitted feedback again.
     *
     * ⚠️ **THIS IGNORES THE SEVEN DAYS, AND THAT IS THE DECISION** (1543). The
     * window bounds the *owner's* undo; it says nothing about the person. A
     * tombstone six months old still resurrects, because the alternative is a
     * review — and, below the invite threshold, a triage conversation with an
     * unhappy customer waiting at the end of it — attached to a contact no
     * screen will ever show. That is decision 358's shape with somebody
     * expecting a reply.
     *
     * ⚠️ **IT IS ALSO FORCED BY THE SCHEMA, NOT ONLY CHOSEN.** `customers`
     * carries `UNIQUE(business_id, email)` and `UNIQUE(business_id, phone)` and
     * a tombstone keeps its identifiers (1540 — clearing them the way a merge
     * does would orphan every consent record, which reads the address live off
     * this row). So there is no second row to create: the person's own return
     * either lands on this one or is refused outright, and 821 settles that a
     * stop of any kind does not take the QR code down.
     *
     * Its own audit action, never `crm.customer_undeleted`: this is not the
     * owner changing their mind, and a log that cannot tell the two apart is one
     * whose reader has to guess (1225's rule).
     */
    public function resurrect(Customer $customer, string $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        if ($customer->deleted_at === null) {
            return $customer;
        }

        $customer->deleted_at = null;
        $customer->save();

        $this->audit->record('crm.customer_returned', $actor, $customer);

        return $customer;
    }

    /**
     * The moment a delete stops being undoable.
     *
     * ⚠️ **PUBLIC BECAUSE `CustomerDirectory` MUST ASK THE SAME QUESTION, AND
     * SHARING THE THRESHOLD IS NOT THE SAME AS SHARING THE COMPARISON.** The
     * two express the rule differently and must: the directory asks it in SQL
     * (`deleted_at > $since` — strictly greater, still restorable) to decide
     * what the Recently deleted room contains, and `undelete()` asks it in PHP
     * (`<= $since` — refuse) to decide what may be written. That is 1462's
     * shape, where the risk worth testing is the two expressions disagreeing at
     * the boundary. What is *not* worth duplicating is the arithmetic that
     * produces the moment, because two copies of it drift into two windows.
     */
    public static function restorableSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::RESTORE_WINDOW_DAYS);
    }

    /**
     * Rename a contact. An empty name clears it — the screens fall back to the
     * email or phone, which is more honest than a name nobody typed.
     */
    public function rename(Customer $customer, ?string $name): Customer
    {
        $this->assertBelongsToTenant($customer);

        $name = trim((string) $name);

        if (mb_strlen($name) > $this->maxNameLength()) {
            throw new InvalidArgumentException(
                'A name is at most '.$this->maxNameLength().' characters. The form '
                .'refuses this first; reaching here means a caller skipped validation.',
            );
        }

        $customer->name = $name === '' ? null : $name;
        $customer->save();

        return $customer;
    }

    /**
     * Record which state this contact is in — `customers.region_code`'s first
     * writer (1594).
     *
     * ⚠️ **THE STATE, NOT AN ADDRESS.** `BUILD-PLAN` §2.10.3 words slice 5 as
     * *"structured address capture"*, and this is deliberately narrower (1594):
     * `customers` has no address columns, `DATA-MODEL.md` defines none for a
     * contact, and the sole reader — `ConsentService::stateRefusal()` — consumes
     * a two-letter USPS code and nothing else. Storing a street address to
     * derive one field from it is more personal data held for no reader, which
     * CLAUDE.md's ambiguity rule decides the other way.
     *
     * ⚠️ **ENTERED OR IMPORTED, NEVER INFERRED** (1595). Three derivations are
     * tempting and all three are 1568's permissive branch wearing a helpful
     * face: the phone number's area code (numbers port, and a Florida NPA on a
     * Georgia resident produces confident compliance with the wrong statute);
     * the location's or the business's address (refused twice already in
     * writing — see the creating migration and the `stateFor()` seam
     * `StateMessagingRules` deleted); and a LERG/NALENND lookup, whose own
     * documentation calls it *"a heuristic about a range, not a fact about a
     * number"*. A guess here silently becomes policy.
     *
     * ⚠️ **THE VALUE IS CHECKED AGAINST A LIST, NOT A PATTERN** (1596). The
     * column's CHECK used to be `^[A-Z]{2}$`, so `ZZ` was storable — and a code
     * no legislature uses matches no `state_messaging_rules` row, which the
     * reader treats as *"nothing stricter than federal here"* and **permits**.
     * `UsState` is the list; the CHECK now carries the same one beneath.
     *
     * ⚠️ **AUDITED, WHERE `rename()` AND `retag()` ARE NOT.** A name is the
     * owner's own label for somebody. A jurisdiction decides whether a person
     * may lawfully be messaged and in which hours, so it is `29` §2 rule 42's
     * *sensitive action* — and the append-only entry is what later answers why a
     * send was allowed. The code is in the metadata because a two-letter state
     * shared by millions is not personal data, and an entry recording only that
     * *something* changed cannot answer the question it exists for.
     *
     * ⚠️ **THIS DOCBLOCK USED TO SAY "THE ONLY THING THAT CAN LATER ANSWER", AND
     * THAT WAS FALSE WHEN IT WAS WRITTEN** (1612, and 314–316's shape again).
     * The import wrote jurisdictions straight to the column and filed no entry
     * at all, so for every contact that arrived on a list the claim resolved to
     * nothing. It is true now because the import routes through this method
     * rather than because the sentence was softened — see `CustomerImports::
     * upsertCustomer()`, which is why `$actor` accepts a plain string.
     *
     * ⚠️ **A WRITE THAT CHANGES NOTHING FILES NOTHING** (1612). `CustomerProfile
     * ::saveDetails()` calls this on every Save — including a rename that never
     * touched a jurisdiction — and an unconditional audit row would file a
     * compliance record for a change that did not happen. `CustomerMerges::
     * undo()` had already written that rule down for itself in this same slice;
     * the guard belongs here so both callers obey one, and so a third caller
     * inherits it rather than having to know.
     *
     * ⚠️ **`$actor` IS `User|string` FOR THE SAME REASON `resurrect()` TAKES A
     * STRING.** Not every write of this column is a person clicking: an attested
     * import is a file, and forcing a `User` there would have meant either
     * inventing one or leaving the import outside the audit — which is the state
     * this parameter widening fixed.
     *
     * An empty string clears it, which returns the contact to `StateUnknown` and
     * refuses marketing again — the honest state for an owner who realises they
     * picked the wrong one.
     *
     * @throws InvalidArgumentException when the code is not a USPS state or territory
     */
    public function setRegion(Customer $customer, ?string $region, User|string $actor): Customer
    {
        $this->assertBelongsToTenant($customer);

        $raw = trim((string) $region);
        $state = UsState::normalise($raw);

        if ($raw !== '' && $state === null) {
            throw new InvalidArgumentException(
                'That is not a US state or territory. A code we do not recognise would '
                .'match no state rule at all, which reads as "this state has no rule" '
                .'and would let a message through the very hours a statute closes.',
            );
        }

        // ⚠️ AFTER THE REFUSAL, NEVER BEFORE IT. Returning early on an
        // unrecognisable code that happens to match nothing would swallow the
        // one message that tells the owner their spreadsheet is wrong.
        if ($customer->region_code === $state?->value) {
            return $customer;
        }

        $customer->region_code = $state?->value;
        $customer->save();

        $this->audit->record('crm.customer_region_set', $this->actorName($actor), $customer, [
            'region_code' => $state?->value,
        ]);

        return $customer;
    }

    /**
     * `audit_log`'s name for whoever did this.
     *
     * A `User` becomes `user:{id}`, which is `StaffActor::OWN_PREFIX` and the
     * vocabulary every other entry in this class uses.
     *
     * ⚠️ **A STRING IS PASSED THROUGH UNCHECKED, AND THIS DOCBLOCK USED TO SAY
     * IT "ALREADY ARRIVED IN THAT VOCABULARY"** (1621). Nothing enforces that.
     * The one caller that passes a string is `CustomerImports`, which passes
     * `ImportAttestation::$attestedBy` — a free-text field whose only validation
     * is that it is not blank, so a human name can land in `audit_log.actor`.
     * **What that costs is bounded and is stated rather than guessed at**: the
     * only reader that parses this column is `Admin\StaffActivity`, which
     * queries for `user:{id}` and `support:{id}` labels, so an actor in neither
     * shape is simply **absent** from a staff search rather than attributed to
     * the wrong person. That is the conservative direction, and it is the one an
     * append-only log can live with. Tightening belongs on the attestation,
     * where the value is first accepted and where the message can name the
     * format; a regex here would refuse an import at the audit line, three
     * services away from anything able to explain it.
     */
    private function actorName(User|string $actor): string
    {
        return is_string($actor) ? $actor : 'user:'.$actor->getKey();
    }

    /**
     * Replace a contact's tags.
     *
     * Whole-set replace rather than add/remove verbs, because that is what an
     * inline text control edits — and it is why the write is held to one place:
     * two writers replacing the whole array from different stale copies is how
     * a tag silently disappears.
     *
     * @param  list<string>  $tags
     */
    public function retag(Customer $customer, array $tags): Customer
    {
        $this->assertBelongsToTenant($customer);

        $clean = [];

        foreach ($tags as $tag) {
            $tag = trim($tag);

            if ($tag === '') {
                continue;
            }

            if (mb_strlen($tag) > $this->maxTagLength()) {
                throw new InvalidArgumentException(
                    'A tag is at most '.$this->maxTagLength().' characters.',
                );
            }

            if (! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        if (count($clean) > $this->maxTags()) {
            throw new InvalidArgumentException(
                'A contact holds at most '.$this->maxTags().' tags. `34` §1.3 keeps the '
                .'CRM a rolodex with a memory, not a sales-ops suite.',
            );
        }

        $customer->tags = $clean === [] ? null : $clean;
        $customer->save();

        return $customer;
    }

    /**
     * The wrong-tenant refusal — the case the global scope cannot catch once a
     * model is already in hand (`CLAUDE.md`'s RLS caveat, and decision 398's
     * rule that the service refuses independently of whichever screen called
     * it).
     */
    private function assertBelongsToTenant(Customer $customer): void
    {
        if ($customer->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That customer belongs to another tenant. Edits are written against the '
            .'acting business, so this would rewrite a contact that is not theirs.',
        );
    }
}
