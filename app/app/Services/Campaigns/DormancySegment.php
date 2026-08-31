<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Models\Customer;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * `S4` — who counts as dormant.
 *
 * The rule as staged: *"dormant = no INBOUND activity or conversion event in N
 * days (seed 90, owner knob) — **our own outbound sends never reset the
 * clock**"*, which the staging note calls out as killing *"the classic
 * self-defeating-reactivation bug"*.
 *
 * ## The clock, and the one signal that actually has a writer
 *
 * ⚠️ **`customers.last_activity_at` IS THE INBOUND CLOCK AND IT HAS EXACTLY ONE
 * WRITER: `FeedbackSubmission`.** That is a customer filling in the tenant's own
 * feedback form — an inbound act by the person, which is precisely what `S4`
 * asks about. Nothing else in `app/` writes it, and in particular **no send
 * path does**, which is what makes "outbound never resets the clock" true by
 * construction rather than by a rule somebody has to remember. Decision 272's
 * check, run before the design rather than before the query (1222).
 *
 * **What is deliberately not in the union, each with its reason:**
 *
 *   `inbound_messages`  platform-scoped, keyed on a hashed identifier with no
 *                       `customer_id`, and it holds STOP/HELP keywords. Somebody
 *                       who texted STOP is not *active*, they are suppressed —
 *                       and `ConsentService` already refuses them, so counting
 *                       it would only ever move somebody *out* of an audience
 *                       they were never in.
 *   `crm_notes` and     the **owner's** activity, not the contact's. A tenant
 *   `crm_tasks`         writing a note about somebody does not mean that person
 *                       came back, and treating it as a signal would make a
 *                       diligent owner's own record-keeping hide their customers
 *                       from the campaign meant to recover them.
 *   `crm_timeline`      has a model, a factory, an RLS policy and no writer at
 *                       all — `CustomerTimeline` refuses to read it for that
 *                       exact reason and this refuses for the same one.
 *   conversion events   there is no such store in this schema.
 *                       `customers.value_to_date_cents` carries no date, so it
 *                       cannot answer *when*.
 *
 * A source joins this union when it gains a writer, never before.
 *
 * ## The contact nobody has ever heard from
 *
 * ⚠️ **AN IMPORTED LIST IS NOT DORMANT ON DAY ONE, AND THAT IS THE ANSWER
 * RATHER THAN A GAP.** `CustomerImports` writes no `last_activity_at` and no
 * `first_seen_at` — the file has no such column and inventing one from the
 * upload date would be a guess that became policy. So the clock falls back to
 * `created_at`: the day the contact entered the book. A list uploaded this
 * morning is therefore ninety days from qualifying, which is the safe answer and
 * is also useless to the tenant who most wants to reach it.
 *
 * **`CampaignAudience::ManualSelect` is the honest path for that case**, and it
 * is why manual-select mode is in the same lane as this rule rather than being a
 * convenience bolted on afterwards. Every other rail still applies to it: the
 * recorded sending basis (decision 2099), the arbiter, recipient-local quiet
 * hours, the credit debit and the confirmation.
 *
 * ⚠️ **THE ALTERNATIVE WAS CONSIDERED AND REFUSED**: treating "we have never
 * heard from them" as *immediately* dormant. It reads reasonable and it means
 * that the day after an import, an automatic campaign texts the entire uploaded
 * list — over the GOAIEZ 10DLC brand, from our own pool, on somebody else's
 * attestation (decisions 2098–2102). The complaint rate from that lands on the
 * platform across every tenant at once, and it would have arrived through a
 * default nobody chose.
 */
final class DormancySegment
{
    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * The moment before which a contact's last inbound signal makes them
     * dormant.
     */
    public function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->registry->int('campaigns.dormancy_days'));
    }

    /**
     * Narrow a customer query to the dormant ones.
     *
     * ⚠️ **`COALESCE` RATHER THAN `whereNull(...)->orWhere(...)`, AND THE
     * DIFFERENCE IS NOT STYLE.** Postgres compares NULL to a timestamp as NULL,
     * which is not true — so a bare `where('last_activity_at', '<', $cutoff)`
     * silently drops every contact who has never produced an inbound signal,
     * which is most of an imported list. Written as an OR it works and reads as
     * two rules; written as a coalesce it is one rule with a stated fallback,
     * which is what it is.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function apply(Builder $query): Builder
    {
        Tenancy::idOrFail();

        return $query->whereRaw(
            'coalesce(last_activity_at, first_seen_at, created_at) < ?',
            [$this->cutoff()],
        );
    }

    /**
     * Whether this one contact is dormant right now.
     *
     * ⚠️ **THE SAME RULE AS `apply()` OR IT IS WORTHLESS**, so it is expressed
     * through it rather than beside it. Two spellings of one predicate is how
     * the badge and the gate drift apart — `ConsentService::standingPredicate()`
     * records the identical hazard, and there it was a contact shown as
     * reachable that a send refused.
     */
    public function includes(Customer $customer): bool
    {
        return $this->apply(Customer::query())->whereKey($customer->getKey())->exists();
    }
}
