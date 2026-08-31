<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Business;
use App\Models\Location;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * How many locations this business has bought, and how many it has left.
 *
 * ⛔ **THE ADD-ON SKU HAD A PRICE, AN INSTALMENT SPLIT, A COLUMN AND NO BUYER**
 * (2753). {@see PlanCharges::additionalLocationPriceFor()} has priced it correctly
 * since row 22 slice D, `Subscriptions::agreedPriceColumns()` has written
 * `additional_locations` on every subscription this application has ever created,
 * and **every one of those writes was a literal zero** — because
 * `BillingTermRequest::selection()` had no quantity to pass and said so in its own
 * docblock. That is `CLAUDE.md`'s writerless-column shape with the arrow reversed:
 * not a column nobody reads, but a column nobody could make say anything but zero.
 *
 * ## What "permitted" is, and why it is a sum rather than a column
 *
 * The base plan **includes one location** (`CLAUDE.md` §Commercial model), and
 * every location beyond that is a purchased add-on. So the permitted count is
 * `1 + additional_locations`, and the one is written here rather than in the
 * registry for the reason 2142 gives inverted: the registry holds figures an
 * operator may move, and "the base plan includes one location" is not a figure —
 * it is what the base price *means*. An Ops edit that made it two would reprice
 * every subscription in the schema without touching a price.
 *
 * ⛔ **IT READS THE AGREED COUNT ON THE ROW AND NEVER THE REGISTRY** (3443, 3444).
 * The count a tenant bought is a term of their agreement exactly as its price is,
 * so this is the same read {@see PlanCharges::agreedPriceFor()} makes and for the
 * same reason. There is no registry key that could answer it.
 *
 * ⚠️ **A ROW THAT PREDATES THE COLUMNS FALLS BACK TO THE INCLUDED ONE**, which is
 * what every such tenant has today: `additional_locations` is nullable with no
 * backfill (see its migration), and no subscription in this schema was ever sold
 * with a non-zero count, so reading null as "one included location" states the
 * present fact rather than guessing. A business with **no subscription row at
 * all** gets the same answer — and that is deliberately the *conservative*
 * direction here, unlike {@see Subscriptions::isEntitled()}, which fails open on
 * the same population. The failure modes are not symmetric: guessing high there
 * locks a paying customer out, guessing high here hands out locations nobody
 * billed for.
 *
 * ⚠️ **`used()` COUNTS ROWS, NOT A COUNTER.** A stored count is a second source of
 * truth for a fact one `COUNT(*)` already answers, and the two would disagree the
 * first time a location was deleted. The query is tenant-scoped by the global
 * scope on {@see Location} like every other read in this application.
 */
final class LocationAllowance
{
    /**
     * Locations the base plan includes before anything is bought.
     */
    public const int INCLUDED = 1;

    public function __construct(
        private readonly Subscriptions $subscriptions = new Subscriptions,
    ) {}

    /**
     * How many locations this business is entitled to hold.
     */
    public function permitted(Business $business): int
    {
        return self::INCLUDED + $this->purchased($business);
    }

    /**
     * How many locations beyond the included one this business has paid for.
     */
    public function purchased(Business $business): int
    {
        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription) {
            return 0;
        }

        // ⚠️ `max(0, …)` rather than trusting the column, even though the
        // migration's CHECK makes a negative unrepresentable. A negative here
        // would subtract from the included location and refuse a tenant the one
        // they are paying for, which is the one direction this method must not be
        // able to fail in.
        return max(0, $subscription->additional_locations ?? 0);
    }

    /**
     * How many locations this business actually holds.
     */
    public function used(Business $business): int
    {
        return Location::query()->where('business_id', $business->id)->count();
    }

    /**
     * How many more it may create before it has to buy one.
     *
     * ⚠️ **NEVER NEGATIVE.** A tenant holding more locations than their allowance
     * — see `minimumPurchasableFor()` for the only way that happens — should read
     * as "none to add", not as a negative that a caller comparing `> 0` handles
     * correctly and a caller adding to a total does not.
     */
    public function remaining(Business $business): int
    {
        return max(0, $this->permitted($business) - $this->used($business));
    }

    /**
     * Whether this business may create another location right now.
     *
     * ⚠️ **A READ, AND ONLY SAFE AS ONE** — see {@see self::hasRoomUnderLock()}.
     * Rendering a screen from this is right; deciding to make rows from it is
     * the check-then-act race below.
     */
    public function hasRoom(Business $business): bool
    {
        return $this->remaining($business) > 0;
    }

    /**
     * The same question, asked so that two askers cannot both be told yes (4642).
     *
     * ⛔ **`hasRoom()` FOLLOWED BY A CREATE IS CHECK-THEN-ACT WITH NOTHING IN
     * BETWEEN.** `used()` is a `COUNT(*)` and the create is a separate statement,
     * so two presses of *Set up a location* landing together both counted one
     * location against an allowance of two and both created one: **the tenant
     * ends up holding three locations on a plan that covers two**, each publishing
     * a feedback page on a public domain and each billed for nothing. Nothing
     * anywhere notices — every screen afterwards renders a coherent, wrong number,
     * which is the same shape as an operator typing a count below what the tenant
     * holds (`Subscriptions::recordAdditionalLocations()`, whose guard exists for
     * that reason).
     *
     * ⚠️ **THE LOCK IS `CreditLedger::move()`'s, DELIBERATELY THE SAME ONE.** That
     * class already serialises every credit movement for a tenant by taking
     * `SELECT … FOR UPDATE` on the **business row**, and its reasoning transfers
     * without change: locking what is being counted cannot work, because the
     * first concurrent pair may be counting an empty set and would lock nothing.
     * The business row always exists. Inventing a second locking convention for
     * the same tenant would also be inventing a lock-ordering problem between
     * them.
     *
     * ⛔ **IT MUST BE CALLED INSIDE THE TRANSACTION THAT CREATES THE LOCATION.**
     * A row lock is held until the transaction ends, so calling this in
     * autocommit takes the lock and gives it back before the caller has decided
     * anything — a lock that reads exactly like a lock and serialises nothing.
     * The refusal below is what stops that being silent.
     *
     * ⚠️ **AND THAT REFUSAL CANNOT BE DRIVEN RED IN THIS HARNESS**, which is said
     * rather than papered over (`CLAUDE.md`: some claims cannot be proven here,
     * and a test named for one it does not make is worse than none). Every feature
     * test runs inside `RefreshDatabase`'s own transaction, so `transactionLevel()`
     * is never zero under the runner. What *is* proven is the lock itself: the
     * query log shows `for update` on `businesses` before the `insert into
     * locations`, and removing either reddens that test.
     *
     * @throws RuntimeException Called outside a transaction, where the lock would
     *                          be released before the caller could use it.
     */
    public function hasRoomUnderLock(Business $business): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                'The location allowance must be claimed inside the transaction that creates '
                .'the location: a row lock taken in autocommit is released before the caller '
                .'has decided anything, so two concurrent presses would both be told there is '
                .'room.'
            );
        }

        Business::query()->whereKey($business->id)->lockForUpdate()->first();

        return $this->remaining($business) > 0;
    }

    /*
     * ⚠️ THE FLOOR — "a count may not be set below what the tenant already holds"
     * — IS NOT HERE, AND WHERE IT ENDED UP IS WORTH LEAVING BEHIND.
     *
     * The count is *replaced* rather than incremented, so recording zero for a
     * tenant holding three locations would leave them entitled to one while
     * holding three — the other two still live, still publishing feedback pages,
     * and billed for nothing.
     *
     * ⛔ IT WAS WRITTEN HERE, ARGUED TO BE UNREACHABLE, DELETED, AND THEN BECAME
     * REACHABLE INSIDE THE SAME SLICE. The argument for deleting it was sound
     * about checkouts and only about checkouts: both refuse once a gateway
     * subscription exists, so a business reaches one at most once in its life
     * holding exactly the one location `TenantProvisioner` made it. Then the
     * operator screen arrived — openable any number of times against a tenant with
     * any number of locations — and the case the argument had ruled out was live.
     * **`CLAUDE.md`'s note that a fix wave is a change like any other, arriving
     * inside one slice.**
     *
     * It now lives in {@see Subscriptions::recordAdditionalLocations()}, beside
     * the other rules about what that column may say, rather than in whichever
     * screen happens to be writing it.
     */
}
