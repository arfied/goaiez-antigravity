<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Enums\CreditProduct;
use App\Enums\CreditUnit;
use App\Enums\ImpersonationMode;
use App\Enums\UserRole;
use App\Exceptions\CreditMovementRefused;
use App\Exceptions\ImpersonationRefused;
use App\Exceptions\WithheldGrantCeiling;
use App\Models\Business;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Impersonation\Impersonation;
use App\Services\Support\AccountDirectory;
use App\Services\Support\AccountLifecycle;
use App\Services\Support\CreditGrants;
use App\Support\Accounts\AccountSnapshot;
use App\Support\Admin\CreditGrantAccess;
use App\Support\Admin\LifecycleAccess;
use App\Support\Admin\StaffActor;
use App\Support\Admin\SupportAccess;
use App\Support\Money;
use App\Support\PlanPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Masmerise\Toaster\Toaster;

/**
 * Account 360 v1 — `28` §9.3, and the door into a customer's account (§9.4).
 *
 * One page per business: who they are, what they have running, and the two
 * buttons that open a support session. Reached by naming a customer rather
 * than by picking one off a list.
 *
 * ## ⚠️ Why there is still no list, decided rather than inherited
 *
 * `BUILD-PLAN` §2.7 defers the question to this screen, so it is answered here
 * rather than in a fifth docblock repeating that it was deferred. `businesses`
 * carries RLS, `FORCE`d, with two policies — `tenant_isolation` on
 * `app.business_id` and `owner_lookup` on `app.user_id`. A support agent owns
 * no business and has no tenant, so a list returns zero rows, and
 * `withoutGlobalScopes()` does not help because it drops the *application*
 * scope and RLS is the layer beneath it. Serving one needs a third policy
 * admitting platform staff to every tenant.
 *
 * It is refused because of what the list would contain. `34` §2.2 locks eleven
 * columns into it — health grade with an 8-week sparkline, lifecycle stage,
 * CSM, MRR, five saved views — and **this application can populate three**.
 * The rest belong to row 17's tables and to billing, and no `tenant_usage`,
 * `plan_overrides`, ticket or health store exists. A permanent hole through the
 * boundary, bought for a three-column list, is decision 566's vacuity trap at
 * the scale of a screen. Decision 800 records what would change the answer.
 *
 * ## ⚠️ Two ways in, because the first one was unopenable
 *
 * Four staff screens now ask for an account number, and **nothing in this
 * application has ever shown that number to anybody who could supply it** — no
 * owner-facing view renders it, and §9.3's "one click from Account 360"
 * assumes a ticketing system that does not exist. An agent with a customer on
 * the phone had no way in at all. The owner's email address is what they hold,
 * and answering "which business does this person own" is what `owner_lookup`
 * was written for. See `AccountDirectory` for why that is a resolution path
 * rather than the list this screen refuses.
 *
 * ## What is not here, and why it is not silence
 *
 * `34` §2.2's seven tabs are Overview, Timeline, Billing, Support, Settings
 * snapshot, Usage & Cost, and Compliance. Two are rendered. The other five are
 * named on the screen with the reason, because a tab rendering an empty panel
 * is worse than an absent one: it reads as a customer with no history rather
 * than as a feature that does not exist.
 */
final class Accounts extends Component
{
    /** What the agent typed — an account number, or the owner's email. */
    public string $reference = '';

    /**
     * The account they have named, or null.
     *
     * ⚠️ `#[Locked]` BECAUSE `resolve()` IS THE ONLY THING THAT MAY SET IT, AND
     * THE AUDIT ROW IS WRITTEN THERE. It was an ordinary public property until
     * this slice, which means it arrived in the update payload: anybody who
     * could reach this component could set it to any integer and have
     * `render()` show that business's name, its owner, its plan and its
     * locations — or call `start()` and open a session against it — with
     * `resolve()` never called and nothing recording the read. That is
     * `PhiTenants`' finding in its sibling screen, and it was live here for as
     * long as this screen has existed.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * ⚠️ **THE ENUM'S OWN VALUE AND NOT THE STRING `'view'`** (3920). `resolve()`
     * used to re-assign this after a hit and now resets it with the rest of the
     * page, so the *default* is what a resolved account starts on — and a bare
     * literal would be a second place `ImpersonationMode::View` is written down,
     * one rename away from starting every session on nothing.
     */
    public string $mode = ImpersonationMode::View->value;

    public string $reason = '';

    public string $ticketRef = '';

    /**
     * The typed reason for a lifecycle action (`28` §9.5).
     *
     * ⚠️ **A SECOND FIELD RATHER THAN REUSING `$reason`.** That one belongs to
     * the impersonation form and is cleared when an account resolves; sharing
     * it would mean a half-typed suspension reason arriving on a support
     * session's audit row, or the reverse. Two forms on one screen with one
     * text property is how a reason ends up filed against the wrong act.
     */
    public string $lifecycleReason = '';

    /** Which lifecycle action is awaiting confirmation: 'suspend', 'lift', or ''. */
    public string $confirming = '';

    /**
     * The amount typed on the grant-credits form (`28` §9.3's rail).
     *
     * A string, matching `$reference` and every other bound text input on this
     * screen — cast to int only where the ceiling and the ledger need one.
     *
     * ⚠️ **WHAT IT COUNTS DEPENDS ON {@see self::$grantProduct}**, and the label
     * beside the box says so: whole messages for texts and emails, **cents** for
     * AI credit (3420). {@see CreditGrants::grant()} is what converts; nothing on
     * this screen does arithmetic on it.
     */
    public string $grantAmount = '';

    /**
     * Which of the three products the operator is granting (3426).
     *
     * ⛔ **A CHOICE, AND THE PRE-SELECTED VALUE IS NOT A RECOMMENDATION.** It
     * holds {@see CreditProduct::Sms} at rest because that is what this rail
     * granted for its whole life, so an operator's existing habit still produces
     * the result it always did. The three options are rendered as equals and the
     * amount label changes underneath them. **A screen that hid the question
     * would let an operator grant texts to a tenant who ran out of emails**,
     * which is the failure 3426 exists to end rather than to relocate.
     *
     * ⚠️ **A STRING, VALIDATED WITH `Rule::enum()`, LIKE `$mode` BESIDE IT.**
     * Anything reachable from the browser is a string until it has been checked,
     * and casting an unchecked one into a backed enum is how a `ValueError`
     * becomes a 500 on a staff screen.
     */
    public string $grantProduct = CreditProduct::Sms->value;

    /**
     * ⚠️ **A THIRD REASON FIELD, NOT A SHARED ONE.** `$reason` belongs to the
     * impersonation form and `$lifecycleReason` to Suspend/Pause; sharing either
     * one means a half-typed sentence for one action landing on another's audit
     * row. Three forms on one screen, three fields — `AccountLifecycle`'s own
     * reasoning, applied a third time.
     */
    public string $grantReason = '';

    /**
     * Whether the grant form is waiting for the operator to confirm (3842).
     *
     * ⛔ **CONFIRM IS RESERVED FOR THREE THINGS AND ONE OF THEM IS "ANYTHING THAT
     * SPENDS MONEY"** (`CLAUDE.md`). The *tenant's* own top-up path has a
     * confirmation; this rail — which mints credit the platform pays for, at
     * 3412's 8:1 retail rate on AI — had none, and for a `support_lead` or a
     * `super_admin` there was no ceiling either, so a mistyped `300000` for
     * `3000` added **$3,000** of AI credit in one click.
     *
     * ⛔ **AND THIS IS THE ONLY THING THAT REFUSES THAT KEYSTROKE — THE CAP DOES
     * NOT** (3921). This docblock and {@see CreditGrants::absoluteGrantCap()}'s
     * both read as though the cap were the guard and this were a belt to its
     * braces. `300_000` **is** the AI cap and the comparison there is `>`, so the
     * canonical mistype is admitted by it exactly — and no figure would change
     * that, because a cap is absolute and a slip of the hand is multiplicative.
     * **The order is the other way round.** This is what catches the keystroke,
     * by reading it back as *"$3,000 of AI credit"* rather than as `300000`; the
     * cap bounds how wrong one un-cancelled action can be, and holds against a
     * caller with no screen at all.
     *
     * ⚠️ **IT ASKS EXACTLY WHEN NOTHING ELSE IS CHECKING THE FIGURE** — when the
     * actor's ceiling is `null`. A `support_agent` inside `28` §9.1's 500 is doing
     * the routine act the rail was designed for and is not asked; the roles with
     * no limit are asked every time, on every product. A confirm step on the
     * bounded case would be in the way of the thing an agent does on a call, and
     * 826's reasoning against decoration confirmations applies to it.
     * ⚠️ **That asymmetry is a departure from the rule as `CLAUDE.md` writes it**,
     * which has no *"unless the actor is bounded"* clause, and it is recorded as
     * the override it is (3931) rather than left reading as compliance.
     *
     * ⚠️ **IT IS A GUARD ON A KEYSTROKE AND NOT A SECURITY BOUNDARY.** It is a
     * public property, so a crafted request can arrive with it already true — the
     * thing that *bounds* this action past any form is
     * {@see CreditGrants::absoluteGrantCap()}, in the service. **A bound and a
     * catch are two different jobs**, and neither is the other's fallback.
     */
    public bool $grantConfirming = false;

    public function mount(): void
    {
        $this->authorize(SupportAccess::GATE);
    }

    /**
     * Put an account on screen, and record that staff opened it.
     *
     * A miss says the same thing whichever way it missed. A number belonging to
     * nobody, an email nobody signed up with, and a typo are one answer on
     * purpose — the screen is behind a staff gate, so the disclosure is bounded
     * either way, but a message distinguishing them would turn the box into an
     * oracle for "is this address a customer" with no reason to be one.
     *
     * ⛔ **EVERY FORM ON THE PAGE IS CLEARED HERE, AND ONLY THREE FIELDS WERE**
     * (3920). {@see self::cancel()} already held the invariant in as many words —
     * *"a pending 'are you sure' about one customer's $30 must never be standing
     * over the next account's form"* — and this is **the other way into a new
     * account**, so it had to hold it too and did not. It is not a crafted
     * request to reach: {@see self::render()} shows the search box again whenever
     * a snapshot comes back null, so an operator whose account vanished mid-ticket
     * types the next reference with a live confirmation and a half-typed reason
     * about the *previous* customer still on the component — and one click grants
     * to the new one.
     *
     * ⚠️ **AND IT RESETS BEFORE THE LOOKUP, SO A MISS CLEARS TOO.** A reference
     * that matches nothing is the commonest way an operator moves between
     * accounts, and it is the path that returns early.
     */
    public function resolve(AccountDirectory $accounts): void
    {
        $this->authorize(SupportAccess::GATE);

        $this->reset([
            'businessId', 'mode', 'reason', 'ticketRef',
            // The lifecycle rail's pair, which had the identical gap: a suspend
            // confirmation and its typed reason survived into the next account.
            'lifecycleReason', 'confirming',
            'grantAmount', 'grantReason', 'grantProduct', 'grantConfirming',
        ]);
        $this->resetErrorBag();

        $id = $accounts->open(trim($this->reference), $this->actor());

        if ($id === null) {
            $this->addError('reference', 'No account matches that. Try the account number, or the email address they signed up with.');

            return;
        }

        $this->businessId = $id;
    }

    public function cancel(): void
    {
        $this->reset([
            'businessId', 'reference', 'reason', 'ticketRef',
            'lifecycleReason', 'confirming', 'grantAmount', 'grantReason',
            // ⚠️ THE PRODUCT GOES BACK TO ITS DEFAULT WITH THE REST OF THE FORM.
            // Leaving it set would carry a choice made about one customer onto
            // the next account an agent opens, which is the shape the three
            // separate reason fields above exist to prevent.
            'grantProduct',
            // ⚠️ AND SO DOES A HALF-ANSWERED CONFIRMATION. A pending "are you
            // sure" about one customer's $30 must never be standing over the
            // next account's form.
            'grantConfirming',
        ]);
    }

    /**
     * A different product is a different question, so an answered confirmation
     * does not survive the change (3842).
     *
     * ⚠️ **THE RADIO IS `wire:model.live`, WHICH IS WHY THIS EXISTS.** The
     * confirmation panel names an amount *and a product*; leaving `$grantConfirming`
     * true while the product moves underneath it would put "Yes, add $30 of AI
     * credit" one click away from an operator who has just switched to text
     * messages.
     */
    public function updatedGrantProduct(): void
    {
        $this->grantConfirming = false;
    }

    /**
     * Back to the form, with what was typed still in it.
     *
     * `cancelConfirm()`'s sibling and deliberately not the same method: the
     * lifecycle rail's `$confirming` and this are two states on two forms, and one
     * cancel button clearing both is how an operator loses a half-typed suspension
     * reason by backing out of a credit grant.
     */
    public function cancelGrantConfirm(): void
    {
        $this->grantConfirming = false;
    }

    /**
     * Ask before doing the two things that need asking.
     *
     * ⚠️ **THE CONFIRMATIONS ARE THE OPPOSITE WAY ROUND FROM THE OWNER'S OWN
     * SCREEN, AND BOTH ARE RIGHT.** Decision 826 confirms *resuming* and not
     * pausing, because a confirm step on somebody's own emergency stop is in
     * the way at the moment they need it most. Nothing here is an emergency
     * stop by the person it affects: every one of these acts on a company that
     * is not the agent's, so **both directions of the suspension are
     * confirmed** — applying one takes a paying customer's product away, and
     * lifting one ends a compliance hold somebody else applied for a reason
     * this agent may not have read.
     *
     * Support's *pause* is not confirmed, and that asymmetry is deliberate:
     * the owner can undo it themselves from `/account`, and it is the action an
     * agent takes while a customer is on the phone asking them to.
     */
    public function confirm(string $action): void
    {
        $this->confirming = in_array($action, ['suspend', 'lift'], true) ? $action : '';
    }

    public function cancelConfirm(): void
    {
        $this->confirming = '';
    }

    /**
     * Stop this account for cause (`28` §9.5's Suspend).
     *
     * ⚠️ **THE GATE IS ASKED HERE AND NOT ONLY ON THE ROUTE.** Decision 630:
     * `can:` refuses during route matching, so a route-gate test proves the
     * route and nothing about the component — and this screen is deliberately
     * on the *wider* `SupportAccess` gate, so a `support_agent` genuinely
     * reaches this method. The route gate is not this action's gate at all.
     */
    public function suspend(AccountLifecycle $lifecycle): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        $this->act(
            fn (int $id): bool => $lifecycle->suspend($id, $this->actor(), $this->lifecycleReason),
            'That account is on hold',
            'Say why. A suspension without a reason is refused.',
        );
    }

    /**
     * Let it start again.
     *
     * Same gate as applying it, on `UserRole::canSuspendTenants()`'s reasoning:
     * a suspension lifted by a wider set of people is only as strong as its
     * weakest reverser.
     *
     * ⚠️ The message is "off hold", never "running again". Lifting a suspension
     * does not start an account the owner had paused, and telling an agent
     * otherwise is how a customer is told their product is back when it is not.
     */
    public function liftSuspension(AccountLifecycle $lifecycle): void
    {
        $this->authorize(LifecycleAccess::SUSPEND);

        $this->act(
            fn (int $id): bool => $lifecycle->lift($id, $this->actor()),
            'That account is off hold',
        );
    }

    /**
     * Pause on the client's behalf (`28` §9.5, §9.3's rail).
     */
    public function pauseClient(AccountLifecycle $lifecycle): void
    {
        $this->authorize(LifecycleAccess::PAUSE);

        $this->act(
            fn (int $id): bool => $lifecycle->pause($id, $this->actor(), $this->lifecycleReason),
            'That account is paused',
            'Say why. The owner sees this on their own screen.',
        );
    }

    /**
     * Start it again for them.
     */
    public function resumeClient(AccountLifecycle $lifecycle): void
    {
        $this->authorize(LifecycleAccess::PAUSE);

        $this->act(
            fn (int $id): bool => $lifecycle->resume($id, $this->actor()),
            'That account is running again',
        );
    }

    /**
     * Grant this account credits (`28` §9.1, §9.3's rail).
     *
     * ⚠️ **THE GATE IS ASKED HERE, NOT ONLY ON THE ROUTE** — decision 630's
     * rule, applied a third time on this screen. This method authorizes for
     * itself rather than trusting the route's `can:` middleware, which refuses
     * during route matching and would prove nothing about whether this method
     * itself is reachable.
     *
     * ⚠️ **THE CEILING IS NOT CHECKED HERE EITHER, DELIBERATELY.** Validating
     * `max:{$ceiling}` in the form gives a friendly message before a request is
     * even sent; {@see CreditGrants::grant()} is what actually enforces it, and
     * this method never duplicates that arithmetic — a form rule and a service
     * rule that drift is worse than one that is merely inconvenient to bypass.
     *
     * ⛔ **THE PRODUCT IS VALIDATED BEFORE THE CEILING IS ASKED FOR, AND THE
     * ORDER IS LOAD-BEARING** (3426). The ceiling is per product now, so a
     * tampered `grantProduct` would otherwise reach
     * {@see UserRole::creditGrantCeiling()} as a `ValueError` from
     * `CreditProduct::from()` — a 500 where a validation message belongs.
     *
     * ⛔ **AND IT ASKS BEFORE IT MINTS MONEY, WHICH IT DID NOT** (3842). `CLAUDE.md`
     * reserves CONFIRM for three things and one of them is *anything that spends
     * money*; a goodwill grant spends ours. The question is asked exactly when the
     * actor has no ceiling — see {@see self::$grantConfirming} — so the bounded
     * routine act stays one click and the unbounded one is read back before it
     * happens.
     *
     * ⚠️ **AND AN UNRULED CEILING IS CAUGHT SEPARATELY FROM A BREACHED ONE.**
     * {@see WithheldGrantCeiling} means nobody has set a limit for this product
     * yet and the operator did nothing wrong; {@see CreditMovementRefused} means
     * they asked for too much. Both land on a field so they are seen, but they
     * are different sentences and the first is shown against the *product* radio,
     * because changing the amount cannot help.
     */
    public function grantCredits(CreditGrants $grants, DefaultsRegistry $registry, AccountDirectory $accounts): void
    {
        $this->authorize(CreditGrantAccess::GATE);

        try {
            $this->validate([
                'grantProduct' => ['required', Rule::enum(CreditProduct::class)],
            ], attributes: ['grantProduct' => 'credit type']);
        } catch (ValidationException $e) {
            // ⚠️ THE SAME DROP AS EVERY OTHER REFUSAL BELOW, AND THIS ONE WAS
            // MISSING IT (3927). The panel replaces the form, so an error raised
            // while it is open is an error nobody can act on — and this arm is
            // the one reachable with the confirmation already true, because
            // `grantProduct` is a public property a crafted request can spoil
            // between the first click and the second.
            $this->grantConfirming = false;

            throw $e;
        }

        $product = CreditProduct::from($this->grantProduct);

        try {
            $ceiling = $this->ceilingAsTyped($product);
        } catch (WithheldGrantCeiling $e) {
            $this->grantConfirming = false;
            $this->addError('grantProduct', $e->getMessage());

            return;
        } catch (InvalidArgumentException) {
            // ⛔ THE SECOND OF THE TWO REFUSALS `creditGrantCeiling()` MAKES, AND
            // ONLY THE FIRST WAS CAUGHT (3930). A role that may not grant at all
            // raises this rather than `WithheldGrantCeiling` — 3840 kept them
            // deliberately apart — and it was an unhandled 500 here. The gate
            // above refuses such an actor first, which is exactly 398's argument
            // for catching it anyway: a `catch` that assumes an unreachable branch
            // stays unreachable is a 500 waiting for the next path that skips the
            // check, and this method already applies that reasoning one arm over.
            //
            // ⚠️ AND THE EXCEPTION'S OWN MESSAGE IS NOT SHOWN. It is written for
            // the developer who reached this — *"Ask UserRole::mayGrantCredits()
            // first"* — and an operator reading it would learn nothing they
            // control.
            $this->grantConfirming = false;
            $this->addError('grantProduct', 'Your role cannot add credits to a customer’s account. '
                .'A support agent, a support lead or a super admin can.');

            return;
        }

        // ⛔ THERE IS ALWAYS A `max:` NOW, AND THERE USED NOT TO BE (3841). A role
        // with no ceiling produced no rule at all, so `min:1` was the only bound
        // on a box that mints credit the platform pays for — and an integer large
        // enough to overflow the AI conversion was a 500 rather than a sentence.
        // The absolute cap is the service's and is asked rather than restated
        // here, on the same reasoning the ceiling is.
        $cap = $grants->absoluteGrantCap($product);
        $max = $ceiling === null ? $cap : min($ceiling, $cap);

        try {
            $this->validate([
                'grantAmount' => ['required', 'integer', 'min:1', "max:{$max}"],
                'grantReason' => ['required', 'string', 'min:10', 'max:500'],
            ], attributes: [
                'grantAmount' => 'amount',
                'grantReason' => 'reason',
            ]);
        } catch (ValidationException $e) {
            // ⚠️ A REFUSED FIGURE DROPS OUT OF THE CONFIRMATION AND BACK TO THE
            // FORM. The confirmation panel replaces the amount and reason boxes,
            // so an error shown while it is open is an error nobody can act on.
            $this->grantConfirming = false;

            throw $e;
        }

        if ($this->businessId === null) {
            // ⚠️ SAID RATHER THAN SWALLOWED (3927). A silent return after
            // validation passed reads to the operator as a click that did
            // nothing — the amount and the reason stay on screen, no error
            // appears, and no credit is added. It is only reachable by clicking
            // Add on a page with no account open, which is a state a crafted
            // request produces and a stale tab reproduces.
            $this->grantConfirming = false;
            $this->addError('reference', 'Open an account first — nothing was added.');

            return;
        }

        if ($ceiling === null && ! $this->grantConfirming) {
            // ⛔ CONFIRM, BECAUSE NOTHING ELSE IS CHECKING THIS FIGURE. See
            // `$grantConfirming`: the roles with no ceiling are the ones that can
            // reach AI credit, where the typed figure is money rather than a count
            // of anything. The panel is rendered from the live properties rather
            // than from a snapshot, so it cannot describe an amount other than the
            // one this method would grant.
            $this->grantConfirming = true;

            return;
        }

        try {
            $exists = $grants->grant(
                $this->businessId,
                $product,
                (int) $this->grantAmount,
                $this->grantReason,
                $this->actorUser(),
            );
        } catch (WithheldGrantCeiling $e) {
            // Unreachable through the form, which asked the same question above —
            // and caught anyway, because 398's rule cuts both ways: the guard
            // that cannot be reached from here is still the one that holds, and a
            // `catch` that assumes otherwise turns a refusal into a 500 the first
            // time somebody adds a path that skips the check.
            $this->grantConfirming = false;
            $this->addError('grantProduct', $e->getMessage());

            return;
        } catch (CreditMovementRefused $e) {
            $this->grantConfirming = false;
            $this->addError('grantAmount', $e->getMessage());

            return;
        }

        if (! $exists) {
            $this->grantConfirming = false;
            $this->addError('grantAmount', 'That account no longer exists.');

            return;
        }

        Toaster::success($this->grantedWording(
            $product,
            $this->currency($registry),
            // ⛔ ASKED AFTER THE WRITE (3926). A support grant lands in the pool
            // 3441 gates, so on an inactive plan the operator has just added
            // credit nothing can spend — and the toast said only that it had been
            // added. The rail says the same thing persistently; this says it at
            // the moment they did it.
            $accounts->creditIsSpendable($this->businessId),
        ));

        // ⚠️ THE PRODUCT GOES BACK WITH THE AMOUNT AND THE REASON, AND IT USED NOT
        // TO (3843). A rail left showing "Emails" over an empty amount box is the
        // next grant already half-answered, by a choice made about the previous
        // one — `cancel()`'s own reasoning, which this line was missing.
        $this->reset(['grantAmount', 'grantReason', 'grantProduct', 'grantConfirming']);
    }

    /**
     * This actor's ceiling for this product, in the units the form's box takes.
     *
     * ⚠️ **THE ONE PLACE THIS SCREEN CONVERTS ANYTHING, AND IT CONVERTS A LIMIT
     * RATHER THAN AN AMOUNT.** The ceiling is stored in ledger units; the box
     * takes cents for AI (3420), so a `max:` rule built from the raw figure would
     * refuse at a hundredth of the real limit. {@see CreditUnit::toCents()} is
     * the single inverse (3571) and nothing here re-derives the factor.
     *
     * ⛔ **IT IS UNREACHABLE FOR AI TODAY AND IS WRITTEN CORRECTLY ANYWAY.** The
     * only role with an AI figure has it withheld, and the two roles that may
     * grant AI have no ceiling at all — so this returns null or throws for that
     * product, and the conversion arm waits for the owner's ruling. Writing the
     * ceiling as a bare integer would have been the bug that ruling shipped with.
     *
     * @throws WithheldGrantCeiling when nobody has ruled a ceiling for this
     *                              product and this role.
     * @throws InvalidArgumentException when this role may not grant credits at
     *                                  all, so it has no ceiling to report.
     *                                  ⚠️ **THIS TAG WAS MISSING AND IS WHY THE
     *                                  CALLER CAUGHT ONLY ONE OF THE TWO** (3930).
     *                                  {@see UserRole::creditGrantCeiling()}
     *                                  declares both and this helper declared one,
     *                                  so the docblock read as a promise the
     *                                  second could not arrive — and Larastan
     *                                  agreed, calling the correct `catch` dead
     *                                  until this line existed.
     */
    private function ceilingAsTyped(CreditProduct $product): ?int
    {
        $ceiling = $this->actorUser()->role->creditGrantCeiling($product);

        if ($ceiling === null) {
            return null;
        }

        // ⚠️ A `match`, NOT AN `===` (3843). A comparison here was the one place on
        // this screen where a new `CreditUnit` case would silently inherit the
        // answer given to `Send`, which is "print the ledger figure as though a
        // person had typed it".
        //
        // ⚠️ **"COMPILE-TIME" IS THE WRONG WORD AND THIS COMMENT USED IT** (3933).
        // `CreditProduct::unit()`'s docblock says *"a fourth product is a
        // compile-time conversation"*; an unmatched enum case is an
        // `UnhandledMatchError` at **run** time, not a compiler error, and PHP has
        // no exhaustiveness check to give one. What the `match` actually buys is
        // that the new case is **loud and immediate** rather than silently wrong —
        // which is the whole of the argument, and is worth saying accurately
        // because a reader who believes the compiler is watching stops looking.
        return match ($product->unit()) {
            CreditUnit::Send => $ceiling,
            CreditUnit::HundredthsOfACent => $product->unit()->toCents($ceiling),
        };
    }

    /**
     * What the confirmation panel says is about to happen, or null when there is
     * nothing coherent to say.
     *
     * ⚠️ **BUILT FROM THE LIVE PROPERTIES EVERY RENDER, NEVER FROM A SNAPSHOT
     * TAKEN WHEN THE PANEL OPENED.** A stored sentence and a stored amount are two
     * things that can disagree, and the one a person reads before clicking "yes"
     * has to be the one the grant will use.
     */
    private function grantConfirmationWording(string $currency): ?string
    {
        $product = CreditProduct::tryFrom($this->grantProduct);
        $typed = (int) $this->grantAmount;

        if (! $product instanceof CreditProduct || $typed < 1) {
            return null;
        }

        return $this->grantPhrase($product, $typed, $currency);
    }

    /**
     * What the operator is told happened.
     *
     * ⛔ **"CREDITS ADDED" IS GONE, AND IT IS THE STRING 3426 MAKES DISHONEST.**
     * It read `'250 credits added to their account.'` — true when this rail could
     * only grant texts, and a sentence that says nothing the moment it can grant
     * three things counted in two units. `22`: every string names what the person
     * controls, and outcome language means naming the outcome and not the
     * mechanism.
     *
     * ⚠️ **THE STAFF VOCABULARY IS `28` §9.1's AND NOT THE TENANT SCREEN'S.**
     * 3551 settled *"what the assistant writes"* for a tenant reading their own
     * balances; an operator's screen says "AI credit", which is what the runbook,
     * the role table and the person on the phone all call it.
     */
    private function grantedWording(CreditProduct $product, string $currency, bool $spendable): string
    {
        $said = $this->grantPhrase($product, (int) $this->grantAmount, $currency);

        if (! $spendable) {
            // ⛔ *WAITING*, NEVER *EXPIRED* (3441, 3926). Nothing has been taken
            // away and the balance is spendable again the moment the plan is, so
            // the word that describes it is the tenant screen's own — and the
            // operator needs it because the thing they have just done does not do
            // what they think it does until the customer resubscribes.
            // ⚠️ NOT "again" (9332): an account whose free trial ran out has
            // never had a plan to start again.
            return $said.' added to their account — waiting until their plan is running.';
        }

        return $said.' added to their account.';
    }

    /**
     * A typed amount, named in the units it was typed in.
     *
     * ⚠️ **ONE PHRASE, TWO READERS, AND BOTH READ IT ABOUT THE SAME FIGURE.** The
     * confirmation panel asks about it and the toast reports it, so a second
     * spelling would be the way a person confirms one sentence and is told a
     * different one — with the AI arm, where the typed figure is cents and the
     * words are money, being exactly where that would go wrong.
     */
    private function grantPhrase(CreditProduct $product, int $typed, string $currency): string
    {
        return match ($product) {
            CreditProduct::Sms => number_format($typed).' text message credits',
            CreditProduct::Email => number_format($typed).' email credits',
            CreditProduct::Ai => PlanPricing::format(Money::of($typed, $currency)).' of AI credit',
        };
    }

    /**
     * The currency platform figures are quoted in, defaulting the way every other
     * reader of this key defaults (`Livewire\Account\Credit` is the sibling).
     */
    private function currency(DefaultsRegistry $registry): string
    {
        $stored = $registry->value('billing.currency');

        return is_string($stored) && $stored !== '' ? $stored : 'USD';
    }

    /**
     * The six balances, in the words an operator uses for them.
     *
     * ⛔ **SIX ROWS AND NO TOTAL ANYWHERE — NOT PER PRODUCT AND NOT ACROSS
     * THEM** (3428, 3315). The two pools are reported separately because they
     * have different lifetimes, and the three products are never added because
     * they are counted in two different units. A screen that helpfully totalled
     * either would render perfectly and mean nothing, which is why the test for
     * this names the sums a tidy version would have shown and asserts their
     * absence.
     *
     * ⛔ **THE POOLS ARE NAMED BY LIFETIME, AND THEY WERE NAMED BY PROVENANCE**
     * (3845). The top-up pool read *"credit they bought"* — and a support `Adjust`
     * lands in it, so the moment an operator granted 750 emails as goodwill the
     * page told the next operator on the ticket that the customer had **paid** for
     * them, which is how a refund gets declined on a falsehood. It is a factual
     * error on a money screen, and `22`'s rule that every string names what the
     * person controls does not survive it. What is true of everything in that pool
     * is its **lifetime**: it does not expire (3307). The monthly pool is named the
     * same way, because "included with their plan" alone never said the thing an
     * operator is actually asking — when it goes away.
     *
     * ⚠️ **AND THE FIX IS NOT TO MOVE `Adjust` INTO THE MONTHLY POOL**, which
     * would make the wording true by giving a goodwill credit an expiry date —
     * contradicting 3307 and 3441 to tidy a caption.
     *
     * ⚠️ **STILL THE OPERATOR'S DIALECT AND NOT THE LEDGER'S** — never "monthly"
     * and "top-up", which are column values. 3551's rule, one screen over.
     *
     * @param  array<string, array{monthly: int, top_up: int}>  $balances
     * @return list<array{product: CreditProduct, heading: string, included: string, bought: string}>
     */
    private function creditCards(array $balances, string $currency): array
    {
        $cards = [];

        foreach (CreditProduct::cases() as $product) {
            $pools = $balances[$product->value] ?? ['monthly' => 0, 'top_up' => 0];

            $cards[] = [
                'product' => $product,
                'heading' => $this->productHeading($product),
                'included' => $this->balanceWording($product, $pools['monthly'], $currency),
                'bought' => $this->balanceWording($product, $pools['top_up'], $currency),
            ];
        }

        return $cards;
    }

    /**
     * A product, as `28` §9.1's audience names it.
     *
     * ⚠️ **NOT `Livewire\Account\Credit`'s HEADINGS.** 3551 chose *"what the
     * assistant writes"* for a tenant looking at their own balance, and it is the
     * right phrase there and the wrong one here: an operator reading a runbook,
     * a role table and a ticket all of which say "AI credit" should not have to
     * translate. The tenant-facing words stay tenant-facing.
     */
    private function productHeading(CreditProduct $product): string
    {
        return match ($product) {
            CreditProduct::Sms => 'Text messages',
            CreditProduct::Email => 'Emails',
            CreditProduct::Ai => 'AI credit',
        };
    }

    /**
     * A balance, in the unit it is counted in.
     *
     * ⛔ **AI IS MONEY AND THE OTHER TWO ARE NOT** (`CreditProduct::unit()`), so
     * there is no shared spelling and no shared number: printing an AI balance as
     * `2,840` puts hundredths of a cent on a screen as though they were a count
     * of something.
     */
    private function balanceWording(CreditProduct $product, int $units, string $currency): string
    {
        return match ($product) {
            CreditProduct::Sms => number_format($units).' '.($units === 1 ? 'text message' : 'text messages'),
            CreditProduct::Email => number_format($units).' '.($units === 1 ? 'email' : 'emails'),
            CreditProduct::Ai => PlanPricing::format(Money::of($product->unit()->toCents($units), $currency)),
        };
    }

    /**
     * What the amount box is counting, said beside the box.
     *
     * ⛔ **THE AI FORM TAKES CENTS AND SAYING SO IS THE WHOLE MITIGATION**
     * (3420). The pool counts hundredths of a cent, so an operator typing `30`
     * into an unlabelled box could be granting thirty cents or thirty hundredths
     * of one, and the difference is a hundredfold on the product with the
     * largest variable cost in the product. The label is not decoration.
     *
     * ⛔ **AND A PRODUCT THIS OPERATOR CANNOT GRANT SAYS SO BEFORE THEY TYPE**
     * (3844). A `support_agent` was offered all three as equals and learned that
     * two of them have no agreed limit only after choosing an amount and writing a
     * reason — the refusal was correct, on the right field, and arrived after the
     * work. `22`: a control that cannot act is marked, in the same shape the
     * impersonation rail already uses for a mode a role may not open.
     *
     * ⚠️ **THE MARK IS NOT THE ENFORCEMENT.** A disabled radio is a browser's
     * opinion; {@see CreditGrants::grant()} refuses the same grant with the
     * component never asked, which is what `CreditGrantsTest` drives.
     *
     * @return list<array{value: string, label: string, hint: string, available: bool}>
     */
    private function grantOptions(): array
    {
        // ⚠️ `actorUser()`, NOT A SECOND `auth()->user()` WITH ITS OWN NULL
        // HANDLING (3930). Every other reader of the signed-in operator on this
        // screen goes through that method, and a second spelling here meant a
        // `null` role rendering three unavailable radios on a page `mount()` has
        // already refused to show — a state nobody can reach, handled anyway, in
        // the one place a reviewer would read as deliberate.
        $role = $this->actorUser()->role;

        $options = [];

        foreach (CreditProduct::cases() as $product) {
            $options[] = [
                'value' => $product->value,
                'label' => $this->productHeading($product),
                'hint' => match ($product) {
                    CreditProduct::Sms => 'Amount is a number of text messages.',
                    CreditProduct::Email => 'Amount is a number of emails.',
                    CreditProduct::Ai => 'Amount is in cents — 100 is one dollar of credit.',
                },
                // ⚠️ NOT AN `&&` CHAIN WHOSE ORDER IS LOad-BEARING. This read
                // `mayGrantCredits() && ceilingIsRuled()`, and removing the first
                // conjunct 500'd `render()` for an `ops_admin` — the availability
                // question is now whole inside `ceilingIsRuled()`, so there is no
                // ordering left to get wrong (3930).
                'available' => $this->ceilingIsRuled($role, $product),
            ];
        }

        return $options;
    }

    /**
     * Whether anybody has ruled what this operator may grant of this product.
     *
     * ⚠️ **THE QUESTION IS ASKED BY CATCHING, BECAUSE REFUSING IS THE ANSWER.**
     * {@see UserRole::creditGrantCeiling()} raises rather than returning a figure
     * nobody has chosen (3639), so "is there one" has no separate predicate — and
     * adding one would be a second place the withheld set is written down, which
     * is what the throwing arm exists to prevent.
     *
     * ⛔ **BOTH REFUSALS, AND IT USED TO CATCH ONE** (3930). That method makes two
     * — `WithheldGrantCeiling` for a figure nobody has ruled, and
     * `InvalidArgumentException` for a role that may not grant at all — and the
     * second was an unhandled 500 in `render()`. It did not fire only because the
     * caller asked `mayGrantCredits()` first, in an `&&` whose **order was
     * load-bearing and undocumented**: removing the first conjunct broke the page
     * for an `ops_admin` rather than merely mismarking a radio. Answering the
     * whole question here leaves no ordering to get wrong.
     *
     * ⚠️ **AND "NO RULED CEILING" IS THE RIGHT ANSWER FOR BOTH.** They are
     * different sentences to an operator and 3840 keeps them apart where that
     * matters — {@see self::grantCredits()} shows two different refusals. Here the
     * question is only *may this radio be pressed*, and it may not, either way.
     */
    private function ceilingIsRuled(UserRole $role, CreditProduct $product): bool
    {
        try {
            $role->creditGrantCeiling($product);
        } catch (WithheldGrantCeiling|InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Run one lifecycle action and say what happened.
     *
     * ⚠️ **THE TENANCY SWITCH IS NOT HERE, AND THAT IS A LINT'S DOING RATHER
     * THAN A PREFERENCE.** The first version of this screen held its own
     * `Tenancy::actingAs()`, and `StaffTest`'s *"the support console reads an
     * account only through the directory"* refused it — correctly, on
     * decision 624's reasoning: a chokepoint weakened for one screen is a
     * security change that looks like a screen. `AccountLifecycle` owns the
     * switch; this owns the words.
     *
     * **The reason field is cleared only on success**, so a refused action is
     * not also a retype. The refusal message is passed in rather than derived,
     * because "say why" means two different things on the two paths and only
     * the caller knows which.
     *
     * @param  callable(int): bool  $action  False when the account is gone.
     */
    private function act(callable $action, string $done, ?string $refusal = null): void
    {
        if ($this->businessId === null) {
            return;
        }

        try {
            $exists = $action($this->businessId);
        } catch (InvalidArgumentException) {
            // The only way in is an empty reason — the cross-tenant guard
            // cannot fire, because AccountLifecycle establishes the tenant from
            // the row it just re-read. Shown on the field it is about; an error
            // with no field is an error people miss.
            $this->addError('lifecycleReason', $refusal ?? 'That could not be done.');

            return;
        }

        if (! $exists) {
            $this->addError('lifecycleReason', 'That account no longer exists.');

            return;
        }

        Toaster::success($done);

        $this->reset(['lifecycleReason', 'confirming']);
    }

    public function start(Impersonation $impersonation, AccountDirectory $accounts): ?Redirector
    {
        $this->authorize(SupportAccess::GATE);

        if ($this->businessId === null) {
            return null;
        }

        // Re-read rather than held from resolve(): an action must not run
        // against a row as it stood in another tab, and this one hands an agent
        // the customer's own session.
        $business = $accounts->business($this->businessId);

        if (! $business instanceof Business) {
            $this->addError('reason', 'That account no longer exists.');

            return null;
        }

        $mode = ImpersonationMode::tryFrom($this->mode);

        if ($mode === null) {
            $this->addError('reason', 'Choose whether you are viewing or making changes.');

            return null;
        }

        $agent = auth()->user();

        if (! $agent instanceof User) {
            // The route is behind `auth`, so this cannot happen through the
            // door. It is checked rather than asserted because the alternative
            // is a TypeError on a security-relevant call.
            return null;
        }

        try {
            $impersonation->start(
                agent: $agent,
                business: $business,
                mode: $mode,
                reason: $this->reason,
                ticketRef: trim($this->ticketRef) === '' ? null : trim($this->ticketRef),
            );
        } catch (ImpersonationRefused $e) {
            // The refusal is written for this reader (see the exception), so it
            // is shown rather than translated. Attached to `reason` because
            // that is the field most refusals are about, and an error with no
            // field is an error people miss.
            $this->addError('reason', $e->getMessage());

            return null;
        }

        // Into the tenant's own account. `/setup` rather than `/` because it is
        // the only authenticated owner-facing surface that exists — when a real
        // owner dashboard lands, this is the line that points at it.
        //
        // ⚠️ AND IT STILL WORKS WHEN THE ACCOUNT IS ON HOLD. Decision 561 makes
        // the acting identity the owner, so `SuspendedTenantStatus` would
        // otherwise show the status page to the one person who has to be able
        // to look — the agent investigating the report the hold was applied for.
        // That middleware exempts an open session by name.
        return $this->redirect(route('setup.index'), navigate: false);
    }

    public function render(AccountDirectory $accounts, DefaultsRegistry $registry): View
    {
        $this->authorize(SupportAccess::GATE);

        $account = $this->businessId === null ? null : $accounts->snapshot($this->businessId);

        return view('livewire.support.accounts', [
            'account' => $account,
            'modes' => ImpersonationMode::cases(),
            // ⚠️ COMPOSED HERE RATHER THAN IN THE TEMPLATE, WHICH IS WHERE THE
            // ONE CONVERSION ON THIS SCREEN LIVES. A Blade file dividing a
            // hundredths balance by a hundred would be a second site for 3331's
            // factor to drift in, in the layer least likely to be reviewed.
            'creditCards' => $account instanceof AccountSnapshot
                ? $this->creditCards($account->creditBalances, $this->currency($registry))
                : [],
            'grantOptions' => $this->grantOptions(),
            // ⚠️ NULL WHENEVER THERE IS NOTHING COHERENT TO ASK ABOUT, SO THE
            // PANEL CANNOT RENDER A HALF-SENTENCE. The confirmation is only ever
            // open with a validated amount behind it, and this is the second
            // reader of the same properties rather than a copy of them.
            'grantConfirmation' => $this->grantConfirming
                ? $this->grantConfirmationWording($this->currency($registry))
                : null,
            'mayAct' => auth()->user()?->role->strongestImpersonationMode() === ImpersonationMode::Act,
            // ⚠️ THE VIEW ASKS THE GATE, NEVER THE ROLE. `28` §9.2's navigation
            // is role-filtered and decision 575's rule is that each item
            // declares its own ability — a template comparing `$user->role`
            // would be a second authorization decision, in the layer least
            // likely to be reviewed. These only decide what is *rendered*; each
            // action re-authorizes on the way in.
            'maySuspend' => auth()->user()?->can(LifecycleAccess::SUSPEND) ?? false,
            'mayPause' => auth()->user()?->can(LifecycleAccess::PAUSE) ?? false,
            'mayGrantCredits' => auth()->user()?->can(CreditGrantAccess::GATE) ?? false,
        ]);
    }

    /**
     * Who is doing this, for the audit entry — one label for the whole screen.
     *
     * ⚠️ **THIS WAS TWO METHODS AND THE READ ONE WAS WRONG.** Reads were filed
     * as `user:{id}` and lifecycle writes as `support:{id}`, so one agent
     * appeared under two labels on one screen. The previous docblock recorded
     * that as an inconsistency to be fixed elsewhere; it is worse than an
     * inconsistency. The two vocabularies distinguish **whose account was acted
     * on** — `user:{id}` means somebody working inside their *own* account, and
     * `AuditExplorer` relies on exactly that to exclude such people from the
     * staff index. The action here is `business.viewed_by_staff`: internal staff
     * opening a customer's account. Filing it as `user:` asserted the one thing
     * that label exists to deny.
     *
     * An actor label rather than a user foreign key, matching every other writer
     * of this log: automation is a first-class actor here, so a nullable user id
     * would model "nobody did this", which is never true.
     *
     * ⚠️ **Rows already filed keep the old label and cannot be repaired** —
     * `audit_log` is append-only, and rewriting a trail to make it tidier is
     * what append-only exists to prevent. {@see StaffActor::labelsFor()} is what
     * a reader uses so those rows are not silently dropped from an answer.
     */
    private function actor(): string
    {
        return StaffActor::internal(auth()->id());
    }

    /**
     * The signed-in operator, for the one caller that needs the role rather
     * than the label — `InternalUsers::actor()`'s reasoning, on the same
     * screen's second form. `CreditGrants::grant()` needs `User` because the
     * ceiling is a fact about the actor's role, not a string {@see self::actor()}
     * already threw away.
     */
    private function actorUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
