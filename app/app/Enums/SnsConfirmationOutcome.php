<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\ConfirmSnsSubscription;
use App\Http\Controllers\Mail\SesFeedbackController;
use App\Services\Mail\SnsSubscriptions;

/**
 * What happened when an operator asked this platform to complete a pending SNS
 * subscription (10220–10229).
 *
 * ⚠️ **AN OUTCOME TYPE RATHER THAN A `bool`, FOR {@see WebhookVerification}'s
 * REASON.** Five of these mean five different next actions at a console — three
 * of them are "the thing you asked for is not there", and collapsing those into
 * one `false` is how an operator retries the command instead of going back to
 * AWS. ⛔ **And {@see self::Unreachable} is the arm that must not be folded into
 * {@see self::Refused}**: nothing was judged, the token is untouched, and the
 * right next step is to run the same command again — which is the opposite of
 * every other failing arm.
 *
 * ⚠️ **NOT PERSISTED AND DELIBERATELY NOT BACKED.** Nothing stores this, so
 * there is no string for a database column to drift from — `CLAUDE.md`'s rule
 * about a second source of truth cuts the other way when there is no first one.
 *
 * @see SnsSubscriptions::confirm()
 * @see ConfirmSnsSubscription
 * @see SesFeedbackController
 */
enum SnsConfirmationOutcome
{
    /**
     * AWS accepted the confirmation. The subscription is live and this endpoint
     * will now receive bounce, complaint and delivery events for the topic.
     */
    case Confirmed;

    /**
     * Nothing is waiting for this topic.
     *
     * ⚠️ **THE COMMONEST CAUSE IS TIME RATHER THAN A MISTAKE.** The capability
     * is held for {@see SnsSubscriptions::PENDING_TTL_SECONDS} and then dropped,
     * so an operator who comes back the next morning lands here. The remedy is
     * at AWS — *Request confirmation* on the subscription — and not in this
     * application, which cannot ask SNS to send another one.
     */
    case NothingPending;

    /**
     * The topic named is not one this deployment accepts.
     *
     * ⚠️ **THE SAME ALLOWLIST THE ENDPOINT ITSELF IS GATED ON**, read a second
     * time at the moment of the fetch rather than trusted from the moment of
     * the record — because between those two moments sits a store, and the
     * store is a trust boundary in both directions.
     */
    case TopicNotAllowed;

    /**
     * AWS answered, and the answer was no.
     *
     * A token is valid for a bounded time at AWS and is spent by a successful
     * confirmation, so this is what a stale one looks like. The held capability
     * is dropped: it will not start working again.
     */
    case Refused;

    /**
     * We could not reach AWS at all, so nothing was judged and nothing was
     * spent.
     *
     * ⛔ **THE HELD CAPABILITY SURVIVES THIS ONE ARM AND ONLY THIS ONE.** The
     * token was never presented, so it is still good; dropping it here would
     * turn a network blip into a trip back to the AWS console for no reason.
     */
    case Unreachable;
}
