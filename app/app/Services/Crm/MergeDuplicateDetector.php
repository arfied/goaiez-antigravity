<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Customer;
use App\Services\Config\DefaultsRegistry;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The same person, twice (`34` §1.2 — *"auto-detected (phone/email match)"*).
 *
 * ⚠️ **A RAW-STRING DETECTOR WOULD FIND ZERO DUPLICATES, FOREVER, BY
 * CONSTRUCTION — AND EVERY TEST OF IT WOULD PASS.** `customers` carries
 * `UNIQUE(business_id, phone)` and `UNIQUE(business_id, email)`, so two contacts
 * in one tenant can never hold the *identical* string. The only duplicate that
 * can exist is **two spellings of one normalised identifier**: `+1 555-123-4567`
 * beside `5551234567`, or `Ada@example.test` beside `ada@example.test`. So
 * `App\Support\Identifier` is not a nicety here, it is the entire feature — this
 * is decision 256's vacuity waiting in a detector, and the shape of it is
 * exactly `PlacesSpendTest` pinning code to recorded prices and being unable to
 * fail when a recorded price was wrong.
 *
 * ## Narrow in SQL, decide in PHP
 *
 * `Identifier` normalises in PHP and cannot be called from a query, so the SQL
 * below is a **superset filter** and never the answer: last ten digits for a
 * phone, `lower()` for an email. Every candidate it returns is then confirmed
 * against `Identifier::normalise()`, which is the one authority. Two spellings
 * of one rule is `decide()`/`permit()`'s stated hazard, and it is defused here
 * by the SQL being deliberately looser than the rule rather than a second copy
 * of it: a superset costs a discarded row, and a *subset* would silently stop
 * detecting a shape somebody added to `Identifier` later.
 *
 * ## What is deliberately not built
 *
 * **The nightly job.** `34` Part 6 lists `MergeDuplicateDetector` as a job run
 * per tenant, and a job needs somewhere to put its findings — which is `44` §8's
 * Advanced duplicate *queue*, explicitly a later half (`BUILD-PLAN` §2.9.3). A
 * scheduled command writing into a table that does not exist is decision 272's
 * shape arriving from the other end. The banner asks this class on the profile
 * it renders, which is where §1.2 puts the answer.
 *
 * **Name similarity, and any fuzzy match at all.** §1.2 says *"phone/email
 * match"* and means it. A name match proposes folding two real people together
 * on the strength of a common name, and the merge is only undoable for thirty
 * days.
 */
final class MergeDuplicateDetector
{
    /**
     * How many candidates a profile offers.
     *
     * ⚠️ A ceiling rather than a page: §1.2's banner is *"this looks like the
     * same person"*, one decision at a time, and a contact with more than a
     * handful of spellings is a data problem the duplicate *queue* (`44` §8) is
     * for. Reached only by an import that wrote the same number six ways.
     */
    public const int MAX_CANDIDATES = 5;

    /**
     * How many trailing digits the SQL narrowing compares.
     *
     * The NANP national number, which is what `Identifier::phone()` builds every
     * US number from — so `+15551234567`, `15551234567` and `5551234567` all
     * share these ten. It is a superset filter and nothing more: an
     * international number whose last ten digits coincide arrives as a candidate
     * and `isDuplicate()` throws it away.
     */
    public const int COMPARED_DIGITS = 10;

    public function comparedDigits(): int
    {
        return app(DefaultsRegistry::class)->int('crm.merge.compared_digits');
    }

    /**
     * The contacts that look like this one, newest activity first.
     *
     * ⚠️ **ARCHIVED AND ALREADY-MERGED CONTACTS ARE EXCLUDED, IN OPPOSITE
     * DIRECTIONS.** A merged-away row is not a candidate because it is not a
     * contact any more — it is a former spelling of somebody who is elsewhere,
     * and offering it would build the chain `CustomerMerges::merge()` refuses.
     * An archived one is excluded because the owner has already dealt with them
     * (`44` §8), and a banner about a contact they deliberately put away is
     * noise on the screen of the one they did not — with the consequence stated
     * rather than hidden: an archived duplicate goes on holding its identifier,
     * and the way to merge it is to restore it first.
     *
     * @return Collection<int, Customer>
     */
    public function candidatesFor(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        // ⚠️ **THE SUBJECT IS FILTERED THE SAME WAY THE CANDIDATES ARE, AND THE
        // FIRST VERSION FILTERED ONLY THE CANDIDATES.** Asymmetry there is not
        // harmless: an owner on an *archived* contact's profile was offered a
        // live duplicate to fold into it, which removes a contact from the list
        // without the owner ever archiving it. And the assertion that a
        // folded-away contact's profile shows no banner was passing for the
        // wrong reason — a merge clears that row's identifiers, so the early
        // return below caught it (decision 398's outer guard, the same one this
        // file already records at `merged_into_id` below).
        //
        // Delete joins that list on the same reasoning (1545), and unlike
        // archive it is symmetric in a second way: a tombstone keeps its
        // identifiers, so nothing downstream would refuse it the way a
        // merged-away row is refused by `isDuplicate()`. Both sides, or an
        // owner tidying their book folds a live contact into a deleted one and
        // takes them off the list without ever pressing delete.
        if ($customer->archived_at !== null
            || $customer->deleted_at !== null
            || $customer->merged_into_id !== null) {
            return collect();
        }

        $phone = Identifier::phone($customer->phone);
        $email = Identifier::email($customer->email);

        // ⚠️ A BOUND ON THE QUERY, NOT A CORRECTNESS GUARD, AND THE FIRST
        // VERSION OF THIS FILE CLAIMED OTHERWISE. It had a `whereRaw('false')`
        // seeding the OR group below, on the reasoning that a group with no
        // clauses compiles to no SQL at all — true — and would therefore
        // "return every contact the tenant has and propose merging the whole
        // book" — **false**. `isDuplicate()` below is the authority and refuses
        // every one of them, because a contact with no normalisable identifier
        // matches nobody by definition. So the guard prevented a wasted scan
        // and its comment described a data-loss bug it was not preventing:
        // 314–316's *"a stated enforcement layer is what stops the next
        // reviewer looking"*, found by mutation rather than by review.
        //
        // What survives is this early return, which is the honest version of
        // the same thing — the profile of an anonymous feedback submission
        // renders on every visit, and it has no identifiers.
        if ($phone === null && $email === null) {
            return collect();
        }

        return Customer::query()
            ->whereKeyNot($customer->getKey())
            ->whereNull('archived_at')
            // ⚠️ Falsifiable where the clause above it is not: a tombstone keeps
            // its identifiers (1540), so a deleted contact matches
            // `isDuplicate()` perfectly and deleting this line proposes them.
            ->whereNull('deleted_at')
            // ⚠️ **UNFALSIFIABLE THROUGH `merge()` AND KEPT ANYWAY** — a merge
            // clears the folded-away row's identifiers, so `isDuplicate()`
            // already refuses it and mutation found this clause surviving
            // deletion (decision 398's outer guard, again). It stays because
            // "a merged-away contact is never proposed" must hold whatever
            // else is true of the row, and proposing one would build the chain
            // `merge()` refuses. A test constructs the state directly — a
            // merged-away row still carrying an identifier — so the clause is
            // falsifiable even though the service cannot produce that row.
            ->whereNull('merged_into_id')
            ->where(function (Builder $query) use ($phone, $email): void {
                if ($phone !== null) {
                    $query->orWhereRaw(
                        "right(regexp_replace(coalesce(phone, ''), '\\D', '', 'g'), "
                        .$this->comparedDigits().') = ?',
                        [$this->tail($phone)],
                    );
                }

                if ($email !== null) {
                    $query->orWhereRaw("lower(coalesce(email, '')) = ?", [$email]);
                }
            })
            ->orderByRaw('last_activity_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->limit(self::MAX_CANDIDATES * 4)
            ->get()
            ->filter(fn (Customer $candidate): bool => self::isDuplicate($customer, $candidate))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    /**
     * Whether these two rows are the same person by `34` §1.2's rule.
     *
     * ⚠️ **THIS IS THE DECISION AND THE QUERY ABOVE IS ONLY A NARROWING.** It is
     * public and static because `CustomerMerges` has no reason to call it and
     * the tests have every reason to: a rule that can only be exercised through
     * a query is one whose edge cases get asserted against SQL semantics by
     * accident.
     */
    public static function isDuplicate(Customer $one, Customer $other): bool
    {
        $phone = Identifier::phone($one->phone);
        $email = Identifier::email($one->email);

        if ($phone !== null && $phone === Identifier::phone($other->phone)) {
            return true;
        }

        return $email !== null && $email === Identifier::email($other->email);
    }

    private function tail(string $normalisedPhone): string
    {
        $digits = preg_replace('/\D/', '', $normalisedPhone) ?? '';

        return substr($digits, -$this->comparedDigits());
    }
}
