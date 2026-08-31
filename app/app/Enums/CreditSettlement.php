<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened when a payment notification met a purchase row.
 *
 * ⚠️ **NOT A PERSISTED COLUMN.** `credit_purchases.status` records where a
 * purchase *is*; this records what one settlement attempt *did*, which is the
 * thing a webhook handler turns into a {@see GatewayEventOutcome} and a test
 * asserts on. The two differ on exactly the case that matters:
 * {@see self::AlreadySettled} leaves the status at `credited` and is the correct,
 * expected answer to a redelivery — a state and an event with the same name would
 * make that indistinguishable from a fresh credit.
 */
enum CreditSettlement: string
{
    /** The purchase was claimed and the top-up pool moved. Exactly once. */
    case Credited = 'credited';

    /**
     * The purchase was already settled, so nothing moved.
     *
     * ⚠️ **THIS IS A SUCCESS AND MUST BE ANSWERED 2xx.** Both gateways redeliver
     * — Stripe for three days by documented design — and a handler that treated a
     * replay as a failure would ask for another one.
     */
    case AlreadySettled = 'already_settled';

    /**
     * No purchase of ours matches this notification.
     *
     * Ordinary rather than an error: one merchant account serves whatever else it
     * serves, and a subscription charge is not a top-up. A *sustained* run of
     * these means the index has lost rows, which is a real defect; a scattering
     * means nothing — `GatewayEventOutcome::Unlinked`'s reading exactly.
     */
    case Unknown = 'unknown';

    /**
     * The money moved and the amount does not match the SKU, so nothing was
     * credited and a person has to look.
     *
     * ⛔ **NEVER CREDIT ON THIS PATH.** Crediting whatever the notification
     * carries, at whatever price it claims, is the free-credit hole this whole
     * comparison exists to close.
     */
    case Mismatched = 'mismatched';
}
