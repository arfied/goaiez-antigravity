<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of one attempt to send one message.
 *
 * ⚠️ **NOT `OutreachStatus`, AND THE TWO MUST NOT BE MERGED.** That enum is the
 * lifecycle of a *row* — queued, sent, delivered, failed, opted out, replied —
 * and it moves over hours as delivery receipts arrive. This one is the answer to
 * a single synchronous call and never changes afterwards. Collapsing them would
 * give `outreach_messages` a `duplicate` state, which is not a thing a message
 * can be: a duplicate is an attempt that produced no message at all.
 *
 * Three cases and deliberately no `Failed`. A transport that could not be
 * reached throws, because 700's failure was a send that did not happen being
 * indistinguishable from one that did, and an enum case is exactly how that
 * becomes indistinguishable again.
 */
enum SendOutcomeStatus: string
{
    /** The carrier or mailer took it and named it. */
    case Accepted = 'accepted';

    /** A rule said no. The reason travels with it. */
    case Refused = 'refused';

    /**
     * This send key had already been used. Nothing new was sent, no credit was
     * debited, and no row should be written.
     */
    case Duplicate = 'duplicate';
}
