<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\AutopilotActionType;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\CreditUnit;
use App\Enums\UserRole;
use App\Exceptions\CreditMovementRefused;
use App\Exceptions\WithheldGrantCeiling;
use App\Models\Business;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\Subscriptions;
use App\Services\Config\DefaultsRegistry;
use App\Support\Admin\StaffActor;
use App\Support\Money;
use App\Support\PlanPricing;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * The one way the support console gives a tenant credits (`28` §9.1, §9.3's
 * quick-actions rail).
 *
 * ## Why `CreditKind::Adjust`, and not `CreditKind::Grant`
 *
 * `Grant` reads as the obvious fit for a name like "grant credits", and it is
 * the wrong one. That case's own docblock narrows it to a single, unbuilt use —
 * minting the *plan-allocation* pool, whose size is open question F and is
 * explicitly **not** answered — and warns that writing one anywhere else is
 * choosing a figure nobody has chosen, into a row nothing can edit afterwards.
 * A support agent's one-off goodwill credit is a different thing entirely: the
 * amount is chosen deliberately, by a human, bounded by
 * {@see UserRole::creditGrantCeiling()}, and explained by a required
 * reason — which is exactly `Adjust`'s own description, *"an operator
 * correction, in either direction"*, and the only kind `CreditLedger` already
 * refuses to accept without one.
 *
 * ## Why this exists rather than a Livewire component calling `CreditLedger`
 * directly
 *
 * `CreditLedger` is the whole application's ledger writer — the purchase flow,
 * the future sender's consumption, and this all share it, and none of those
 * callers knows or should know what a *support_agent* may do. The ceiling is a
 * fact about the console, not about the ledger, so it lives here: one place
 * that composes the ledger with the actor's role, and the only file the support
 * console's Livewire layer is permitted to reach for a credit write (held by an
 * `ArchitectureTest` lint, on `AccountLifecycle`'s and `AccountDirectory`'s
 * precedent).
 *
 * ## What this checks, and what it leaves to the gate
 *
 * What this class enforces is the thing a `Gate` cannot: *how much*, which is a
 * business rule shaped like an amount rather than a boolean, and has to hold
 * whether or not the caller is a Livewire component sitting behind
 * `CreditGrantAccess::GATE`.
 *
 * ⛔ **AND IT NOW ASKS `UserRole::mayGrantCredits()` AS WELL** (3840). This
 * section used to say *"eligibility is not asked here"*, on `AccountLifecycle`'s
 * split — correct as a division of labour and wrong as a description of what
 * held: the ceiling lookup answered **null** for an ineligible role, and null is
 * how an *unbounded* role answers, so a direct call by an `ops_admin` or an
 * `owner` succeeded with no limit at all. The gate is still where *may they act*
 * is decided and this does not restate its reasoning — it refuses to compute an
 * amount for an actor the gate would never have admitted, which is the same
 * posture 398 asks of the ceiling itself.
 *
 * ## Which product an operator grants, and the two denominations in play
 *
 * ✅ **ALL THREE, SINCE 3426 WAS CLOSED.** This granted {@see CreditProduct::Sms}
 * on every path until then, and the gap was that **support could not GIFT email
 * or AI credit**: a tenant whose emails or AI ran out because of something we did
 * had no remedy an operator could apply, and the only answer was to wait for the
 * period boundary.
 *
 * ⛔ **AND THAT IS NARROWER THAN THIS DOCBLOCK CLAIMED** (3846). It said
 * *"`CreditKind::Purchase` is constructed nowhere in `app/`, so a support grant is
 * the only path that adds credit a tenant did not receive as a monthly
 * allotment"*. **It is constructed** — `App\Services\Billing\CreditPurchases`,
 * the one permitted site, named by `BillingTest`'s funding lint — and
 * `TopUpCatalog::all()` sells **all three products**, so a tenant could already
 * *buy* what support could not *give*. The false half was the motivation written
 * up beside the change, which is the shape `CLAUDE.md` names: **a claim asserted
 * before it was checked, in the file the next reader trusts.**
 *
 * ⛔ **THE PRODUCT IS THE CALLER'S AND THE CEILING FOLLOWS IT.**
 * {@see UserRole::creditGrantCeiling()} takes the product for the reason this
 * class used to refuse the whole question: `28` §9.1's *"credits grant ≤ 500"* is
 * **500 texts**, and the same integer is half a monthly email allotment or five
 * cents of AI credit. The email and AI ceilings are the owner's to set, are
 * withheld rather than defaulted, and asking for one raises
 * {@see WithheldGrantCeiling} naming the decision that left it open.
 *
 * ⛔ **`$amount` IS THE FIGURE A PERSON TYPES AND THE LEDGER'S `delta` IS NOT
 * ALWAYS THE SAME NUMBER** (3420, 3331). The AI pool counts hundredths of a cent;
 * an operator granting AI credit thinks in cents, and asking them to type
 * `300000` for $30 is the 100× error with a human in the loop instead of a
 * compiler. So this class takes the **grant denomination** — whole sends for
 * texts and emails, integer **cents** for AI — and converts once, through
 * {@see CreditProduct::ledgerUnitsFromGrant()}, which is 3419's single conversion
 * boundary. ⚠️ **The conversion is here rather than on the screen because the
 * ceiling comparison is here**: a caller that converted first would leave the
 * guard measuring a figure it did not produce, and a second caller converting
 * differently is exactly 3331.
 */
final class CreditGrants
{
    public function __construct(
        private readonly AccountDirectory $accounts,
        private readonly CreditLedger $ledger,
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
        /**
         * ⚠️ **FOR ONE KEY — `billing.currency` — AND ONLY BECAUSE ONE OF THE
         * THREE PRODUCTS IS MONEY.** An AI grant has to be spoken about in a
         * currency, and 512 leaves `PlanPricing::format()` as the only exit from
         * cents to a string a person reads. Injected rather than constructed
         * inline because every other collaborator here is, and nothing in `app/`
         * builds this class with `new`.
         */
        private readonly DefaultsRegistry $registry,
        /**
         * ⚠️ **READ-ONLY, AND FOR ONE QUESTION — CAN THIS TENANT SPEND WHAT WE ARE
         * ABOUT TO GIVE THEM** (3926). `CreditKind::Adjust` lands in the pool 3441
         * gates, so the answer decides what the owner's own feed is told. It is
         * the service rather than the model, on `AccountDirectory`'s reasoning:
         * `subscriptions` is held to one reader by a lint, and a chokepoint
         * weakened for one sentence is a security change that looks like copy.
         */
        private readonly Subscriptions $subscriptions,
    ) {}

    /**
     * Grant a tenant one product's credits, and record why.
     *
     * @param  int  $amount  ⚠️ **In the grant denomination, which is not always
     *                       the ledger's**: whole sends for {@see CreditProduct::Sms}
     *                       and {@see CreditProduct::Email}, integer **cents** for
     *                       {@see CreditProduct::Ai}. See the class docblock.
     * @return bool false when the account no longer exists.
     *
     * @throws CreditMovementRefused when the amount is not positive, exceeds
     *                               the actor's ceiling for this product, or the
     *                               reason is too short to answer "why" a year
     *                               from now.
     * @throws WithheldGrantCeiling when nobody has ruled this actor's ceiling for
     *                              this product — see {@see UserRole::creditGrantCeiling()}.
     */
    public function grant(int $businessId, CreditProduct $product, int $amount, string $reason, User $actor): bool
    {
        $delta = $this->guardAmount($product, $amount, $actor);
        $reason = $this->guardReason($reason);

        $business = $this->accounts->business($businessId);

        if (! $business instanceof Business) {
            return false;
        }

        $actorLabel = StaffActor::internal($actor->getKey());
        // ⚠️ READ OUT HERE AND CARRIED IN, BECAUSE `DefaultsRegistry` IS A PLATFORM
        // SETTING AND HAS NO BUSINESS BEING ASKED INSIDE SOMEBODY'S TENANT. The
        // title is now composed *inside* the switch instead, because its other
        // half — whether this credit can be spent — is a tenant-scoped read (3926).
        $currency = $this->currency();

        Tenancy::actingAs((int) $business->id, function () use ($product, $amount, $delta, $reason, $actorLabel, $business, $currency): void {
            /*
             * ⛔ ONE TRANSACTION OVER ALL THREE WRITES, AND THERE WAS NONE (3928).
             * `CreditLedger::move()` opens and commits its own transaction, so the
             * audit row and the feed item ran **after** it, outside any. A failure
             * between them — a constraint, a dropped connection, a fatal — minted
             * credit with no `tenant.credits_granted` row, against `CLAUDE.md`'s
             * *"every sensitive action → append-only audit log"*. And an
             * append-only log cannot be repaired afterwards, so the missing row is
             * missing for ever.
             *
             * ⚠️ THE NESTING IS DELIBERATE AND ITS ONE HAZARD IS AVOIDED. Laravel
             * does **not** issue `ROLLBACK TO SAVEPOINT` when a concurrency error
             * fires inside a nested `DB::transaction()` — `ManagesTransactions`
             * decrements the level and rethrows a `DeadlockException` — which
             * leaves Postgres in `25P02` for anything that carries on. Nothing here
             * carries on: no `catch` sits between the ledger and these two writes,
             * and the attempt count is the default `1`, so the exception leaves
             * this closure, the outer handler issues a real `ROLLBACK`, and it
             * reaches the caller. **A retry or a swallowed exception here would be
             * the poisoning**, not the nesting.
             *
             * ⚠️ AND IT IMPROVES THE BROADCAST RATHER THAN RISKING IT.
             * `ActivityRecorded` is `ShouldDispatchAfterCommit`, so with no
             * transaction it fired immediately; it now fires when the row it
             * describes is actually durable.
             */
            DB::transaction(function () use ($product, $amount, $delta, $reason, $actorLabel, $business, $currency): void {
                $entry = $this->ledger->record(
                    // ⛔ THE CALLER'S PRODUCT, AND `CreditLedger::record()` HAS NO
                    // DEFAULT FOR ONE (3419). It used to be `CreditProduct::Sms`
                    // written in here, because the ceiling above was denominated in
                    // texts and the product could not be a choice without the ceiling
                    // becoming one too. 3426 made the ceiling a choice, so this is one.
                    $product,
                    CreditKind::Adjust,
                    $delta,
                    $actorLabel,
                    $reason,
                );

                $this->audit->record('tenant.credits_granted', $actorLabel, $business, [
                    // ⚠️ THE PRODUCT AND ITS UNIT RIDE WITH THE FIGURE, BECAUSE
                    // `amount` ALONE STOPPED BEING ANSWERABLE. `250` is 250 texts,
                    // 250 emails or two and a half cents depending on a column this
                    // entry did not used to carry, and `audit_log` is append-only —
                    // a row that cannot say which is a row nobody can read back.
                    'product' => $product->value,
                    'unit' => $product->unit()->value,
                    // ⚠️ THE LEDGER FIGURE, NOT THE TYPED ONE, SO THAT IT AGREES WITH
                    // `balance_after` BESIDE IT. For AI the two differ by a hundred,
                    // and two denominations in one metadata array is how a reader ends
                    // up comparing them.
                    'amount' => $delta,
                    'reason' => $reason,
                    'balance_after' => $entry->balance_after,
                ]);

                // `28` §9.4's attribution rule applied to a write that is not an
                // impersonation session: the owner sees that support was there and
                // what they did, in their own words, on the same feed
                // `Impersonation::recordWrite()` writes to for an act-as change.
                //
                // ⛔ AND IT IS ASKED HERE WHETHER THEY CAN ACTUALLY SPEND IT (3926).
                // Inside the tenancy, after the write, because the answer is a
                // tenant-scoped read and `isEntitled()` fails open with no row.
                $this->activity->record(
                    AutopilotActionType::SupportMadeAChange,
                    title: $this->activityTitle(
                        $product,
                        $amount,
                        $currency,
                        $this->subscriptions->isEntitled($business),
                    ),
                );
            });
        });

        return true;
    }

    /**
     * What the owner reads on their own feed.
     *
     * ⛔ **NAMED PER PRODUCT, BECAUSE "CREDITS" MEANT MESSAGES AND NOW MEANS
     * THREE THINGS.** `22`'s rule is that every string names what the person
     * controls; a line saying *"support added 250 credits"* to a tenant whose
     * emails ran out is the same sentence they would have read if their texts
     * had, which makes the feed unable to answer the only question being asked
     * of it.
     *
     * ⚠️ **THE AI LINE IS MONEY AND IS BUILT FROM THE TYPED CENTS, NOT FROM THE
     * LEDGER FIGURE.** Printing the ledger's hundredths would put `30000` in
     * front of an owner as though it were a count of something. It is composed
     * before the tenancy switch for no reason other than that
     * {@see DefaultsRegistry} reads a platform setting and has no business being
     * asked inside somebody's tenant.
     *
     * ⚠️ **"WRITING", NOT "AI CREDIT"** — 3551 settled the tenant-facing
     * vocabulary on the credit screen (*"what the assistant writes"*), and this
     * feed has the same reader. The operator's own screen says "AI credit",
     * which is `28` §9.1's vocabulary for `28` §9.1's audience.
     *
     * ⛔ **AND IT SAID THEY HAD CREDIT THEY COULD NOT SPEND** (3926). A support
     * grant is `CreditKind::Adjust`, which funds `CreditPool::TopUp`, and since
     * 3441 that pool is filtered out of the draw order while the plan is
     * inactive — so *"support added 750 email credits to your account"* was
     * written to the feed of an owner who could not send one of them. The
     * balance is real, the row is real, and the sentence was still false about
     * the only thing the reader would do next.
     *
     * ⚠️ **THE WORD IS *WAITING* AND NEVER *EXPIRED*** — `CLAUDE.md`'s own
     * wording for this state, because nothing is taken away and the balance
     * becomes spendable the moment the plan does. It is deliberately the same
     * sentence the tenant's own credit screen already carries
     * (`Livewire\Account\Credit`'s *"Your credit is waiting for you"*), so the
     * feed and the screen cannot tell them two different stories.
     */
    private function activityTitle(CreditProduct $product, int $amount, string $currency, bool $spendable): string
    {
        $said = match ($product) {
            CreditProduct::Sms => number_format($amount).' text message credits',
            CreditProduct::Email => number_format($amount).' email credits',
            CreditProduct::Ai => PlanPricing::format(Money::of($amount, $currency)).' of writing credit',
        };

        if (! $spendable) {
            // ⚠️ NOT "again" (9332). From 2026-08-25 an account can be here
            // because a free trial ran out, and it has never had a plan to start
            // again — the word would name something that never happened, on the
            // owner's own feed, in a line they cannot reply to.
            return 'GO AI EZ support added '.$said.' to your account — waiting for you '
                .'until your plan is running';
        }

        return 'GO AI EZ support added '.$said.' to your account';
    }

    /**
     * The currency platform figures are quoted in, defaulting the way every other
     * reader of this key defaults.
     */
    private function currency(): string
    {
        $stored = $this->registry->value('billing.currency');

        return is_string($stored) && $stored !== '' ? $stored : 'USD';
    }

    /**
     * The most this action may move in one go on any product, whatever the actor's
     * role — a **bound on the blast radius**, and not a policy ceiling.
     *
     * ⛔ **IT DOES NOT CATCH THE 100× KEYSTROKE, AND THIS DOCBLOCK CLAIMED IT DID**
     * (3921, superseding 3841's reading). The sentence here read *"a mistyped
     * `300000` for `3000` granted $3,000 of AI credit … a cap on the typed figure
     * closes both"*, and `300_000` is **exactly** {@see self::absoluteGrantCap()}
     * for {@see CreditProduct::Ai} while the comparison below is `>`. So the one
     * keystroke this method named as its reason was the one it admitted. `100000`
     * for a 1,000-text grant is the same shape on {@see CreditProduct::Sms}.
     *
     * ⛔ **AND NO FIGURE WOULD FIX IT, WHICH IS WHY THE CLAIM MOVED RATHER THAN
     * THE CONSTANT.** A cap is absolute and a slip of the hand is
     * *multiplicative*: for any cap `C`, typing `C` while meaning `C / 100` is
     * admitted exactly, so lowering `C` relocates the admitted keystroke instead
     * of closing it. Lowering it would also have to re-derive a figure that has a
     * derivation — ten of the largest pack a tenant can buy outright — into one
     * chosen to catch a particular typo, which is precisely how a safety bound
     * quietly becomes the considered limit 3639 and 3658 reserve to the owner.
     *
     * ⛔ **WHAT CATCHES THE KEYSTROKE IS THE CONFIRMATION** (3842), and it landed
     * in the same wave as this. It is asked **exactly when the actor's ceiling is
     * null** — the two roles that can reach AI at all, on every product — and it
     * reads the figure back in the denomination a person can check: *"Add $3,000
     * of AI credit to this account?"*, never `300000`. For a role that **has** a
     * ceiling it is the ceiling that catches the slip, and the blast radius is
     * that ceiling. ⚠️ **The confirmation is a guard on a keystroke and not a
     * security boundary** — `$grantConfirming` is a public Livewire property — so
     * this cap is still the only thing that holds against a caller with no screen
     * in front of it. **A bound, and a catch, are two different jobs.**
     *
     * ⛔ **WHAT IT DOES CLOSE, IT CLOSES COMPLETELY.** `92233720368547759` passes
     * Laravel's `integer` rule; `$cents * 100` in {@see CreditUnit::fromCents()}
     * then leaves `int` range, PHP returns a float, and an `int`-declared method
     * raises a `TypeError` — **an unhandled 500 on a staff money screen**, from a
     * figure a person can type. On SMS and email there is no multiplication and
     * the overflow lands one floor down instead, in the ledger's own arithmetic.
     * A cap on the **typed** figure, checked **before** the conversion, closes
     * that end of the range absolutely, because there is no arithmetic between
     * the two.
     *
     * ⚠️ **IT IS NOT THE WITHHELD FIGURE AND MUST NEVER BE READ AS ONE** (3639,
     * 3658). The email and AI *ceilings* are the owner's to rule and are still
     * withheld; this is deliberately an order of magnitude above any grant anyone
     * would defend, so that it can never be mistaken for a considered limit and
     * can never quietly become one. **Ten of the largest pack a tenant can buy
     * outright** (3301–3303's manual SKUs: 10,000 texts, 15,000 emails, $300 of
     * AI credit) — past that, a grant is a keystroke rather than a decision.
     * ⚠️ **This is also the reason the comparison stays `>` rather than `>=`.**
     * The boundary is admitted deliberately, and all three products are pinned at
     * it by test: a cap that refused its own figure would be an off-by-one policy
     * nobody ruled, hidden inside a guard described as arithmetic.
     *
     * ⚠️ **A CONSTANT AND NOT A REGISTRY KEY.** An Ops-editable safety bound is a
     * support surface offering to raise the thing that stops a typo, and a
     * `DefaultsRegistry` read here could raise `WithheldRegistryValue` from inside
     * a guard whose whole job is to answer.
     *
     * @return int in the **grant denomination** — whole sends for
     *             {@see CreditProduct::Sms} and {@see CreditProduct::Email},
     *             integer cents for {@see CreditProduct::Ai}.
     */
    public function absoluteGrantCap(CreditProduct $product): int
    {
        return match ($product) {
            CreditProduct::Sms => 100_000,
            CreditProduct::Email => 150_000,
            CreditProduct::Ai => 300_000,
        };
    }

    /**
     * The ceiling that actually holds, regardless of what the caller checked.
     *
     * ⚠️ **THE LOAD-BEARING GUARD.** `28` §9.1's one real number —
     * `support_agent` may grant at most 500 — is enforced here rather than only
     * in the Livewire form's validation rules, on decision 398's reasoning: a
     * form that refuses first makes the rule behind it untestable on its own.
     * Calling this method directly, past any Gate or form, is what proves the
     * ceiling holds rather than merely that the screen asks nicely.
     *
     * ⛔ **ELIGIBILITY IS ASKED HERE TOO, AND THIS CLASS USED TO SAY IT WAS NOT**
     * (3840). The old text — *"eligibility is not asked here … `CreditGrantAccess`
     * is the gate a caller must satisfy first"* — was true of the only caller and
     * false of the class: `guardAmount()` asked for a ceiling and got `null` back
     * for an `ops_admin`, an `owner` or a `None`, because null was **also** how a
     * role with no limit answered. A direct call by an ineligible actor therefore
     * succeeded **unbounded**. It was unreachable through the console and the two
     * docblocks that claimed this service is what proves the guard holds are 314's
     * shape, so the claim is now true rather than merely written down.
     *
     * ⛔ **AND IT IS PER PRODUCT NOW, WHICH GIVES 398 A SECOND EDGE.** The screen
     * asks {@see UserRole::creditGrantCeiling()} too, so a withheld ceiling
     * refuses there first and this arm would never be reached from a browser.
     * That is precisely why it is asked again here: an unruled ceiling has to
     * refuse a caller that is not a Livewire component at all.
     *
     * ⚠️ **THE ORDER IS CONVERT, THEN COMPARE.** The ceiling is in ledger units
     * and the amount arrives in the grant denomination, so comparing before
     * {@see CreditProduct::ledgerUnitsFromGrant()} would measure cents against a
     * limit in hundredths and pass a grant a hundred times the size of the one
     * that was allowed.
     *
     * @return int the movement in `$product`'s ledger units.
     */
    private function guardAmount(CreditProduct $product, int $amount, User $actor): int
    {
        if (! $actor->role->mayGrantCredits()) {
            // ⛔ FIRST, BECAUSE EVERY QUESTION BELOW PRESUPPOSES IT. Asking a role
            // that may not grant what its ceiling is has no answer, and
            // `creditGrantCeiling()` now throws rather than returning the null
            // that used to read as "no limit".
            throw CreditMovementRefused::because(
                $actor->role->label().' cannot add credits to a customer’s account. '
                .'A support agent, a support lead or a super admin can.'
            );
        }

        if ($amount <= 0) {
            throw CreditMovementRefused::because(
                'A credit grant adds credits, so the amount must be more than zero. '
                .'Taking credits back is a different action.'
            );
        }

        // ⛔ ON THE TYPED FIGURE, AND BEFORE THE CONVERSION. For AI the conversion
        // is what overflows `int`, so a cap applied afterwards is a cap applied to
        // a `TypeError`. See `absoluteGrantCap()`.
        $cap = $this->absoluteGrantCap($product);

        if ($amount > $cap) {
            throw CreditMovementRefused::because(
                'That is more than anybody adds in one action — the most is '
                .$this->capAsTyped($product, $cap).'. Check the figure, and add it '
                .'in more than one go if it is really what you meant.'
            );
        }

        $ceiling = $actor->role->creditGrantCeiling($product);
        $delta = $product->ledgerUnitsFromGrant($amount);

        if ($ceiling !== null && $delta > $ceiling) {
            throw CreditMovementRefused::because(
                $actor->role->label().' may grant at most '.$this->ceilingAsTyped($product, $ceiling)
                .' in one action. Ask a super admin or a support lead for more.'
            );
        }

        return $delta;
    }

    /**
     * A ceiling, in the units the person who hit it typed.
     *
     * ⚠️ **NOT `$ceiling.' credits'`, WHICH IS WHAT THIS SENTENCE USED TO SAY.**
     * The figure is in ledger units, and for AI those are hundredths of a cent —
     * so the refusal would have told an operator who typed dollars that they may
     * grant at most some six-figure number of nothing they recognise.
     * {@see CreditUnit::toCents()} is the one inverse (3571), so this reads a
     * limit back into the denomination it was hit in rather than deriving a
     * second one.
     *
     * ⛔ **IT IS UNREACHABLE FOR AI TODAY AND IS STILL WRITTEN CORRECTLY.** Both
     * roles with an AI ceiling have `null`, and the one role with a figure has it
     * withheld — so this arm renders the day the owner rules a number, which is
     * the day nobody will be reading this method.
     *
     * ⚠️ **THE `match` IS A RUN-TIME GUARANTEE AND NOT A COMPILE-TIME ONE** (3933).
     * `CreditProduct::unit()`'s docblock calls a fourth case *"a compile-time
     * conversation"* and this file repeated it; an unmatched enum case is an
     * `UnhandledMatchError` when the line executes, because PHP has no
     * exhaustiveness check. The value is that a new `CreditUnit` is **loud**
     * rather than silently given `Send`'s answer — which is the real argument,
     * and worth stating accurately, because a reader who believes the compiler is
     * watching stops looking.
     */
    private function ceilingAsTyped(CreditProduct $product, int $ceiling): string
    {
        return $this->capAsTyped($product, match ($product->unit()) {
            CreditUnit::Send => $ceiling,
            CreditUnit::HundredthsOfACent => $product->unit()->toCents($ceiling),
        });
    }

    /**
     * A figure that is **already** in the grant denomination, said in words.
     *
     * ⚠️ **TWO METHODS BECAUSE TWO DENOMINATIONS ARRIVE HERE, NOT FOR TIDINESS.**
     * A role ceiling is in ledger units and {@see self::absoluteGrantCap()} is in
     * the units a person types, so one method taking "a number" would be the
     * single place both could be handed in and only one of them printed
     * correctly — 3331's factor, in a sentence rather than in a balance. This one
     * converts nothing; {@see self::ceilingAsTyped()} converts and then calls it.
     *
     * ⚠️ **THE OPERATOR'S VOCABULARY, NOT THE TENANT'S** — "AI credit" and not
     * 3551's *"what the assistant writes"*, on 3651's reasoning.
     */
    private function capAsTyped(CreditProduct $product, int $typed): string
    {
        return match ($product) {
            CreditProduct::Sms => number_format($typed).' text message credits',
            CreditProduct::Email => number_format($typed).' email credits',
            CreditProduct::Ai => PlanPricing::format(Money::of($typed, $this->currency())).' of AI credit',
        };
    }

    /**
     * Bounded and required, on `StaffDirectory::guardReason()`'s reasoning: this
     * lands in `audit_log`, append-only forever, so the floor stops a request
     * with no explanation and the ceiling stops a document pasted into a column
     * nothing can edit afterwards.
     *
     * `CreditLedger` itself refuses an empty reason for `Adjust` — this refuses
     * a *thin* one before that, so the caller sees a sentence rather than "must
     * carry a reason" on a box they typed one character into.
     */
    private function guardReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw CreditMovementRefused::because(
                'Say why, in a sentence. This is the record of why a customer’s '
                .'balance moved, and support will be asked.'
            );
        }

        if (mb_strlen($reason) > 500) {
            throw CreditMovementRefused::because(
                'That reason is longer than 500 characters. Keep it to the '
                .'sentence somebody will read.'
            );
        }

        return $reason;
    }
}
