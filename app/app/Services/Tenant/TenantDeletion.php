<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\L0Archive;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantDeletionOutcome;
use App\Enums\TenantDeletionReason;
use App\Models\Business;
use App\Models\CreditPurchase;
use App\Models\TenantDeletionRequest;
use App\Models\User;
use App\Services\Billing\PurchaseReconciliation;
use App\Services\Billing\SubscriptionCancellation;
use App\Services\Billing\Subscriptions;
use App\Services\Campaigns\CampaignMedia;
use App\Services\Export\ExportBuilder;
use App\Services\Gbp\GbpConnections;
use App\Services\Knowledge\KnowledgeUploads;
use App\Services\Sms\InboundMediaCapture;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\VoiceCalls;
use App\Services\Zernio\ZernioWhatsappMedia;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Delete — the one place this application destroys an account.
 *
 * `28` §9.5: *"Delete (right-to-erasure or hard offboard): two-person rule
 * (`super_admin` + one more admin confirm), 7-day cooling window with export
 * offered, then the existing privacy-ops deletion path across Postgres + R2.
 * Erasure is crypto-shred — destroy the per-identity DEK; aggregates survive.
 * Fully logged."*
 *
 * The two-person rule, the cooling window, the Postgres deletion and the record
 * ship. Four clauses do not, and saying which is the point of this docblock.
 *
 * ## ⚠️ FOUR THINGS §9.5 ASKS FOR THAT THIS DOES NOT DO
 *
 * **1. It is not crypto-shred, and the word appears nowhere in this class.**
 * There is no per-identity DEK in this application. The only encryption is
 * Laravel's `Crypt` on `platform_credentials` and `oauth_connections`, keyed on
 * one platform `APP_KEY` — destroying it erases every tenant simultaneously, so
 * it is not a shred, it is an outage. What ships is *delete what cascades, and
 * leave standing what must remain*. That is genuinely weaker: the surviving rows
 * still exist, and a reconstruction from backups is not defeated by it, which
 * destroying a key would be. Stated rather than approximated, on decision 531's
 * precedent and against 314–316's most-repeated defect.
 *
 * **2. ~~No export is offered during the window.~~ ✅ CLOSED.** §9.5 says the
 * cooling window comes "with export offered", and §3.7's export engine now
 * exists: {@see ExportBuilder}, reachable from the owner's
 * own account screen and — since 1900 — from the suspended-account page, which
 * is the only screen a stopped tenant can see. Nothing about a pending deletion
 * gates it, because nothing about a pending deletion changes how the account
 * behaves (see the section below).
 *
 * **3. The owner's `users` row is not touched, and this is the gap that most
 * looks like an oversight.** The account's data goes; the person's login
 * survives with their name and email intact. It is not laziness — it is
 * decision 746's wall. Deciding whether redacting them is safe means answering
 * *"do they still own another account"*, and that question cannot be answered
 * from inside the deleted tenant's context: `businesses` is `FORCE`d on
 * `app.business_id`, so the sibling account is invisible and the honest-looking
 * query returns "owns nothing" for a live customer. The only route is the
 * `owner_lookup` policy through `Tenancy::actingAsUser()`, which decision 803
 * holds to one caller by a lint. ⚠️ **Redacting a customer who still has a
 * paid account is the worst thing this class could do**, and it is what the
 * plausible implementation does — the first draft of this method did exactly
 * that and a two-business test caught it. So v1 does nothing here rather than
 * something confident and wrong.
 *
 * **4. ~~Nothing is deleted from R2.~~ ✅ CLOSED, AND NOT BY THE SLICE THIS
 * PARAGRAPH EXPECTED** (1902). It used to read: *"the bucket's content is the
 * pixel's L0 archive, and the pixel is unbuilt — there is nothing there
 * belonging to any tenant. ⚠️ Whoever builds the pixel owns this line: the
 * moment L0 carries a tenant's events, an offboard that touches only Postgres
 * stops being complete, silently, because every test here would still pass."*
 *
 * That moment arrived from a different direction. The pixel is still unbuilt;
 * `28` §3.7's **export engine** put a complete, unencrypted ZIP of every
 * contact's name, email, phone and consent state in the same bucket, and
 * `tenant_exports.business_id` cascades — so `$business->delete()` removed only
 * *the row that names the path*, leaving an unreferenced object nobody could
 * find to delete. The prediction was right about the mechanism and wrong about
 * which feature would trigger it, which is the useful half.
 *
 * {@see self::execute()} now calls
 * {@see ExportBuilder::purgeAllFor()} **before** the
 * transaction, and **refuses the whole deletion if it fails** — the row is the
 * only thing that knows where the object is, so destroying it after a failed
 * R2 call is the exact defect, made permanent.
 *
 * ⚠️ **"THE ORIGINAL WARNING STILL STANDS FOR L0" STOPPED BEING TRUE AT
 * DECISIONS 4960–4979 AND IS CORRECTED HERE — 2026-08-18 (5080).** It read
 * *"Nothing here deletes pixel events, because there still are none; whoever
 * builds the pixel still owns that line."* The pixel collector now exists —
 * `POST /api/pixel/e` → `PixelCollector` → `ArchivePixelBatchJob` →
 * `L0Archive::store()` is a real, reachable chain (though §10's bundle
 * delivery is not, so no genuine visitor traffic has ever used it — 4966).
 * {@see self::execute()} now also calls {@see \App\Contracts\
 * L0Archive::purgeFor()}, on the same terms as the export purge above: before
 * the transaction, refusing the whole deletion if the archive may still hold
 * an object. ⚠️ **A `Phi`-classified business never has anything to purge
 * here** — `ObjectStoreL0Archive::store()` refuses a `Phi` batch outright
 * (decision 4863), so this call always finds nothing for one and the erasure
 * proceeds; it exists for the `Pii` tenant the pixel's own gate does admit.
 *
 * ⛔ **AND "TWO PURGES" WAS THE WHOLE OF IT UNTIL 2026-08-23, WHICH LEFT FOUR
 * KINDS OF OBJECT STANDING — TWO OF THEM SOMEBODY ELSE'S PERSONAL DATA**
 * (8866, 8871, 8876). Inbound MMS photographs, voicemail audio, the owner's
 * knowledge uploads and every rendered campaign picture survived an erasure
 * with real bytes on a disk, and `ErasureLeavesObjectsTest` asserted exactly
 * that as a hazard rather than a fix. ⚠️ **THAT NAME IS HISTORICAL AND RESOLVES
 * TO NOTHING TODAY — the file is now
 * `tests/Feature/Support/ErasurePurgesObjectsTest.php`, which says so in its own
 * header (corrected 2026-08-25, 9662).** A past-tense citation is still a name
 * the next reader greps for. ⛔ **The sharp half is that erasing made
 * them *more* permanent**: `StorageRetention` is row-driven and tenant-scoped
 * and the cascade destroys both the row that names the path and the business
 * that puts a tenant in context, so a **live** tenant's photograph was pruned
 * at the operator's period and an **erased** tenant's was kept for ever.
 * {@see self::execute()} now purges all four beside the two above, on the same
 * terms — before the transaction, refusing the whole deletion if one may still
 * be there. ⚠️ **This docblock deliberately states the property and not a
 * running total**: what an erasure purges is a fact about the code that ran,
 * which is 8861's ruling about the note applied to the class it describes.
 *
 * ## ⚠️ A PENDING DELETION IS NOT A FOURTH STOP-STATE
 *
 * The tempting design gives a pending deletion its own suppression, joining
 * {@see TenantPause} and {@see TenantSuspension}. It deliberately does not.
 * Decision 822 verified the pause's width *by mutating it wider* and found the
 * over-reach reddens; a third overlapping switch would need the same proof
 * against both existing ones, and 821's rule still governs — stopping us acting
 * is one decision, and destroying the account is another. An operator who wants
 * the account stopped during the window has Suspend, which exists, is attributed
 * and is liftable. So a confirmed deletion changes nothing about how the account
 * behaves until the moment it stops existing.
 *
 * ## ⚠️ THE BILLING DEPENDENCY, WHICH IS WHY MOST TENANTS CANNOT BE DELETED YET
 *
 * ⛔ **THIS SECTION NAMED ONE TABLE AND ONE GATEWAY, AND THERE ARE THREE AND
 * TWO — CORRECTED 2026-08-23 (8840–8843).** It read: *"`stripe_customers.
 * business_id` is `restrictOnDelete` … So {@see self::execute()} refuses while
 * Stripe would still bill — see `stripeWouldStillBill()` … and it clears the
 * day a cancel path lands."* Every clause of that was true when written and
 * three of them are not now, in the file a reader consults to find out what
 * this class does.
 *
 * **`businesses` carries exactly three `restrictOnDelete` keys**, and the
 * catalogue is the authority rather than this paragraph — `pg_constraint`,
 * `confdeltype = 'r'`, parent `businesses`. On 2026-08-23 they were
 * `stripe_customers`, `authorize_net_customers` (2056) and
 * `credit_purchase_references` (3419's top-up index). All three migrations
 * give the **same** reason in the same words: *"a business row disappearing
 * while a live … still points at it means an inbound event that resolves to
 * nothing, silently, on the vendor that takes money."*
 *
 * ⛔ **ONLY THE FIRST WAS RELEASED, AND THE OTHER TWO DID NOT REFUSE — THEY
 * RAISED.** `stripeWouldStillBill()` read `stripe_subscription_id` and nothing
 * else, so an Authorize.Net tenant — **the primary gateway** — passed the guard,
 * reached `$business->delete()` and took a `SQLSTATE[23503]` out of
 * `DB::transaction()`, past this class's own *"REFUSES RATHER THAN FAILS"*.
 * 32 tests covered this class and not one built the row.
 *
 * **What ships instead is all three readings, in the order that makes each
 * safe** (8843):
 *
 * 1. {@see self::gatewayWouldStillBill()} asks **both** gateways. Releasing the
 *    index without this would have been the money bug the guard exists to
 *    prevent, arriving through the door marked *"tidy up the delete"*: dropping
 *    `authorize_net_customers` for a tenant with a live ARB subscription
 *    destroys the only mapping a `subscription.failed` notification resolves
 *    through, **while Authorize.Net goes on charging their card for an account
 *    that no longer exists**.
 * 2. {@see self::releaseBillingRecords()} then releases all three, on
 *    `stripe_customers`' own argument — reached only once nothing is charging,
 *    what goes is a dead index. ⚠️ **And the index cannot preserve anything the
 *    erasure has not already destroyed**: `subscriptions` and `credit_purchases`
 *    both cascade, so a surviving pointer points at a row that is gone. These
 *    keys are tripwires, not archives, and the place to act on a tripwire is in
 *    front of the delete.
 * 3. {@see TenantDeletionOutcome::DatabaseRefused} catches what neither of those
 *    predicted, because the schema gains keys every wave and this has already
 *    happened twice (2686's CHECK, then these).
 *
 * ⛔ **"IT CLEARS THE DAY A CANCEL PATH LANDS" — THE CANCEL PATH LANDED**
 * (2980–2999, 8845). {@see SubscriptionCancellation}
 * calls `cancelSubscriptionAtPeriodEnd` on Stripe and
 * `ARBCancelSubscriptionRequest` on Authorize.Net, from the account's own
 * billing page. This class still **refuses** rather than cancelling on the
 * customer's behalf, and that is a ruling rather than an omission: what a
 * cancellation does to a paid term, and whether anything is refunded, is the
 * money policy 2980–2999 wrote down and nobody has answered. Erasure may not
 * answer it as a side effect.
 *
 * Import shipped refusing for a comparable reason (842–844); this is the same
 * shape.
 *
 * ## ⚠️ WHERE THE LOG IS, AND WHY IT IS NEITHER OF THE TWO OBVIOUS PLACES
 *
 * §9.5 says *"Fully logged"*, and both existing stores are wrong for it.
 *
 * **`audit_log` cannot hold it.** It is tenant-owned, `business_id` is
 * `NOT NULL`, and it cascades — so an entry recording that an account was
 * destroyed is destroyed by the thing it records, in the same statement. That is
 * decision 489's finding (`AuditService` opens with `Tenancy::idOrFail()`) with a
 * sharper edge: here the tenant does not merely fail to exist, it stops existing
 * *because of the act being logged*.
 *
 * **`staff_events` must not grow to hold it.** Decision 742 closed that table at
 * two cases behind a `CASE event … ELSE false` CHECK, warning that *"the store
 * that grows a third meaning becomes `audit_log` without the tenant boundary"*.
 * The database would refuse a third case outright, and widening the CHECK to
 * admit four deletion events is precisely the drift 742 exists to prevent.
 *
 * **So the request row is the record.** It names both people, carries every
 * timestamp of every transition, holds the typed reason, and survives the
 * account by design. It is strictly more complete than an `audit_log` entry
 * would have been, which is the same argument decisions 490 and 492 made for the
 * compliance registers being their own record, and 509 made for
 * `registry_changes`. ⚠️ **Nothing may delete a row from
 * `tenant_deletion_requests`** — there is no method here that does, and the row
 * is the only surviving evidence that an account ever existed.
 *
 * ## The tenant boundary
 *
 * Every write to the tenant's own data runs inside `Tenancy::actingAs()`, which
 * satisfies `tenant_isolation`'s USING and WITH CHECK without a fifth precedent
 * against decision 800's refusal — 1120's observation that *by-reference answers
 * writes as well as reads*. The request store itself is platform-scoped, because
 * it has to outlive the tenant it names.
 */
final class TenantDeletion
{
    /**
     * `28` §9.5's cooling window.
     *
     * Not in the Defaults Registry, on decision 1092's boundary: it is a
     * statutory-adjacent safety interval named in the specification, not a
     * threshold an operator tunes. If support ever asks a customer about it,
     * that is the signal it has become a setting.
     */
    public const COOLING_DAYS = 7;

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly ExportBuilder $exports,
        private readonly TenantNumbers $numbers,
        private readonly GbpConnections $gbp,
        private readonly L0Archive $l0,
        private readonly InboundMediaCapture $inboundMedia,
        private readonly VoiceCalls $voice,
        private readonly KnowledgeUploads $knowledge,
        private readonly CampaignMedia $campaignMedia,
        private readonly ZernioWhatsappMedia $whatsappMedia,
    ) {}

    /**
     * File a request. The first of the two people.
     *
     * ⚠️ **This does not start a clock.** `executes_at` stays null until
     * {@see self::confirm()}, because a window that began here would let one
     * operator start the countdown and a second confirm on day six — seven days
     * in the record, one in reality. The migration's CHECK ties the three
     * confirmation columns together so that cannot be written by hand either.
     *
     * @throws InvalidArgumentException when the business is not the tenant in
     *                                  context, or a request is already open
     */
    public function request(
        Business $business,
        User $requester,
        TenantDeletionReason $reason,
        ?string $detail = null,
    ): TenantDeletionRequest {
        $this->assertIsTenant($business);

        if ($this->pendingFor($business) !== null) {
            throw new InvalidArgumentException(
                'This account already has an open deletion request. Cancel it before filing another.'
            );
        }

        return DB::transaction(function () use ($business, $requester, $reason, $detail): TenantDeletionRequest {
            $request = new TenantDeletionRequest;

            $request->forceFill([
                'business_id' => $business->id,
                'business_ref' => $business->id,
                'reason' => $reason,
                'detail' => $this->normaliseNote($detail),
                'requested_by' => $requester->id,
                'requested_at' => now(),
            ])->save();

            return $request;
        });
    }

    /**
     * The second person agrees, and the clock starts.
     *
     * ⚠️ **THE SELF-CONFIRM REFUSAL IS THE WHOLE TWO-PERSON RULE**, and it is
     * refused here *and* by `tenant_deletion_requests_needs_two_people`. Decision
     * 216's layering, for the reason that governs every destructive control in
     * this codebase: the database catches the repair script, and this catches the
     * operator with a sentence they can act on.
     *
     * ⚠️ Testing it needs **two** distinct admins in the fixture. Decision 744
     * records the sibling failure exactly: a refusal whose test is satisfied by a
     * different guard is a refusal you can delete without going red.
     *
     * @throws InvalidArgumentException when the same person confirms their own
     *                                  request, or the request is not open
     */
    public function confirm(TenantDeletionRequest $request, User $confirmer): void
    {
        $this->assertOpen($request);

        if ($request->confirmed_at !== null) {
            return;
        }

        if ((int) $request->requested_by === (int) $confirmer->id) {
            throw new InvalidArgumentException(
                '`28` §9.5 requires two people. The person who requested a deletion cannot confirm it.'
            );
        }

        DB::transaction(function () use ($request, $confirmer): void {
            $confirmedAt = CarbonImmutable::now();

            $request->forceFill([
                'confirmed_by' => $confirmer->id,
                'confirmed_at' => $confirmedAt,
                'executes_at' => $confirmedAt->addDays(self::COOLING_DAYS),
            ])->save();
        });
    }

    /**
     * Stop it. `28` §9.5's *Restore*, and decision 1138's counterpart arriving.
     *
     * 1138 recorded that Restore is Delete's counterpart rather than Suspend's,
     * and that it stayed refused *"until there is a soft-delete window to restore
     * from"*. This slice creates the window, so this is that method — and it is
     * deliberately a cancellation of a pending act rather than a resurrection of
     * a destroyed one. **Nothing restores an executed deletion.** That is not an
     * omission: it is what makes the seven days mean something.
     *
     * Available to one person, unlike the request. A control that undoes a
     * destructive act should never be harder to reach than the act.
     */
    public function cancel(TenantDeletionRequest $request, User $actor, ?string $reason = null): void
    {
        $this->assertOpen($request);

        DB::transaction(function () use ($request, $actor, $reason): void {
            $request->forceFill([
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $this->normaliseNote($reason, 500),
            ])->save();
        });
    }

    /**
     * Destroy the account.
     *
     * Called by `tenants:execute-deletions` past `executes_at`, never directly
     * from a screen — there is no button anywhere that deletes an account now,
     * and that is deliberate.
     *
     * ⚠️ **REFUSES RATHER THAN FAILS.** Returns a refusal when the account
     * cannot be destroyed yet; throws only when asked to do something
     * incoherent. Decision 823's rule: a blocked automation that throws burns a
     * retry ladder against a condition only a human clears, and 364's sweep
     * counts attempts.
     *
     * ⛔ **THAT PARAGRAPH WAS A CLAIM AND NOT A MECHANISM, AND IT WAS FALSE FOR
     * THE WHOLE LIFE OF THIS CLASS — CORRECTED 2026-08-23 (8840, 8842).** On a
     * tenant holding any of the three `restrict` keys above, this did neither:
     * it raised a `QueryException` out of `DB::transaction()`, five lines under
     * a docblock promising a refusal. **This is 314–316's most-repeated defect
     * in its purest form** — the paragraph asserting the protection is what
     * stopped anybody looking for it, and 32 tests were green because every
     * fixture avoided the state. What makes the sentence true now is the
     * `catch` below, not this paragraph: a database refusal returns
     * {@see TenantDeletionOutcome::DatabaseRefused} and leaves the request open.
     *
     * ⚠️ **IT RETURNS A REASON, NOT A BOOLEAN** (1992). This returned `bool`
     * while there was exactly one way to refuse, and 1902 added a second — the
     * `purgeAllFor()` guard below — without the one place that reports a refusal
     * to a human learning about it. An unreachable bucket then read as *"it
     * still has a live subscription"*, which sends an operator to Stripe while
     * statutory deletions stop platform-wide. {@see TenantDeletionOutcome}
     * carries the difference and owns the sentence, so a further refusal cannot
     * be added without a `match` refusing to compile.
     *
     * ⛔ **AND "A THIRD REFUSAL CANNOT BE ADDED WITHOUT A `match` REFUSING TO
     * COMPILE" HAD A HOLE THE SIZE OF THE DATABASE** (8842). It is true of every
     * refusal *this application chooses to write*. Postgres writes refusals too,
     * and a `match` cannot see one. The count is also dropped from that sentence
     * on 5131's own rule — it said "two" and there were more before the ink
     * dried.
     */
    public function execute(TenantDeletionRequest $request): TenantDeletionOutcome
    {
        if ($request->executed_at !== null || $request->cancelled_at !== null) {
            return TenantDeletionOutcome::NotDue;
        }

        if ($request->executes_at === null || $request->executes_at->isFuture()) {
            return TenantDeletionOutcome::NotDue;
        }

        // ⚠️ TENANCY IS ESTABLISHED FROM `business_ref` BEFORE ANYTHING IS READ,
        // AND NOTHING HERE CAN READ `businesses` WITHOUT IT.
        //
        // Two wrong versions came first and the second is the instructive one.
        // `$request->business` is a `BelongsTo` on a globally scoped model, so
        // it answers according to the ambient tenant — and the sweep runs with
        // none. Replacing it with `withoutGlobalScopes()` looked like the fix
        // and is not: **RLS sits beneath the application scope**, `businesses`
        // is `ENABLE`+`FORCE`d on `app.business_id`, and dropping the Eloquent
        // scope drops the layer that was not stopping us. Decision 569's wall,
        // met here for the first time by something that has to *write*.
        //
        // Unfixed, the failure is silent and total: every swept deletion finds
        // null, takes the branch below, marks itself executed and **deletes
        // nothing** — a queue that empties while every account survives.
        $ref = (int) $request->business_ref;

        return Tenancy::actingAs($ref, function () use ($request, $ref): TenantDeletionOutcome {
            $business = Business::query()->whereKey($ref)->first();

            if (! $business instanceof Business) {
                // Already gone by some other path. Close the request rather than
                // sweeping it forever — a request that can never complete is a
                // permanent entry in a queue whose whole purpose is to be short.
                $request->forceFill(['executed_at' => now()])->save();

                return TenantDeletionOutcome::AlreadyGone;
            }

            // ⚠️ The billing refusal — see the class docblock. Before the
            // transaction, because an open transaction around a no-op is a lock
            // nobody needs.
            if ($this->gatewayWouldStillBill($business)) {
                return TenantDeletionOutcome::BillingActive;
            }

            // ⚠️ R2 BEFORE POSTGRES, AND A REFUSAL IF R2 SAYS NO (1902).
            // `tenant_exports` cascades on this delete, so the row naming the
            // object goes with it — and `storage_path` is not derivable from
            // anything that survives. Deleting Postgres first, or deleting it
            // anyway after a failed object-store call, leaves a complete
            // unencrypted copy of the account on R2 that nothing will ever look
            // for again. The order is the whole fix; the refusal is what makes
            // the order matter on the day the bucket is unreachable.
            //
            // Outside the transaction on purpose: an object store is not in it,
            // so a rollback could not undo a delete that had already happened,
            // and holding row locks across a network call to a third party is
            // the thing `gatewayWouldStillBill()` above is placed here to avoid.
            if (! $this->exports->purgeAllFor($business)) {
                return TenantDeletionOutcome::ObjectStoreRefused;
            }

            // ⚠️ SAME ORDER, SAME REFUSAL, FOR THE SAME REASON (5080). L0's own
            // path is not derivable from anything Postgres carries — it is a
            // partition on `business_id` alone — but the danger this guards
            // against is the same one `purgeAllFor()` guards against above:
            // deleting the business first, or ignoring a failed R2 call, leaves
            // years of that tenant's own pixel archive sitting under a business
            // id nothing will ever look for again. Outside the transaction for
            // the same reason as the export purge — an object store is not in
            // it, and a rollback could not undo a delete that already happened.
            //
            // A Phi-classified business always finds nothing here and this call
            // never refuses on its account: `ObjectStoreL0Archive::store()`
            // refuses a Phi batch outright (4863), so there is never anything
            // under its prefix to fail to delete.
            if (! $this->l0->purgeFor((int) $business->id)) {
                return TenantDeletionOutcome::ObjectStoreRefused;
            }

            // ⛔ **THE FOUR KINDS THE ERASURE DID NOT REACH, AND THE REASON THEY
            // ARE HERE RATHER THAN IN A NIGHTLY SWEEP** (8866, 8871, 8876).
            // Until now an erasure purged exports and the pixel archive and
            // nothing else, so a former customer's account left behind, in a
            // bucket: **another person's photograph**, **another person's
            // voice**, the owner's uploaded documents, and one rendered picture
            // per campaign recipient with a named living person's first name in
            // the pixels.
            //
            // ⛔ **AND THE ERASURE MADE THEM MORE PERMANENT, NOT LESS.**
            // `StorageRetention` is row-driven and tenant-scoped, and
            // `PruneStoredObjects` walks users, then each user's businesses,
            // then acts inside `Tenancy::actingAs()`. One line below, the
            // cascade destroys every row that names one of these paths and the
            // business that would put a tenant in context — so a **live**
            // tenant's inbound photograph was deleted at the operator's period
            // and an **erased** tenant's was kept for ever. The person who asked
            // to be forgotten got the worse outcome, which is the whole of why
            // this is not a tidy-up.
            //
            // ⚠️ **ALL FOUR RUN; NONE SHORT-CIRCUITS.** Chaining them with `||`
            // would stop at the first refusal and leave the other three kinds in
            // place until the retry, and this deletion is deferred either way —
            // so doing as much as can be done tonight is strictly better than
            // doing the first of it.
            //
            // ⛔ **A REFUSAL DEFERS THE WHOLE ERASURE, WHICH IS THE OPPOSITE OF
            // `revokeVendorGrants()` BELOW, AND THE DIFFERENCE IS THE RECORD.**
            // The Zernio grant is written down before the commit and retried
            // nightly from a row that survives (4880–4888); an uncredited
            // purchase is reported with a vendor handle that survives (8847).
            // **An object has no such record.** Two of these four prefixes are
            // derivable afterwards from `business_ref` and two are not — the
            // campaign ids go with the cascade — so proceeding after a failed
            // purge converts a bucket having a bad minute into a permanent
            // orphan, and 4880's own tie-break is which harm is reversible. A
            // deferred deletion is retried tomorrow. A stranger's photograph
            // that nothing can name is not.
            //
            // Outside the transaction and before it, for the two reasons the
            // export purge above already gives: an object store is not in the
            // transaction, and the row naming each object is destroyed by it.
            $purged = [
                $this->inboundMedia->purgeAllFor((int) $business->id),
                $this->voice->purgeVoicemailAudioFor((int) $business->id),
                $this->knowledge->purgeAllFor((int) $business->id),
                $this->whatsappMedia->purgeAllFor((int) $business->id),
                // ⚠️ **NO BUSINESS ID, AND THAT IS THE FINDING RATHER THAN AN
                // OVERSIGHT** (8876). `campaign-media/{campaign}/…` carries no
                // tenant, so this one reads the tenant's campaign ids while they
                // still exist. It is the only kind of the four that has to be
                // purged here or never.
                $this->campaignMedia->purgeAllFor(),
            ];

            if (in_array(false, $purged, true)) {
                return TenantDeletionOutcome::ObjectStoreRefused;
            }

            // ⚠️ READ BEFORE THE TRANSACTION, REPORTED AFTER IT COMMITS — see
            // {@see self::uncreditedPayments()} for why it is both.
            $uncredited = $this->uncreditedPayments($business);

            try {
                DB::transaction(function () use ($request, $business): void {
                    $this->releaseBillingRecords($business);

                    // ⛔ **THE THIRD-PARTY GRANT IS RECORDED HERE AND REVOKED AFTER
                    // THE COMMIT** (4731, 4880). Releasing the number without ending
                    // the Zernio connection left a deleted customer with a
                    // subprocessor holding `business.manage` — read *and* write — on
                    // their Google Business Profile, and no tenant record left for
                    // anybody to revoke it from. `gbp_connections` goes with the
                    // line below; `gbp_account_bindings` does not, so this is the
                    // last instant at which the platform can name the accounts
                    // involved. Afterwards the question is unaskable: every table
                    // that could answer it is RLS-`FORCE`d and reads zero rows from
                    // a sweep with no tenant (4732).
                    $this->gbp->markGrantsOwedForDeletion();

                    // ⛔ **BEFORE THE DELETE, AND NOT AS TIDYING** (2686, 2882).
                    // `phone_numbers.business_id` is `nullOnDelete`, so the line
                    // below nulls it while `role` stays `primary` — and
                    // `phone_numbers_shared_pool_has_no_business` refuses precisely
                    // that shape. A tenant holding a number could not be deleted at
                    // all: a CHECK violation inside this transaction, rolling back a
                    // statutory erasure with a SQLSTATE nobody could act on.
                    //
                    // ⚠️ **AND THE ORDER IS WHAT KEEPS THE AUDIT ENTRY.**
                    // `NumberLifecycle` files the state change under the number's
                    // tenant; run after the delete, there would be no tenant to file
                    // the record of why the number left. The number itself survives,
                    // parked, answering STOP and HELP at platform level for ninety
                    // days — see `TenantNumbers::releaseFromTenant()`.
                    $this->numbers->releaseFromTenant(
                        (int) $business->getKey(),
                        reason: 'The tenant was deleted under 28 §9.5. Parked before reassignment '
                            .'because consent records do not follow a number (2686).',
                    );

                    // Every `cascadeOnDelete` foreign key on `business_id` carries
                    // the tenant's own data out with this line, and every
                    // `nullOnDelete` one detaches what is shared.
                    //
                    // ⚠️ **NO COUNT HERE, DELIBERATELY** (5149). This counted both
                    // kinds of key by hand — true of the schema on the day it was
                    // written, wrong by the following wave, which added six more
                    // cascading keys on its own. A figure in prose beside a schema
                    // that grows every wave goes stale in silence and reads as
                    // precision while it does it, which is 2505's shape; the
                    // property is *every*, and that one cannot go stale.
                    $business->delete();

                    // `business_id` has just gone null by the FK's own rule;
                    // `business_ref` is what the record is read by afterwards.
                    $request->forceFill(['executed_at' => now()])->save();
                });
            } catch (QueryException $e) {
                // ⛔ **THE REFUSAL THIS CLASS PROMISED AND COULD NOT MAKE**
                // (8842). Narrow on purpose: a `QueryException` is the database
                // declining a statement, which is exactly *"the account cannot
                // be destroyed yet"* — a `restrict` key, a CHECK, a deadlock, a
                // connection that went away. A `catch (Throwable)` here would
                // swallow a programming error and report it as a deferral, and
                // there is a `catch (Throwable)` in the sweep for the rest.
                //
                // ⚠️ **THE TRANSACTION HAS ALREADY ROLLED BACK.**
                // `DB::transaction()` rolls back before it rethrows, so nothing
                // partial survives this line and the request is untouched: it
                // stays open, leaves nothing marked executed, and is due again
                // tomorrow.
                //
                // ⛔ **THE MESSAGE IS NOT LOGGED, AND THAT IS NOT CAUTION FOR
                // ITS OWN SAKE.** `QueryException::getMessage()` interpolates
                // the bindings into the SQL, and the statements inside this
                // transaction carry a release reason and the tenant's own phone
                // number among them. `CLAUDE.md`: sensitive data never reaches a
                // log. The SQLSTATE is the identifying half an operator can act
                // on and carries nothing of anybody's.
                Log::warning('a tenant erasure was refused by the database', [
                    'business_ref' => $ref,
                    'sqlstate' => $e->getCode(),
                ]);

                return TenantDeletionOutcome::DatabaseRefused;
            }

            $this->warnAboutUncreditedPayments($ref, $uncredited);

            // ⚠️ **REACHING THIS LINE MEANS THE ACCOUNT IS GONE.** Every
            // refusal returns before the call above, and that call either
            // commits or is turned into `DatabaseRefused` by the `catch`. The
            // outcome is therefore decided by what happens next, at the vendor.
            return $this->revokeVendorGrants($ref);
        });
    }

    /**
     * End the grants the deleted tenant left at Zernio.
     *
     * ⛔ **AFTER THE COMMIT, AND NEVER BEFORE IT.** Three orderings were
     * available and two of them are wrong in ways a green suite would not show.
     *
     * **Before the transaction** — the shape {@see GbpConnections::disconnect()}
     * uses for an owner pressing the button — revokes a *surviving* tenant's connection on
     * any path that then refuses or rolls back. The tenant is still there, their
     * listing is silently disconnected, and nothing in the record says why.
     *
     * **Inside the transaction** holds row locks across a third party's network
     * call, which the class docblock already refuses for the object store, and
     * cannot be undone by a rollback anyway.
     *
     * **After it**, with the obligation written down first, is the only ordering
     * where a crash is survivable: the stamp is committed with the erasure, so a
     * process that dies right here leaves `gbp:revoke-owed-grants` something to
     * find tomorrow.
     *
     * ⚠️ **A FAILURE DOES NOT UNDO THE ERASURE AND DOES NOT PRETEND TO SUCCEED.**
     * Those two pull against each other and the tie is broken on which harm is
     * reversible. Refusing the deletion would keep the customer's data past a
     * statutory deadline **and** would not revoke anything — the grant stands
     * either way — so the refusal buys nothing and costs a second breach. What
     * ships is: erase on time, record the obligation durably, retry it nightly,
     * and return a distinct outcome so the operator running the sweep is told.
     */
    private function revokeVendorGrants(int $businessRef): TenantDeletionOutcome
    {
        $result = $this->gbp->revokeOwedGrants($businessRef);

        // ⚠️ **A FAILURE OUTRANKS A SKIP, BECAUSE ONLY ONE OF THEM MEANS A THIRD
        // PARTY STILL HOLDS THE LISTING.** If both counts were non-zero the grant
        // genuinely left in Zernio's hands is the fact an operator has to act on;
        // the skipped row is reported by the nightly sweep and marked on the Ops
        // screen either way.
        if ($result['outstanding'] > 0) {
            return TenantDeletionOutcome::DestroyedGrantOutstanding;
        }

        // ⛔ **`skipped` GETS ITS OWN OUTCOME AND MUST NEVER BE ADDED BACK INTO
        // THE LINE ABOVE** (5074, 5100). It was, and the sum reported
        // `DestroyedGrantOutstanding` — *"the revocation could not be delivered
        // … a third party has read and write access to a former customer's
        // listing"* — for a row where **nothing was sent to Zernio at all** and
        // the business named is a **current** customer. That is the sentence
        // 5074 says sends an operator to disconnect a live account by hand, and
        // `RevokeOwedGbpGrants` was given two distinct sentences for exactly
        // this while this call site kept one.
        //
        // ⚠️ **STILL UNREACHABLE BY CONSTRUCTION.** `revokeOwedGrants()` skips a
        // stamped binding whose business still exists, and the business this
        // call names was destroyed one line above — so the count is zero here
        // today. It is read anyway because the alternative is reporting a clean
        // erasure while a grant is still held (4882), and it is read into its
        // *own* outcome because a defensive branch kept for the day the
        // construction breaks is worth nothing if what it says on that day is
        // false.
        if ($result['skipped'] > 0) {
            return TenantDeletionOutcome::DestroyedGrantNotSent;
        }

        return TenantDeletionOutcome::Destroyed;
    }

    /**
     * Everything confirmed, uncancelled, unexecuted and due.
     *
     * Ordered by id, never by a timestamp — `created_at` is nullable on 40 of
     * this schema's tables and Postgres sorts NULL *first* on a DESC order,
     * which is the lint in `Architecture\ConventionsTest` and the defect it was
     * written for.
     *
     * @return Collection<int, TenantDeletionRequest>
     */
    public function due(): Collection
    {
        return TenantDeletionRequest::query()
            ->whereNotNull('executes_at')
            ->whereNull('executed_at')
            ->whereNull('cancelled_at')
            ->where('executes_at', '<=', now())
            ->orderBy('id')
            ->get();
    }

    /**
     * The open request for this account, or null.
     */
    public function pendingFor(Business $business): ?TenantDeletionRequest
    {
        return TenantDeletionRequest::query()
            ->where('business_ref', $business->id)
            ->whereNull('executed_at')
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Is this account counting down?
     *
     * True only once the second person has confirmed. A filed-but-unconfirmed
     * request is a proposal, and a screen that showed it as pending destruction
     * would misreport an account nobody has agreed to destroy.
     */
    public function isCountingDown(Business $business): bool
    {
        return $this->pendingFor($business)?->confirmed_at !== null;
    }

    /**
     * Is there anything at **either** gateway that would keep charging this card?
     *
     * ⛔ **THIS WAS `stripeWouldStillBill()` AND ASKED ONE GATEWAY IN AN
     * APPLICATION WITH TWO, WHOSE PRIMARY IS THE ONE IT DID NOT ASK** (2056,
     * 8841). The failure was not a wrong refusal — it was **no refusal**: an
     * Authorize.Net tenant has no `stripe_subscription_id`, so this returned
     * false, the delete ran, and `authorize_net_customers`' `restrictOnDelete`
     * raised out of the transaction. **A guard named for one vendor is a guard
     * that stops being asked the day a second one ships**, and nothing reddened
     * when it did.
     *
     * ⚠️ **NOT `Subscriptions::isEntitled()`, AND THE DIFFERENCE IS INVERTED IN
     * THE CASE THAT MATTERS.** That method answers *may this tenant use the
     * product*, and it deliberately returns **true** when no subscription row
     * exists — decision 588's fail-open, because locking a paying customer out
     * on the strength of a missing row is the worse error. Asked here it refuses
     * every account that never reached Checkout, which is precisely the set with
     * nothing at a gateway to cancel. The first draft of this class used it and
     * three tests caught it.
     *
     * The honest question is narrower and has three clauses:
     *
     * 1. **Is there a vendor subscription id at all?** A tenant at
     *    `pending_checkout` has a row and no id on either gateway, so nothing is
     *    charging.
     * 2. **Is the row still in a billing state?** A `canceled` subscription is
     *    not charging.
     * 3. ⛔ **Is an end already recorded?** `ends_at` is written from the
     *    vendor's own answer — Stripe's `ended_at ?? cancel_at ?? canceled_at`,
     *    and Authorize.Net's paid-term arm — so a non-null value means the end
     *    is known and no renewal will be taken.
     *
     * ⛔ **THE THIRD CLAUSE IS NOT TIDINESS, IT IS WHAT STOPS THIS REFUSING FOR
     * EVER ON THE COMMONEST ERASURE THERE IS** (8844). A customer who leaves
     * cancels *first* and asks to be erased second. On an **annual** ARB term,
     * `Subscriptions::applyAuthorizeNetSubscription()` deliberately holds the
     * row at `active` with `ends_at = annual_term_ends_on` so the tenant keeps
     * the year they bought (2748, 2980–2999) — and **nothing ever writes
     * `canceled` afterwards**, because the vendor already sent its one
     * notification. Without this clause that tenant's statutory erasure is
     * deferred nightly, politely, with a correct-looking sentence, **for ever**.
     * That is a worse failure than the throw it replaced, not a smaller one: a
     * permanent refusal is 314–316's shape, where the throw at least stopped
     * the run loudly enough to be noticed.
     *
     * ⚠️ **AND IT DOES NOT WEAKEN THE STRIPE HALF.** A `cancel_at_period_end`
     * subscription has `ends_at` set and takes no further charge; what Stripe
     * still holds is a subscription object, which is the vendor's record and
     * not our customer's money. The harm this guard exists to prevent is
     * *"charging a card for something that no longer exists"*, and that
     * requires a charge.
     *
     * ⛔ **THIS PARAGRAPH NAMED A METHOD THAT DOES NOT EXIST, AND THEN
     * DESCRIBED A SCENARIO NOTHING CAN REACH — CORRECTED 2026-08-23 (9000,
     * 9001).** It read: *"THE THIRD CLAUSE HAS ONE KNOWN HOLE AND IT IS WRITTEN
     * DOWN RATHER THAN GUESSED AT (8850(a)).
     * `Subscriptions::recordAuthorizeNetSubscription()` writes a new
     * `authorize_net_subscription_id` without clearing `ends_at`, so a tenant
     * who cancels and then resubscribes carries a stale end until the first
     * vendor notification overwrites it — and inside that window this answers
     * false for an account ARB is billing. The window is one webhook wide …"*
     *
     * **There is no such method.** It is `startAuthorizeNetSubscription()`, and
     * the wrong name is the load-bearing half: a reader who greps the name they
     * are given finds nothing and cannot check the claim, so **the paragraph
     * explaining the hazard is what stops the next reviewer looking** — this
     * codebase's most-repeated defect, arriving as a citation rather than as an
     * assertion.
     *
     * ⛔ **AND THE WINDOW CANNOT OPEN AT ALL, WHICH IS WORSE THAN IT BEING
     * NARROW**, because a hole nobody can reach in a paragraph nobody can check
     * reads as a live risk somebody has already sized. Re-derived here rather
     * than inherited: **nothing in `app/` ever writes a null vendor
     * subscription id** — every writer of `stripe_subscription_id` and
     * `authorize_net_subscription_id` takes a non-nullable `string`, and
     * `StripeSubscriptionState::$subscriptionId` is `string` too.
     *
     * ⛔ **AND THIS PARAGRAPH SAID "BOTH CHECKOUT PATHS THROW WHEN ONE IS
     * ALREADY SET" UNTIL 2026-08-24, WHICH WAS FALSE WHEN IT WAS WRITTEN AND IS
     * WHERE 9076(c) CAME FROM — CORRECTED (9092–9095).** `BillingCheckout`
     * read `stripe_subscription_id` **only**, so a tenant holding an
     * Authorize.Net subscription — live *or* cancelled — reached Stripe
     * Checkout and **was charged**; what refused them was a database CHECK
     * inside the webhook, afterwards, where nothing reads the failure.
     * ⚠️ **The conclusion below is true today by a different mechanism than the
     * one this paragraph named**: the door guard reads `subscriptions.gateway`,
     * the column the CHECK itself reads, rather than an id written a network
     * round trip later — which is also why the symmetric id-shaped repair
     * would have left the declined-card population charged. ⚠️ **The line
     * numbers are deliberately not restated**; they moved in the wave that
     * corrected this, and a cited line is a claim that rots on its own.
     *
     * So there is
     * no resubscribe: the id can never become null, and while it is non-null no
     * second subscription can be created behind it.
     *
     * ⚠️ **WHAT IS ACTUALLY THERE IS A PRODUCT GAP AND IT IS RAISED RATHER THAN
     * BUILT FOR** (9002): a tenant who cancels can never buy again through
     * either checkout, and nothing in this application says so. That is the
     * owner's to answer. Building the `ends_at` clearing this paragraph asked
     * for would be building for a path nothing can take.
     */
    private function gatewayWouldStillBill(Business $business): bool
    {
        $subscription = $this->subscriptions->for($business);

        if ($subscription === null) {
            return false;
        }

        $hasVendorSubscription = $subscription->stripe_subscription_id !== null
            || $subscription->authorize_net_subscription_id !== null;

        if (! $hasVendorSubscription) {
            return false;
        }

        if ($subscription->ends_at !== null) {
            return false;
        }

        return $subscription->status !== SubscriptionStatus::Canceled;
    }

    /**
     * The three platform-scoped index rows `restrictOnDelete` makes our problem.
     *
     * ⛔ **THIS RELEASED ONE OF THEM AND WAS NAMED FOR THAT ONE** (8840, 8843).
     * `authorize_net_customers` (2056) and `credit_purchase_references` (3419)
     * carry the same key for the same written reason and neither was released,
     * so a tenant holding either raised `SQLSTATE[23503]` out of the erasure's
     * transaction. Enumerated rather than derived on purpose: a loop over
     * `pg_constraint` would delete from whatever table a future migration
     * restricts, which is the opposite of the judgement each of these rows
     * deserves. **The lint that keeps this honest is in
     * `tests/Feature/TenantDeletionTest.php`** — it reads the catalogue and
     * fails if a fourth `restrict` key on `businesses` appears without a
     * decision here.
     *
     * Reached only after {@see self::gatewayWouldStillBill()} has said no, so
     * what is removed is the local mapping for an account nothing is charging.
     *
     * ⚠️ **THE UNCREDITED-PAYMENT REPORT IS NOT HERE**, though it is about one of
     * these three tables: see {@see self::uncreditedPayments()} for why reading
     * and reporting are split either side of the transaction.
     *
     * ⚠️ **AND WHAT THEY INDEX IS ALREADY GONE BY THE TIME THIS RUNS.**
     * `subscriptions` and `credit_purchases` both `cascadeOnDelete`, so keeping
     * a pointer would preserve nothing — `PurchaseReconciliation::examine()`
     * reads the purchase behind the index and returns null when it is absent.
     * These keys are tripwires, and the tripwire is answered in front of the
     * delete rather than by hoarding the wire.
     *
     * ⚠️ **NEITHER VENDOR'S OWN OBJECT IS TOUCHED** — a Stripe customer, an
     * Authorize.Net customer profile and a transaction are the other party's
     * record of a commercial relationship that existed. We do not own them, and
     * deleting them would destroy somebody else's books as a side effect of
     * ours.
     */
    private function releaseBillingRecords(Business $business): void
    {
        DB::table('stripe_customers')->where('business_id', $business->id)->delete();
        DB::table('authorize_net_customers')->where('business_id', $business->id)->delete();
        DB::table('credit_purchase_references')->where('business_id', $business->id)->delete();
    }

    /**
     * The payments this erasure is about to destroy that money moved for and no
     * credit was ever written against.
     *
     * ⛔ **THIS EXPOSURE IS ONE THIS SLICE UNLOCKED AND THEREFORE OWES A WORD
     * ABOUT** (8847, lesson 18). Until 8843 a tenant holding a
     * `credit_purchase_references` row could not be erased at all — the delete
     * raised. Releasing that index makes them erasable, and `credit_purchases`
     * cascades, so an `authorized` or `mismatched` top-up — *money moved, no
     * credit written* — leaves with the account and takes the only record of
     * what is owed with it.
     *
     * ⛔ **A REFUSAL WAS THE WRONG ANSWER AND THE OBVIOUS ONE.**
     * `CreditPurchaseStatus::isSettleable()` looks exactly like the predicate to
     * refuse on, and `PurchaseReconciliation::abandon()` **deliberately leaves an
     * unreconcilable purchase settleable** — *"a person has to decide what
     * happened to this payment"* — so a refusal keyed on it would defer a
     * statutory erasure for ever over an abandoned Checkout session. Erase on
     * time and report what was destroyed is the ordering
     * {@see self::revokeVendorGrants()} already argues for on the vendor grant.
     *
     * ⚠️ **READ BEFORE THE TRANSACTION AND REPORTED AFTER IT COMMITS**, which is
     * the whole reason this is two methods. Reading has to happen first — after
     * the commit there is nothing left to read — and reporting has to happen
     * last, because a `DatabaseRefused` rollback would otherwise leave a log
     * line stating that an erasure destroyed something it did not touch.
     *
     * @return list<string> One vendor transaction handle per uncredited payment,
     *                      in the order the purchases were opened.
     */
    private function uncreditedPayments(Business $business): array
    {
        $handles = [];

        $purchases = CreditPurchase::query()
            ->where('business_id', $business->id)
            ->whereNull('credited_at')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            // Asked through the enum's own predicate rather than as a `whereIn`,
            // so a sixth status cannot inherit an answer nobody chose —
            // `CreditPurchaseStatus::moneyMoved()`'s own reasoning.
            if ($purchase->status->moneyMoved()) {
                $handles[] = $purchase->gateway_transaction_id ?? 'unknown';
            }
        }

        return $handles;
    }

    /**
     * Say so, durably, once the erasure has actually happened.
     *
     * ⚠️ **A LOG LINE RATHER THAN A BELL, AND RATHER THAN THE ACTIVITY FEED OR
     * THE AUDIT LOG.** Both of those are tenant-owned and cascade, so an entry
     * about this account is destroyed by the statement it is recording — this
     * class's own docblock makes that argument for `audit_log`.
     * `PurchaseReconciliation::abandon()` files exactly this fact about exactly
     * this population with `Log::warning`, so this is the established place for
     * it rather than a new one.
     *
     * ⚠️ **THE GATEWAY HANDLE IS THE POINT.** After the commit there is nothing
     * left to refund against, so the vendor's transaction id is the whole of
     * what an operator can act on. It is a vendor handle and not personal data;
     * no name, address, card or amount goes near this line.
     *
     * @param  list<string>  $handles
     */
    private function warnAboutUncreditedPayments(int $businessRef, array $handles): void
    {
        if ($handles === []) {
            return;
        }

        Log::warning('a tenant erasure destroyed credit purchases that money moved for and were never credited', [
            'business_ref' => $businessRef,
            'purchases' => count($handles),
            'gateway_transaction_ids' => implode(',', $handles),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertOpen(TenantDeletionRequest $request): void
    {
        if ($request->executed_at !== null) {
            throw new InvalidArgumentException('This account has already been deleted.');
        }

        if ($request->cancelled_at !== null) {
            throw new InvalidArgumentException('This deletion request was cancelled.');
        }
    }

    /**
     * ⚠️ The cross-tenant guard, {@see TenantSuspension::assertIsTenant()}'s
     * reasoning with the stakes raised: this class is only ever called by
     * somebody acting on an account that is not their own, and the wrong
     * business here is not a stopped account but a destroyed one.
     *
     * @throws InvalidArgumentException
     */
    private function assertIsTenant(Business $business): void
    {
        if ((int) $business->id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'Deletion acts on the tenant in context. Wrap the call in Tenancy::actingAs().'
            );
        }
    }

    private function normaliseNote(?string $note, int $limit = 2000): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : mb_substr($note, 0, $limit);
    }
}
