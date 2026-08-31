<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\ExecuteTenantDeletions;
use App\Services\Billing\SubscriptionCancellation;
use App\Services\Tenant\TenantDeletion;

/**
 * What {@see TenantDeletion::execute()} did, and — when it did nothing — why.
 *
 * ## ⚠️ WHY THIS REPLACED A BOOLEAN (1992)
 *
 * `execute()` returned `bool`, and for one refusal that was honest enough:
 * {@see ExecuteTenantDeletions} printed *"it still has a live subscription, and
 * no cancel path exists yet"* because that was the only way to get false. 1902
 * then added a **second** refusal — `purgeAllFor()` answering false when the
 * object store is unreachable — and the one place that reports a refusal to a
 * human was not updated, so an unreachable bucket read as a billing problem.
 *
 * The failure that produces is quiet and total: R2 credentials rotate and are
 * not updated, every night's sweep defers every due account with a sentence
 * about Stripe, an operator checks Stripe and finds nothing wrong, and
 * **statutory deletions stop platform-wide** for as long as it takes somebody
 * to disbelieve the message. A boolean cannot carry the difference, and a
 * boolean plus a log line at the refusal site would put the true reason
 * somewhere the operator running the command is not looking.
 *
 * ⚠️ **NEVER STORED.** This is a return value, not a column — CLAUDE.md's rule
 * against database `enum` columns is not in play, and neither is any migration.
 * `tenant_deletion_requests` remains the record of what happened.
 */
enum TenantDeletionOutcome: string
{
    /** The account is gone. */
    case Destroyed = 'destroyed';

    /**
     * Nothing to do — already executed, already cancelled, unconfirmed, or the
     * cooling window has not run out. The sweep's ordinary no-op.
     */
    case NotDue = 'not_due';

    /**
     * The business row had already gone by some other path, so the request was
     * closed rather than swept forever. Not a refusal and not a destruction.
     */
    case AlreadyGone = 'already_gone';

    /**
     * A gateway would still bill this card.
     *
     * ⛔ **THIS CASE SAID "STRIPE" AND THE GUARD BEHIND IT ONLY ASKED STRIPE,
     * IN AN APPLICATION WHOSE PRIMARY GATEWAY IS AUTHORIZE.NET — CORRECTED
     * 2026-08-23 (8841).** {@see TenantDeletion::gatewayWouldStillBill()} now
     * asks both. The consequence of the narrow question was not a wrong
     * refusal: it was **no refusal at all**, followed by a foreign-key
     * violation out of the erasure's own transaction, on the gateway most
     * paying tenants are on.
     *
     * ⛔ **AND "CLEARS WHEN A CANCEL PATH LANDS (ROW 22 SLICE C)" WAS TRUE AND
     * IS NOT — CORRECTED 2026-08-23 (8845).** The cancel path landed at
     * 2980–2999: {@see SubscriptionCancellation} calls
     * `cancelSubscriptionAtPeriodEnd` on Stripe and
     * `ARBCancelSubscriptionRequest` on Authorize.Net, and it is reachable from
     * the owner's own billing page and through impersonation. So this refusal
     * clears by somebody using a control that exists, which is a different
     * instruction to an operator than *"wait for a slice"* — see
     * {@see self::deferralMessage()}, where the stale half was the sentence a
     * human actually read.
     */
    case BillingActive = 'billing_active';

    /**
     * The tenant's data could not be removed from object storage, so Postgres
     * was left alone. Clears when the bucket is reachable again. ⚠️ **This is
     * the one an operator will misread as the one above** if nothing
     * distinguishes them.
     *
     * ⚠️ **ONE CASE, EVERY OBJECT-STORE CALL, DELIBERATELY** — {@see
     * ExportBuilder::purgeAllFor()}, {@see
     * \App\Contracts\L0Archive::purgeFor()} since 5080, and since 2026-08-23
     * the four kinds an erasure used to leave standing (9003). All of them
     * guard the same shape of harm (an object surviving the Postgres row that
     * named it, with nothing left to point at it) and all are refused before
     * the same transaction, so a further case distinguishing "which call
     * failed" would tell an operator nothing they can act on differently — the
     * remedy is the same sentence below either way: check the bucket
     * credentials. ⚠️ **THIS SAID "TWO OBJECT-STORE CALLS" AND THE COUNT IS
     * DROPPED RATHER THAN RAISED TO SIX**, on the rule the two paragraphs below
     * this one already state twice: a counter in prose beside a `match` that
     * enforces exhaustiveness buys nothing and goes stale in silence (2505).
     *
     * ⚠️ **THIS SENTENCE READ "A SEVENTH CASE" AND WAS COMPOSED WITH THE LANE
     * THAT ADDED ONE** (5131). W26 wrote it against six cases; W25 added
     * {@see self::DestroyedGrantNotSent} on another branch and generalised its
     * own two counters to "a further case" for exactly this reason. In the
     * composed tree *"a seventh case"* named a case that exists and is about
     * something else entirely, so the hypothetical this paragraph refuses read
     * as a description of the one below it. **Neither lane could see it**, and
     * a counter in prose is the shape that keeps being wrong here (2505) —
     * which is why the argument is now stated without a number.
     */
    case ObjectStoreRefused = 'object_store_refused';

    /**
     * The **database** refused to destroy the account, so nothing was destroyed.
     *
     * ⛔ **THE CASE THIS ENUM'S OWN DESIGN SAID COULD NOT BE NEEDED, AND THE
     * ONE THE CLASS HAS BEEN HITTING SINCE THE DAY IT SHIPPED** (8840, 8842).
     * 1992 wrote that *"a third refusal cannot be added without a `match`
     * refusing to compile"*, and that held for every refusal anybody chose to
     * write. What it could not reach is a refusal **Postgres** issues:
     * `businesses` carries `restrictOnDelete` foreign keys, and a tenant
     * holding one of those rows did not refuse and did not fail politely — it
     * raised a `QueryException` out of `DB::transaction()`, past a docblock
     * saying the method *"REFUSES RATHER THAN FAILS"*, into a nightly sweep
     * with no `catch`.
     *
     * ⚠️ **IT IS NOT A SYNONYM FOR THE THREE `restrict` KEYS AND MUST NOT BE
     * READ AS ONE.** Those three are released now, and if this case only ever
     * meant *"one of them"* it would be dead the day it landed. Its subject is
     * the class: **a constraint in this schema stopped a statutory erasure.**
     * The schema gains keys and CHECKs every wave — 5149 removed a hand-counted
     * figure from {@see TenantDeletion} for exactly that reason — and this has
     * already happened twice for two different reasons: 2686's
     * `phone_numbers_shared_pool_has_no_business` CHECK, then these keys. A
     * third is a matter of time, and the property worth owning is that it costs
     * one deferred account rather than every account queued behind it.
     *
     * ⚠️ **THE REQUEST STAYS OPEN AND IS RETRIED**, exactly like
     * {@see self::BillingActive}: nothing is failed, nothing is closed, and 823's
     * rule holds — a blocked automation that throws burns a retry ladder against
     * a condition only a human clears.
     *
     * ⛔ **AND IT IS THE ONE DEFERRAL WHOSE STEADY STATE IS "SOMETHING IS
     * WRONG".** Every other refusal here has an ordinary population — tenants
     * with live subscriptions, a bucket that is briefly unreachable. This one
     * means the erasure path met a shape nobody predicted, so
     * {@see ExecuteTenantDeletions} rings a bell for it
     * rather than printing a line into output the scheduler discards.
     */
    case DatabaseRefused = 'database_refused';

    /**
     * The account is gone **and** something at a third party is not.
     *
     * ⛔ **ONE OF THE TWO CASES HERE THAT ARE NOT REFUSALS** (4731, 4882 — the
     * other is {@see self::DestroyedGrantNotSent}, 5100).
     *
     * ⚠️ **THIS OPENED "THE SIXTH CASE" AND STOPPED BEING TRUE WHEN
     * {@see self::DatabaseRefused} LANDED — CORRECTED 2026-08-23 (8846).** The
     * ordinal is dropped rather than incremented, which is the same call 5131
     * made about *"a seventh case"* ten lines above and for the same reason: a
     * counter in prose beside a `match` that already enforces exhaustiveness
     * buys nothing and goes stale in silence (2505). **The property is that
     * there are two, and `destroyed()` is where that is enforced.**
     * Zernio holds `business.manage` — read *and* write — on the former
     * customer's Google Business Profile, and the vendor call that ends it did
     * not go through. The erasure still happened, deliberately: a statutory
     * deletion held hostage to a subprocessor's uptime is a second harm on top of
     * the first, and refusing would not revoke anything either. What replaces the
     * refusal is a durable obligation — `gbp_account_bindings.revocation_owed_at`
     * — retried by `gbp:revoke-owed-grants` until it succeeds.
     *
     * ⚠️ **{@see self::destroyed()} IS TRUE FOR THIS CASE.** The account really
     * is destroyed, the §9.5 queue item really is closed, and reporting otherwise
     * would leave an erasure looking unfinished on a screen forever. The
     * unfinished part is at the vendor, and {@see self::outstandingMessage()} is
     * what says so.
     */
    case DestroyedGrantOutstanding = 'destroyed_grant_outstanding';

    /**
     * The account is gone, and a grant recorded as owed for it was **left
     * alone** because the business that grant names still exists.
     *
     * ⛔ **IT EXISTS SO THAT A SKIP CANNOT BORROW
     * {@see self::DestroyedGrantOutstanding}'s SENTENCE** (5100). ⚠️ **This
     * opened "the seventh case … the sixth's sentence" and both ordinals were
     * falsified by {@see self::DatabaseRefused} — corrected 2026-08-23 (8846),
     * by naming the sibling instead of counting to it.** `GbpConnections::revokeOwedGrants()` counts a
     * skip separately from a failure, and {@see TenantDeletion} added the two
     * together — so a skip printed {@see self::outstandingMessage()}'s
     * *"the revocation could not be delivered … a third party has read and
     * write access to a former customer's listing"*, in which **every clause is
     * false**: nothing was sent to Zernio at all, and the business named is a
     * current customer. `RevokeOwedGbpGrants` had already been given two
     * distinct sentences for exactly this (5074) and this path had not.
     *
     * ⚠️ **UNREACHABLE BY CONSTRUCTION TODAY, AND THAT IS NOT A REASON TO LEAVE
     * IT SHARING AN ARM.** The business this outcome names was destroyed one
     * line earlier, so the survival probe answers false and the skip count is
     * zero — the read is defensive, kept for the day the construction stops
     * holding, and on that day the only thing that matters is which sentence it
     * prints to the operator running the sweep.
     *
     * ⚠️ **{@see self::destroyed()} IS TRUE HERE TOO.** The erasure happened;
     * what did not happen is a vendor call nobody should now make by hand.
     */
    case DestroyedGrantNotSent = 'destroyed_grant_not_sent';

    public function destroyed(): bool
    {
        // Exhaustive rather than `$this === self::Destroyed`, so that a further
        // case cannot be added without deciding this question — which is exactly
        // the compile-time refusal this enum was created for (1992).
        return match ($this) {
            self::Destroyed, self::DestroyedGrantOutstanding, self::DestroyedGrantNotSent => true,
            self::NotDue, self::AlreadyGone, self::BillingActive,
            self::ObjectStoreRefused, self::DatabaseRefused => false,
        };
    }

    /**
     * What is still owed after an account that really was destroyed, or null.
     *
     * Separate from {@see self::deferralMessage()} because it answers a different
     * question: that one explains why nothing happened, and this one explains
     * what happened anyway. Collapsing them would put a warning about a third
     * party's access under a heading that says an account was deferred.
     *
     * ⛔ **THE TWO NON-NULL ARMS SAY OPPOSITE THINGS AND MUST NEVER BE MERGED**
     * (5100). One reports a call that was made and failed; the other reports a
     * call that was deliberately **not** made. They differ in the only thing the
     * operator reading them can act on — whether a third party still holds write
     * access to a listing, and therefore whether revoking by hand is the fix or
     * is itself the harm.
     */
    public function outstandingMessage(): ?string
    {
        return match ($this) {
            self::DestroyedGrantOutstanding => 'the account was destroyed, but Zernio still holds '
                .'the Google Business Profile grant — the revocation could not be delivered and is '
                .'recorded as owed. `gbp:revoke-owed-grants` retries it nightly; until it succeeds a '
                .'third party has read and write access to a former customer\'s listing, and we are '
                .'still billed for the account.',
            self::DestroyedGrantNotSent => 'the account was destroyed, and a Google Business grant '
                .'recorded as owed for it was left alone: the business that grant names still '
                .'exists, so nothing was sent to Zernio and nothing at the vendor changed. Do not '
                .'revoke it by hand — that would disconnect a live customer\'s Google listing. It '
                .'is on the Ops grant-revocation screen, marked.',
            self::Destroyed, self::NotDue, self::AlreadyGone, self::BillingActive,
            self::ObjectStoreRefused, self::DatabaseRefused => null,
        };
    }

    /**
     * The sentence `tenants:execute-deletions` prints for a deferral.
     *
     * On this enum rather than in the command, so that a further case cannot be
     * added without a `match` arm somewhere refusing to compile — which is the
     * whole defect this type exists to prevent, recurring.
     */
    public function deferralMessage(): string
    {
        return match ($this) {
            self::Destroyed, self::AlreadyGone,
            self::DestroyedGrantOutstanding, self::DestroyedGrantNotSent => 'Nothing was deferred.',
            self::NotDue => 'it is not due yet — the cooling window is still running, '
                .'or the request was cancelled or already executed.',
            // ⛔ THIS SENTENCE ENDED "AND NO CANCEL PATH EXISTS YET" UNTIL
            // 2026-08-23 (8845), WHICH IS 1992's DEFECT IN 1992's OWN FIX. The
            // whole reason this method lives on the enum is that the one
            // sentence a human reads had gone stale; it then went stale again,
            // in the opposite direction — telling an operator there is nothing
            // they can do, on a platform where `SubscriptionCancellation` has
            // shipped for both gateways since 2980–2999 and is reachable from
            // the account's own billing page.
            self::BillingActive => 'a gateway would still bill this card. Cancel the subscription '
                .'first — the account\'s own billing page does it on both gateways — and the next '
                .'sweep will erase the account.',
            // ⛔ THE PARENTHESIS HERE READ "(exports or the pixel archive)"
            // AND WAS AN INVENTORY OF THE PURGE SET — REMOVED 2026-08-23
            // (9015). Four more kinds now refuse through this same case, so the
            // two it named had become the two it happened to list, which is
            // 8861's ruling about the stored erasure note arriving in the
            // sentence an operator reads at 3am. The remedy is the same
            // whichever purge refused, and the log line beside it carries the
            // account reference; naming a subset told nobody anything and went
            // stale the first time the set moved.
            self::ObjectStoreRefused => 'its data could not be removed from object storage, '
                .'so nothing in Postgres was touched either. '
                .'Check the bucket credentials — this is not a billing problem.',
            // ⚠️ IT NAMES NO TABLE AND NO CONSTRAINT, DELIBERATELY. A
            // `QueryException` message interpolates its bindings, and the
            // statements inside this transaction carry a release reason and a
            // phone number among them — so the identifying detail is logged as
            // a SQLSTATE beside the reference, and never rendered here.
            self::DatabaseRefused => 'the database refused the delete, so nothing was destroyed '
                .'and the request is still open. This is neither a billing nor a bucket problem: '
                .'a constraint in our own schema stopped it. The SQLSTATE is in the log beside '
                .'this account reference.',
        };
    }
}
