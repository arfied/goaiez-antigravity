<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Console\Commands\WatchPlatformComplaintRate;
use App\Enums\OperatorAlertKind;

/**
 * What one pass over every tenant found — the aggregate, **and the tenants whose
 * own receipts went silent inside it** (7720–7739).
 *
 * ## Why this exists rather than two more fields on `PlatformRateSample`
 *
 * ⛔ **{@see PlatformRateSample}'S OWN CLASS DOCBLOCK FORBIDS IT IN THOSE
 * WORDS**: it is a different type from {@see SendingRates} because *"a shared
 * type would have fields that are null in one of its two uses, and somebody
 * would eventually read one."* A sample is built in two places — by
 * {@see PlatformComplaintRate::measure()}, which walks every tenant, and by
 * {@see PlatformComplaintRate::lastReported()}, which rehydrates six integers
 * out of a cache entry that deliberately carries **no tenant identity at all**.
 * A blind-tenant list on that type would be honest in the first and meaningless
 * in the second, which is the shape the type was split to avoid.
 *
 * ⚠️ **AND A DEFAULT WAS REFUSED FOR 7487's REASON.** `PlatformRateSample::$sent`
 * is required rather than defaulted precisely because a forgotten default makes
 * a detector *"present, plausible and off"*. An empty blind list defaulted onto
 * eight existing construction sites across four test files would have been that
 * failure again, one wave later, in the same class that carries the warning —
 * so the sample is untouched and the new fact travels beside it.
 *
 * ## The aggregate and the tenants answer two different questions
 *
 * ⛔ **AND THAT IS THE WHOLE OF THIS SLICE.**
 * {@see PlatformRateSample::reportingHasGoneSilent()} asks whether **nothing at
 * all** came back, over sums taken across every tenant — so one tenant reporting
 * normally puts a non-zero `delivered` into the sum and the answer is false for
 * everybody. A tenant with four thousand blind sends is invisible the moment any
 * other tenant's receipts are working, which on a platform with more than one
 * customer is the ordinary case. The map below is the per-tenant answer to the
 * same question, asked inside each tenant's own context with
 * {@see SendingRates::trafficWithoutOutcomes()} — the identical predicate
 * `SendingGuard::reportBlindSpot()` uses one tenant at a time.
 *
 * ⚠️ **THE TWO BELLS ARE MUTUALLY EXCLUSIVE BY CONSTRUCTION, AND THAT IS
 * DECIDED AT THE RAISER RATHER THAN HERE.** This object reports what was
 * measured; {@see WatchPlatformComplaintRate} decides which bell that is.
 */
final readonly class PlatformSweep
{
    /**
     * ⚠️ **A MAP RATHER THAN A LIST, BECAUSE THE SIZE OF EACH TENANT'S SILENCE
     * IS WHAT RANKS THEM.** When more accounts are blind than one bell can name,
     * the ones worth naming are the ones with the most traffic already on the
     * wire — {@see self::worstBlindTenants()} — and a bare list of ids could not
     * answer that without a second read.
     *
     * @param  array<int, int>  $blindTenants  business id => how many messages
     *                                         that tenant handed to a carrier in
     *                                         the window with **no** outcome of
     *                                         any kind reported back. Empty when
     *                                         nobody is blind **and** when the
     *                                         per-tenant trip is switched off,
     *                                         which are deliberately the same
     *                                         answer: with no trip configured
     *                                         there is nothing to be blind about.
     */
    public function __construct(
        public PlatformRateSample $sample,
        public array $blindTenants,
    ) {}

    /**
     * How many tenants are individually blind.
     *
     * ⚠️ **NEVER COMPARED WITH `sample->tenantCount` TO INFER A PLATFORM-WIDE
     * BLACKOUT.** *"Every tenant is blind"* and *"the platform reported nothing"*
     * are different statements — a tenant under the floor is not counted here and
     * still contributes its `sent` to the aggregate.
     * {@see PlatformRateSample::reportingHasGoneSilent()} is the only answer to
     * the second question and stays that way, on 2402's one-formula rule.
     */
    public function blindTenantCount(): int
    {
        return count($this->blindTenants);
    }

    /**
     * How many messages went to members of the public with the per-tenant
     * complaint trip unable to fire behind them.
     *
     * ⚠️ **THE FIGURE THE BELL LEADS WITH**, because it says how much this has
     * already cost rather than how many accounts are involved: one tenant with
     * forty thousand blind sends on the shared GOAIEZ 10DLC registration is a
     * worse night than forty tenants with fifty each.
     */
    public function blindSent(): int
    {
        return array_sum($this->blindTenants);
    }

    /**
     * The blind tenants worth naming, heaviest traffic first.
     *
     * ⛔ **A RENDERING BOUND AND NOT A THRESHOLD** — `OperatorAlerts::clamp()`'s
     * own 300-character bound, applied to evidence instead of to prose.
     * Nothing is decided by `$limit`: the count and the total above are always
     * whole, the bell rings identically whether one account or nine hundred are
     * involved, and this only decides how many of them fit in a context column a
     * person reads. **A policy figure would be the owner's** (2409); a cap on how
     * much evidence goes into one JSON blob is not one.
     *
     * ⚠️ **HEAVIEST FIRST, AND TIES KEEP THEIR ORDER.** `arsort()` has been
     * stable since PHP 8.0, so two tenants with identical traffic are named in
     * the order they were walked — which is business-id order, because
     * `WatchPlatformComplaintRate::everyTenant()` walks users by id. That is what
     * makes this assertable rather than flaky.
     *
     * @return list<int>
     */
    public function worstBlindTenants(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $blind = $this->blindTenants;

        arsort($blind);

        return array_slice(array_keys($blind), 0, $limit);
    }

    /**
     * Whether anything here is worth ringing about.
     *
     * ⚠️ **A METHOD RATHER THAN `=== []` AT THE CALL SITE**, so that
     * {@see OperatorAlertKind::TenantDeliveryReceiptsSilent}'s precondition has
     * one spelling. There is exactly one raiser today, and writing the rule down
     * before there are two is the only point at which it is cheap.
     */
    public function hasBlindTenants(): bool
    {
        return $this->blindTenants !== [];
    }
}
