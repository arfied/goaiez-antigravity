<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to a payment-gateway event this application accepted.
 *
 * ⚠️ **IT WAS `StripeEventOutcome` UNTIL AUTHORIZE.NET ARRIVED (decision 2130).**
 * Decision 2056 makes the second gateway *additive* — two webhook vocabularies,
 * one abstraction — and this enum is the one piece of that vocabulary that is
 * genuinely identical on both sides: applied, ignored, unlinked, unmodelled,
 * superseded describe what a handler did, not what a vendor sent. Copying it as
 * `AuthorizeNetEventOutcome` would have been the smaller diff and the wrong
 * shape: two enums with the same five cases drift the moment one gains a sixth,
 * and the string values are already persisted in `stripe_events.outcome`, so a
 * rename costs nothing at the database. **The values are unchanged; only the
 * type's name moved.**
 *
 * Recorded on the event table rather than inferred from the row's existence,
 * because the three interesting answers are all "we have a row for it" and they
 * mean very different things. Without this column, "the gateway says it
 * delivered this event and nothing changed" has one answer — silence — for a
 * type we deliberately ignore, an event that named a customer we do not know,
 * and an event we applied to a row that was already in that state.
 *
 * ⚠️ **THERE IS NO `Failed` CASE, AND ITS ABSENCE IS THE DESIGN.** A handler
 * that throws must not leave a row here, because the row is what makes the
 * redelivery a no-op — recording a failure would consume the event id and turn
 * the vendor's retries into one lost event. Failures are exceptions, a non-2xx
 * response, and the vendor trying again. The rows in these tables are outcomes
 * that are *finished*.
 *
 * ⚠️ **THE TWO VENDORS RETRY VERY DIFFERENTLY AND THE CASES DO NOT CARE.**
 * Stripe retries for three days. Authorize.Net's webhook delivery retries are
 * not documented as a published schedule at all, which is a reason to be
 * *stricter* about answering 2xx only for outcomes that are finished — not a
 * reason to add a case.
 */
enum GatewayEventOutcome: string
{
    /** Projected onto the tenant's subscription row. */
    case Applied = 'applied';

    /**
     * A type we do not subscribe to and would not act on.
     *
     * Recorded rather than dropped: an endpoint that answers 200 to everything
     * and stores only what it understood cannot answer "did you get it" for the
     * event somebody is asking about, which is the question a billing
     * investigation opens with.
     */
    case Ignored = 'ignored';

    /**
     * Verified, and it named a Stripe customer no business here is linked to.
     *
     * ⚠️ **THIS IS ORDINARY, NOT AN ERROR.** One Stripe account serves whatever
     * else it serves; test-mode traffic, a customer created by hand in the
     * dashboard, and a subscription belonging to a different application all
     * produce this. It stays a distinct outcome from {@see self::Ignored}
     * because a *sustained* run of these means the customer index has lost rows
     * — a real defect — while a scattering of them means nothing at all.
     */
    case Unlinked = 'unlinked';

    /**
     * Verified, understood as far as its shape, and carrying a status this
     * product has no meaning for.
     *
     * ⚠️ **THE REFUSAL {@see SubscriptionStatus} PROMISES.** That
     * enum's docblock says a webhook handler "must refuse a status it does not
     * recognise rather than coerce it to the nearest one: a subscription in an
     * unmodelled state is a thing a person needs to look at, not a thing to
     * round off". `unpaid`, `paused` and `incomplete_expired` all exist upstream
     * and none of them is modelled, because `CLAUDE.md` says plainly that nobody
     * has decided what a delinquent tenant loses or when.
     *
     * ⚠️ **AND IT ANSWERS 2xx RATHER THAN RETRYING.** Redelivering will not
     * make the status one we model, so a non-2xx here buys three days of noise
     * and no fix. The row, and a logged warning, are the record.
     */
    case Unmodelled = 'unmodelled';

    /**
     * Understood, and the state it describes is older than what we hold.
     *
     * Stripe does not guarantee ordering, so a `customer.subscription.updated`
     * from before a `deleted` can arrive after it. Reconciling against the
     * object in the event — rather than assuming a transition — is what the
     * vendor's own guidance asks for, and this is what that refusal is called
     * when it fires.
     */
    case Superseded = 'superseded';
}
