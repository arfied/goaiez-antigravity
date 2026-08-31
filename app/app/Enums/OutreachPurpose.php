<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a message is being sent — the axis every scrubbing rule turns on.
 *
 * ⚠️ THIS IS NOT A LABEL, IT SELECTS WHICH LAW APPLIES. `24` §3.4 makes the
 * distinction explicit for every control it lists: DNC scrubbing applies to
 * "marketing messages (existing-customer relationship exempts most
 * transactional)", while litigator suppression applies to "all". A gate that
 * cannot tell the two apart either blocks every review request — which are
 * transactional by `24` §3.3 and are the only messages this product sends — or
 * lets marketing through the federal Do Not Call registry. Neither is
 * survivable, which is why the parameter exists rather than a constant.
 *
 * ⚠️ TWO CASES, AND DELIBERATELY NOT A COPY OF `outreach_messages.purpose`.
 * That column's vocabulary is `review_request|triage|reminder|owner_alert|fill`
 * (DATA-MODEL §5.7) and it describes what a message *is about*. This describes
 * what a message *is*, legally, and the mapping between the two is many-to-one.
 * Collapsing them would mean a new campaign type silently choosing its own
 * compliance treatment by picking a string.
 *
 * ⚠️ MARKETING IS THE DEFAULT EVERYWHERE THIS APPEARS. It is the more restricted
 * of the two, so a caller who does not think about it gets the safe answer and a
 * caller who wants the permissive one has to say so in writing. `OptOutScope`
 * defaults to `Platform` for the identical reason, and `ConsentCapture` records
 * the same asymmetry: refusing to send is safe and routine, sending is the
 * irreversible one.
 */
enum OutreachPurpose: string
{
    /**
     * Anything promoting a product, service or offer.
     *
     * Subject to the Do Not Call registries, state mini-TCPA windows, and the
     * prior-express-written-consent requirement of `29` §2 rule 7.
     */
    case Marketing = 'marketing';

    /**
     * Messages arising from a transaction or relationship the person already has
     * with the business.
     *
     * ⚠️ REVIEW REQUESTS LIVE HERE, and `24` §3.3 is the reason they are allowed
     * to: "Review requests stay strictly transactional: no offers, no
     * incentives, no promotional language." That sentence is a condition, not a
     * classification — a review invite that carries a discount code has made
     * itself marketing, and nothing in this enum can notice. The composer that
     * writes the body owns that boundary.
     */
    case Transactional = 'transactional';

    /**
     * Whether the Do Not Call registries and the state mini-TCPA rules apply.
     *
     * A match with no default, so a third purpose is a compile-time conversation
     * rather than one that quietly inherits the permissive answer.
     */
    public function isSubjectToDoNotCall(): bool
    {
        return match ($this) {
            self::Marketing => true,
            self::Transactional => false,
        };
    }
}
