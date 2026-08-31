<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a gateway said when we asked it what happened to one charge.
 *
 * ⚠️ **NOT A PERSISTED COLUMN AND NOT A VERDICT.** This is the *vendor's* answer,
 * normalised across two gateways whose vocabularies share no words;
 * {@see PurchaseReconciliationVerdict} is what this application then *did* about
 * it, and the two differ on the case that matters — a gateway saying `Paid` still
 * produces `AlreadySettled` when a late notification got there first.
 *
 * ⛔ **THERE IS NO PERMISSIVE DEFAULT ANYWHERE THAT PRODUCES ONE OF THESE.** Every
 * status neither vendor documents, every unreadable amount and every shape change
 * lands on {@see self::Ambiguous}, which credits nobody and leaves the purchase
 * exactly as it was. *"When the gateway's answer is ambiguous, do nothing and
 * surface it"* — leaving a human to look is the correct outcome and is what
 * existed before this sweep.
 */
enum GatewayPaymentState: string
{
    /**
     * The money moved and is ours.
     *
     * ⛔ **THE ONLY STATE THAT CAN CREDIT ANYBODY**, and the bar for reaching it
     * is deliberately narrow on both vendors:
     *
     *   Authorize.Net  `transactionStatus` is `capturedPendingSettlement` or
     *                  `settledSuccessfully` **and** `responseCode` is `1`. Every
     *                  other member of `transactionStatusEnum` — including
     *                  `authorizedPendingCapture`, `underReview`, `FDSPendingReview`
     *                  and `settlementError` — is not this.
     *   Stripe         the PaymentIntent is `succeeded`, its `amount_received` is
     *                  positive, and its expanded latest charge says nothing has
     *                  been refunded.
     */
    case Paid = 'paid';

    /**
     * The vendor says definitively that no money is ours from this transaction.
     *
     * Declined, voided, expired, cancelled. ⚠️ **TERMINAL FOR THE SWEEP AND NOT
     * FOR THE PURCHASE**: nothing about the row changes, so the ordinary
     * settlement path is still armed if the vendor ever does send a notification.
     */
    case NotPaid = 'not_paid';

    /**
     * The money moved and went back.
     *
     * ⛔ **RECORDED, NEVER CREDITED, AND NEVER REFUNDED AGAIN BY US.** This slice
     * reads from the gateways; taking or returning money is out of its scope
     * entirely, and a purchase whose payment has been reversed owes the tenant
     * nothing.
     */
    case Refunded = 'refunded';

    /**
     * The answer does not decide the question, so nothing is decided.
     *
     * A held-for-review transaction that may yet capture, a settlement error, a
     * status neither vendor documents, an amount that will not parse, two
     * PaymentIntents carrying one reference, a charge that came back unexpanded.
     * **The sweep tries again until its attempt bound, then gives up loudly.**
     */
    case Ambiguous = 'ambiguous';

    /** The vendor could not be asked at all. Retried, never interpreted. */
    case Unreachable = 'unreachable';
}
