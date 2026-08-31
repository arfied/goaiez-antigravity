<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\CancellationOutcome;
use App\Enums\CardReplacementOffer;
use App\Enums\SubscriptionStatus;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\GatewayRefusals;
use App\Services\Billing\PaymentMethodReplacement;
use App\Services\Billing\PlanCharges;
use App\Services\Billing\SubscriptionCancellation;
use App\Services\Billing\Subscriptions;
use App\Support\CardholderName;
use App\Support\CardNumberShape;
use App\Support\Money;
use App\Support\PlanPricing;
use App\Support\PlanSelection;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use RuntimeException;

/**
 * The owner's plan, and the cancellation this application promised and did not
 * have (2980–2999).
 *
 * ⛔ **`grep -rn "cancel" routes/*.php` RETURNED NOTHING, AND THE SECOND HALF OF
 * THAT FINDING IS THAT `/billing` HAD NO DOOR EITHER.** Every cancellation
 * arrived inbound — `AuthorizeNetWebhooks` projecting a vendor-side end, or
 * `Dunning` exhausting its schedule — while the card form told every buyer *"you
 * can cancel any time"*. And the one screen that showed what we hold,
 * `billing.index`, is reachable only from the post-registration redirect:
 * nothing in `OwnerNav`, nothing under `/account`, no link anywhere in `app/`. A
 * cancellation mechanism nobody can navigate to is what California's Automatic
 * Renewal Law is about, not a polish item.
 *
 * ⚠️ **A SECOND SCREEN RATHER THAN A LINK TO `/billing`, AND THE SPLIT IS THE
 * LIFECYCLE.** `billing/index` and `billing/authorize-net` render in the *setup*
 * shell because "for almost everybody this page IS part of signup" (decision
 * 690) — they are the buying screens. This is where somebody who already bought
 * comes back to, so it renders in the account shell with the navigation on it,
 * the way every other returning-owner screen does. ⚠️ **What must not happen is
 * a third reader of the subscription row growing its own opinion**: this screen
 * asks {@see SubscriptionCancellation::preview()} rather than deciding anything
 * from `status` itself, so the sentence it shows and the outcome the press
 * produces come from one method.
 *
 * ⚠️ **IT WRITES NOTHING, AND THE CANCEL IS A PLAIN `POST` TO A NAMED ROUTE
 * RATHER THAN A LIVEWIRE ACTION.** `TenantExportRequestController`'s reasoning
 * (1900), and it applies here with the same force: `SuspendedTenantStatus` runs
 * on the whole `web` group and exempts by **route name**, and a Livewire button
 * posts to `default-livewire.update` (1997) — a name no exemption can usefully
 * carry, because exempting it exempts every action on every screen. A named POST
 * route is exemptible at exactly the width of the promise. It also means the
 * policy and the form request are exercised by a real HTTP request, where
 * `Livewire::test()` runs no middleware at all (809).
 *
 * ⚠️ **THE POLICY IS ASKED HERE AS WELL AS IN THE FORM REQUEST, AND THAT IS NOT
 * A SECOND GATE.** `CancelSubscriptionRequest::authorize()` is the gate; this
 * read decides whether to *render* the control. A manager who cannot cancel is
 * told who can, rather than shown a button that 403s — 1220's rule that the
 * honest shape for "not yours" is absent rather than disabled.
 *
 * ## ⛔ AND UNTIL 4840 IT NEVER NAMED A PRICE (3536, 4665)
 *
 * The blade beside this file has said since 4301 that *"'What am I paying?' is
 * this page"*, and the page carried no figure of any kind — not the plan price,
 * not the payment schedule, not what an extra location on the account costs. 3536
 * named *"an account billing page, an invoice line or a receipt"* as the next
 * thing owed and W6 verified on 2026-08-17 that none of the three existed in
 * `app/`. This is the first of them: a tenant can now read what they are paying
 * on the screen that already claimed to tell them.
 *
 * ⛔ **EVERY FIGURE HERE IS `agreedPriceFor()` / `agreedPaymentsFor()` /
 * `agreedAdditionalLocationPriceFor()`, WHICH READ THE ROW.** This screen quotes
 * somebody who has **already bought**, so 3535's split puts it wholly on the
 * `agreed*` side and 3443 is why: the day a price moves, the registry figure and
 * the gateway's charge diverge for every existing customer at once, and a billing
 * page quoting the registry would show a number the card is not charged. It is
 * enumerated by name in `BillingTest`'s *"a surface quoting an existing
 * subscription reads the agreed price, never a live one"* (4662) — by hand,
 * because there is no way to derive that property from a file's namespace.
 *
 * ⚠️ **THE PAYMENT COUNT IS NEVER A WORD IN THE TEMPLATE.** The retail annual is
 * three payments and the founder annual is two (2092, 2754), the count lives on
 * the subscription row, and 4643's defect was a card page that said "three"
 * unconditionally. The template renders `count($payments)` and iterates; there is
 * no branch on a number anywhere in it.
 *
 * ## ⛔ AND UNTIL 6500 A TENANT IN DUNNING HAD NOWHERE TO PUT A NEW CARD
 *
 * 6404 is the finding: `AuthorizeNetGateway::replacePaymentMethod()` calls
 * itself *"the dunning remedy, and the only recovery that actually works on this
 * gateway"* and had **no caller anywhere in `app/`**, while `PaymentFailed`
 * started telling tenants their plan was about to end. **And the obvious button
 * was worse than none**: `billing.card` is the signup screen, its POST ends in
 * `AuthorizeNetGateway::subscribe()`, and that method throws outright for a
 * business already holding a subscription id — which is every business a dunning
 * notice reaches. It would have rendered, been pressed by somebody trying to save
 * their plan, and 500d.
 *
 * ⛔ **THE FORM IS HERE RATHER THAN ON A SECOND SCREEN, AND THE CARD STILL NEVER
 * TOUCHES THIS APPLICATION.** Accept.js posts the card straight to the vendor
 * from the browser and hands back an opaque nonce; the card inputs carry `data-`
 * attributes and **no `name`**, so they are not serialised by anything, and the
 * only value that reaches PHP is the nonce. SAQ-A is preserved on this path
 * exactly as it is at signup (2056) — `billing/authorize-net.blade.php` carries
 * the same reasoning from the other side.
 *
 * ⚠️ **A LIVEWIRE ACTION RATHER THAN A NAMED POST, WHICH IS THE OPPOSITE OF THE
 * CANCEL CONTROL TEN LINES AWAY, AND IT IS ARGUED RATHER THAN COPIED.**
 * `account.plan.cancel` is a named route because `SuspendedTenantStatus` exempts
 * by route name and `TenantSuspension::suspend()` leaves the billing running — so
 * a suspended tenant must be able to stop paying. **The card is the other
 * direction**: `28` §9.5 stops that account, and `/account/credit`'s route
 * comment already settles the shape for it — *"a tenant on hold has no business
 * spending more money with us, and the middleware sending them to the on-hold
 * page is the correct answer here rather than an exemption"*. So the form is not
 * rendered for a suspended tenant at all ({@see CardReplacementOffer::AccountOnHold}),
 * which is 1220's absent-rather-than-disabled rather than a dead button.
 *
 * ⚠️ **AND THE DUNNING POPULATION IS NOT THE SUSPENDED ONE.**
 * `Subscriptions::suspendForNonPayment()` writes a subscription status and never
 * `businesses.suspended_at`, so a tenant three failed payments deep reaches this
 * screen through the ordinary `auth` middleware and their press lands normally.
 * Conflating the two is the mistake that would have made this feature inert for
 * the only people who need it.
 *
 * ⚠️ **NO POLICY ABILITY, AND THAT IS THIS FILE'S EXISTING POSITION RATHER THAN
 * A GAP.** `SubscriptionPolicy`'s own docblock says `/billing/card` sitting
 * behind `auth` alone *"is defensible for adding a card — a staff user who does
 * that has spent nobody's money"*, and reserves the owner-only gate for ending
 * the plan, which cannot be undone. Replacing a card is the first of those and
 * not the second: it settles a debt that is already owed and takes nothing away.
 * ⚠️ **What is refused is a support session** — `ImpersonationCapability::ManagePaymentMethods`,
 * inside {@see PaymentMethodReplacement::replace()}, because an agent typing a
 * customer's card over the phone is a MOTO transaction and SAQ-A does not cover
 * one.
 */
#[Layout('components.account.layout')]
final class Plan extends Component
{
    /**
     * What the last card attempt has to say for itself, or null.
     *
     * ⚠️ **A PANEL AND NOT ONLY A TOAST, BECAUSE THE PERSON IS MID-TASK.** A
     * toast is right for *"that worked, carry on"* and wrong for *"try a
     * different card"* — the second is an instruction somebody has to act on
     * with the form still in front of them, and `masmerise/livewire-toaster`
     * fades. The success case is a toast; every refusal is this.
     *
     * ⚠️ **`#[Locked]` THOUGH IT NAMES NO RECORD.** The brief's rule is about
     * properties naming a record and this is not one — it is locked anyway
     * because it is *our* sentence about somebody's money, and an unlocked
     * property lets the page it is rendered on decide what this application just
     * said about a payment.
     */
    #[Locked]
    public ?string $cardMessage = null;

    public function render(
        Subscriptions $subscriptions,
        SubscriptionCancellation $cancellation,
        PlanCharges $charges,
        PaymentMethodReplacement $replacements,
    ): View {
        $business = $this->business();
        $subscription = $subscriptions->for($business);
        $outcome = $cancellation->preview($business);

        return view('livewire.account.plan', [
            'subscription' => $subscription,

            // ⛔ EVERY FIGURE ON THE PAGE, RESOLVED IN ONE PLACE. Spread rather
            // than listed here because "is there a plan to price" is one question
            // and six answers hang off it — asking it six times is how five of
            // them stay right and the sixth renders a zero.
            ...$this->pricePanel($charges, $subscription, $outcome),

            // ⛔ THE SAME SHAPE FOR THE SAME REASON — one question about the
            // card, five answers hanging off it (6500).
            ...$this->cardPanel($replacements, $business, $subscription),

            // ⚠️ THE PREVIEW, NEVER A SENTENCE ASSEMBLED HERE. It is the same
            // method the press runs through, so the screen's promise and the
            // outcome cannot come apart — and its own docblock states the one
            // direction it can be wrong in, which is a difference that costs the
            // reader nothing either way.
            'outcome' => $outcome,

            // ⚠️ A REQUEST THAT HAS NOT BEEN ANSWERED YET IS NOT A CANCELLED
            // SUBSCRIPTION (2056), so this renders as "we have asked" and never
            // as the state. `status` still moves only on a verified webhook.
            'requestedAt' => $subscription?->cancellation_requested_at,

            'mayCancel' => Gate::allows('cancel', Subscription::class),

            // ⛔ THE SCREEN CALLED "Your plan" SHOWED A TRIAL OWNER NOTHING AT
            // ALL, AND IT WAS THE FOURTH SURFACE NOBODY HAD COUNTED (9400).
            // Every date on this page comes from `ends_at`, `current_period_end`
            // or `trial_ends_at`, and a `pending_checkout` row carries none of
            // the three — `Subscriptions` states that as a checked property, and
            // both `trial_ends_at` writers require a card. So the one population
            // whose product is about to stop read *"You do not have a paid plan
            // on this account"* and not one word about the clock they were on.
            //
            // ⚠️ THE SAME TWO METHODS `/account/credit` ASKS, WITH THE ROW
            // ALREADY IN HAND (9332). One clock, one pair of answers, two
            // screens — a second derivation here is how the two would come to
            // disagree about the day somebody's service stops.
            'trialEndsOn' => $subscriptions
                ->noCardTrialEndsAt($business, $subscription)
                ?->translatedFormat('j F Y'),
            'trialHasEnded' => $subscriptions->noCardTrialHasEnded($business, $subscription),
        ]);
    }

    /**
     * Move this plan onto a new card — 6404's missing caller (6500).
     *
     * ⛔ **IT REACHES `replacePaymentMethod()` AND NEVER `subscribe()`, AND THAT
     * IS THE WHOLE POINT OF THE SLICE.** `subscribe()` throws for a business
     * already holding a subscription id, which is every business in dunning, so
     * a "helpful" edit pointing this at the checkout gateway method turns the one
     * recovery path into a 500 for exactly the population it was built for.
     * `PlanScreenCardTest`'s *"the press reaches replacePaymentMethod and never
     * subscribe"* fails the build on it.
     *
     * ⚠️ **THE NONCE ARRIVES AS AN ARGUMENT AND IS NEVER A COMPONENT PROPERTY.**
     * Accept.js hands it to the browser, the browser calls this method with it,
     * and it dies here — so it never enters the component snapshot, never
     * round-trips back to the page, and is never a value `#[Locked]` has to hold.
     * ⚠️ **It is not a card number and there is no parameter here for one**:
     * see the class docblock and the template.
     *
     * ⛔ **AND SINCE 2026-08-29 IT TAKES THE NAME ON THE CARD FOR THE SAME
     * REASON THE SIGNUP FORM DOES.** Both surfaces reach a profile **creator**
     * at the vendor, and a payment profile created with no `billTo` is what
     * `E00014` refuses — the fault that meant no tenant had ever completed a
     * checkout. ⚠️ **The two names are arguments for the nonce's reason**: they
     * are a natural person's legal name, they die in this method, and a
     * component property would round-trip them back into the page on every
     * subsequent render. ⚠️ **They are the CARDHOLDER's** and are not derived
     * from `users.name`: the person pressing this button is not always the
     * person whose card it is.
     *
     * ⚠️ **NO SECOND OPINION ABOUT ELIGIBILITY LIVES HERE** (`CLAUDE.md` 398).
     * The service is the only guard; this method renders whatever it answers.
     * Re-asking `offer()` before the call would make that guard unfalsifiable —
     * deleting it would leave the suite green and the vendor holding a request
     * for a subscription that has ended.
     */
    public function replaceCard(
        PaymentMethodReplacement $replacements,
        string $nonce,
        string $firstName,
        string $lastName,
    ): void {
        $this->cardMessage = null;

        // ⚠️ **AN INLINE GUARD RATHER THAN `$this->validate()`, BECAUSE THE
        // SUBJECT IS AN ARGUMENT AND NOT A PROPERTY.** Livewire's `validate()`
        // validates the component's own state; the nonce is deliberately never
        // component state (see this method's docblock), so there is nothing for
        // it to look at. A form request cannot reach here either — there is no
        // request of our own to attach one to. ⛔ **The bound is on length and
        // never on shape**: the vendor documents no format for `dataValue`, and a
        // regex guessed from one sandbox response is the kind of plausible line
        // that fails silently the day they lengthen it.
        if (trim($nonce) === '' || mb_strlen($nonce) > 2048) {
            $this->cardMessage = 'We did not get the card details. Please try again.';

            return;
        }

        // ⛔ **THE NAME ON THE CARD, AND THE VENDOR WILL NOT TAKE A PAYMENT
        // PROFILE WITHOUT IT.** `PaymentMethodReplacement::replace()` creates a
        // **new** payment profile at the vendor, and a payment profile with no
        // `billTo` is what an `ARBCreateSubscriptionRequest` refuses with
        // `E00014` — the defect that meant no tenant could ever subscribe. This
        // surface is not the signup form and the population here is somebody in
        // dunning, so the failure to avoid is the one that mints a stored card
        // attached to nothing.
        //
        // ⚠️ **THREE GUARDS RATHER THAN ONE, BECAUSE THEY ARE THREE DIFFERENT
        // THINGS TO DO ABOUT IT** — type the name, shorten it, or refresh the
        // page because something put a card number in a name box.
        // {@see CardholderName} refuses all three again at the boundary; these
        // exist so the person is told which.
        if (trim($firstName) === '' || trim($lastName) === '') {
            $this->cardMessage = 'We also need the first and last name on the card. Please add them and try again.';

            return;
        }

        if (mb_strlen(trim($firstName)) > CardholderName::MAX_LENGTH
            || mb_strlen(trim($lastName)) > CardholderName::MAX_LENGTH) {
            $this->cardMessage = 'Your payment provider takes up to '.CardholderName::MAX_LENGTH
                .' characters for each half of the name on the card. Please shorten it and try again.';

            return;
        }

        // ⛔ **THE AUTOFILL TRIPWIRE, ON THE SURFACE THAT HAS NEVER HAD ONE.**
        // These two inputs are the only ones on this panel that are sent to us
        // at all, and they sit in the browser's **card** autofill group beside
        // `cc-number` — so a mis-mapping browser, extension or password manager
        // puts a PAN in one of them and nothing else here would look different.
        // ⚠️ **THE VALUE IS NOT LOGGED, NOT EVEN ITS LENGTH**, which is
        // `AuthorizeNetCheckoutRequest`'s rule for the same tripwire on the
        // other page; what is worth saying is that it happened.
        if (CardNumberShape::looksLikeOne(trim($firstName)) || CardNumberShape::looksLikeOne(trim($lastName))) {
            Log::error('a card-shaped value reached a cardholder name field on the plan screen');

            $this->cardMessage = 'We could not process that securely. Please refresh the page and try again.';

            return;
        }

        $user = Auth::user();

        // The route sits behind `auth`, so this cannot happen — asserted rather
        // than defaulted because the actor is what the audit entry is *for*, and
        // `CancelSubscriptionController` makes the same assertion for the same
        // reason: a record of a sensitive act with no actor on it is a record of
        // nothing.
        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $offer = $replacements->replace(
                $this->business(),
                $nonce,
                CardholderName::fromInput($firstName, $lastName),
                'user:'.$user->getKey(),
            );
        } catch (ImpersonationRefused $refusal) {
            // `28` §9.4's blocklist as a sentence an agent can act on, rather
            // than the 500 an unhandled exception would be. It is shown in the
            // panel because the agent is the one reading it.
            $this->cardMessage = $refusal->getMessage();

            return;
        } catch (AuthorizeNetRequestFailed $failure) {
            // ⚠️ THE CLASSIFIED REASON, NEVER THE VENDOR'S MESSAGE, AND NEVER
            // THE EXCEPTION OR ITS TRACE. `AuthorizeNetRequestFailed`'s own
            // docblock: the `text` field is written for a person and routinely
            // quotes the value it rejected. The nonce is an argument three frames
            // up, so logging a throwable here would put it in the log.
            Log::warning('a card replacement was refused by the gateway', [
                'business_id' => $this->business()->id,
                'reason' => $failure->reason,
                'configuration' => $failure->configuration,
                // ⚠️ A DIFFERENT NEXT MOVE FOR WHOEVER READS THIS: a credential
                // is pasted, a malformed request is fixed. See
                // `AuthorizeNetRequestFailed`.
                'malformed_request' => $failure->malformedRequest,
            ]);

            $this->cardMessage = self::vendorRefusal($failure);

            return;
        } catch (RuntimeException $e) {
            // A shape neither the service nor the vendor documents. The person
            // can act on none of it, so they get one sentence and a person to
            // ask, and the log line is what says which.
            Log::warning('a card replacement could not be completed', [
                'business_id' => $this->business()->id,
                'reason' => $e->getMessage(),
            ]);

            $this->cardMessage = 'We could not save that card. Reply to any email from us and we will sort it out with you.';

            return;
        }

        if ($offer === CardReplacementOffer::Available) {
            // ⚠️ "WE HAVE GIVEN IT TO THEM", NOT "YOU ARE PAID UP" (2056). The
            // row moves when a verified notification says so and at no other
            // moment, and this vendor decides *when* it retries — see
            // `PaymentMethodReplacement`'s note on Automatic Retry. A toast
            // saying the payment had gone through would be a claim nothing here
            // can make.
            Toaster::success('Saved — your plan will be paid from the new card');

            return;
        }

        // ⚠️ THE SERVICE'S OWN ANSWER, RENDERED. Nothing was sent to the vendor
        // and nothing was created; the sentence names which of the four it was
        // so the person is not told to "try again" against a subscription that
        // has ended.
        $this->cardMessage = self::refusalFor($offer);
    }

    /**
     * What a person is told when the vendor says no.
     *
     * ⚠️ **THREE SENTENCES, NOT ONE, AND THE MIDDLE ONE IS THE ONE THAT MATTERS.**
     * `E00039` is *"a duplicate record already exists"* — on
     * `createCustomerPaymentProfileRequest` it means the card just entered is
     * already on file, which in a dunning recovery is the **most likely** wrong
     * turn: somebody whose payment failed for want of funds tops up and re-enters
     * the same card. The generic *"check the details or try another card"* is
     * actively misleading there, because the details are fine.
     *
     * ⛔ **AND THE BETTER FIX IS NOT MADE HERE — IT IS OWED (6509).** The vendor
     * returns the existing `customerPaymentProfileId` on that very error
     * (`createCustomerPaymentProfileResponse`, XSD read 2026-08-21: *"will only
     * be present if a payment profile was created **or a duplicate payment
     * profile was found**"*), so the honest behaviour is to point the
     * subscription at the profile we already hold. `AuthorizeNetApi::assertOk()`
     * throws before any caller can read that body, and widening it is a change to
     * the client's contract that wants its own slice and a real vendor response
     * to test against.
     */
    private static function vendorRefusal(AuthorizeNetRequestFailed $failure): string
    {
        if ($failure->configuration) {
            // An operator has to paste a credential. Telling this person to try
            // again would send them round a loop nothing they do can break.
            //
            // ⛔ **AND IT SAID *"Our team has been told"*, WHICH WAS FALSE —
            // 9297.** Nothing on either payment gateway writes
            // `PlatformHealthSignal::VendorCall`, the signal
            // `OperatorAlertKind::VendorErrorRate` reads (9235), so no billing
            // failure of any kind rings a bell. ⚠️ **This was the true-sibling
            // tell working in reverse**: the `configuration` branch itself is
            // right, was right first, and is cited by two other artefacts as
            // the paragraph to copy — which is what made the false clause
            // inside it read as considered.
            return GatewayRefusals::cannotSaveCard();
        }

        // ⛔ **THE VENDOR READ OUR REQUEST AND SAID IT WAS WRONG, WHICH IS NOT
        // THIS PERSON'S CARD.** `E00014` and its siblings reached the last line
        // of this method and told a tenant in dunning to try a different card —
        // the population least able to afford being sent round that loop. The
        // sentence is the credential one because their options are identical;
        // the difference is in the log line and in `AuthorizeNetRequestFailed`.
        if ($failure->malformedRequest) {
            return GatewayRefusals::cannotSaveCard();
        }

        if ($failure->reason === 'E00039') {
            return 'That is the card we already have on file. To fix the payment, use a different card.';
        }

        return $failure->retryable
            ? GatewayRefusals::cannotReachGateway()
            : 'That card was not accepted. Please check the details or try a different card.';
    }

    /**
     * The sentence for an offer that is not a form.
     *
     * ⚠️ **REACHED ONLY WHEN THE PRESS AND THE RENDER DISAGREE**, which is a real
     * state rather than a defensive one: the render reads our own row and the
     * press reads the vendor, so a subscription terminated since the page was
     * drawn arrives here. The panel copy for the same cases lives in the
     * template; this is the same information for somebody who has already
     * pressed, which is why it is phrased as *what just happened* rather than as
     * *what you may do*.
     */
    private static function refusalFor(CardReplacementOffer $offer): string
    {
        return match ($offer) {
            // Unreachable — `replaceCard()` returns before this on that arm.
            // Stated rather than defaulted so a seventh case goes red here.
            CardReplacementOffer::Available => 'Saved — your plan will be paid from the new card.',
            CardReplacementOffer::AccountOnHold => 'Your account is on hold, so we cannot take a new card here. Reply to any email from us and we will sort it out with you.',
            CardReplacementOffer::PlanHasEnded => 'Your plan has already ended, so nothing was charged and there is nothing to pay. Ask us and we will start it again for you.',
            CardReplacementOffer::NothingIsDue => 'Every payment for your plan has already been taken, so there is nothing to pay and nothing was charged.',
            CardReplacementOffer::NoPlanToPayFor => 'There is no paid plan on this account, so there is nothing to put a card against.',
            CardReplacementOffer::HandledByStripe => 'The card for this plan is held by a different payment provider. Ask us and we will change it with you.',
        };
    }

    /**
     * What the card panel may offer, and what the browser needs to offer it.
     *
     * ⛔ **ONE GATE AND FIVE ANSWERS, `pricePanel()`'s RULE.** "Is there a card to
     * replace" is one question, and asking it per key is how four of them stay
     * right and the fifth renders a form nobody can submit.
     *
     * ⚠️ **`PlatformCredentials::has()` RATHER THAN `get()`, AND THE DIFFERENCE
     * IS A 500 ON A PAGE PEOPLE LAND ON TO READ.** `get()` throws when a
     * credential is unset, which is a real deployment state — production has run
     * with `MAIL_MAILER=log` for weeks and nothing says the Authorize.Net keys
     * are pasted either. A missing key must degrade to *"ask us"*, never to a
     * stack trace on the billing page (`29` §2 rule 43's surviving half).
     *
     * ⚠️ **SANDBOX AND PRODUCTION ARE DIFFERENT SCRIPT HOSTS ON THIS VENDOR** —
     * `jstest.authorize.net` against `js.authorize.net`, where Stripe
     * distinguishes modes by the key alone. Loading the wrong one fails in the
     * browser with a message about authentication, which reads as a bad key.
     * `AuthorizeNetCheckoutController::create()` picks it the same way; the two
     * are deliberately identical rather than shared, because a helper here would
     * be a third place the environment is interpreted.
     *
     * ⚠️ **`paymentIsOutstanding` IS `past_due` AND NOTHING SOFTER.** That status
     * is written by exactly one thing — `AuthorizeNetWebhooks::openDunning()`,
     * the same event that starts the dunning schedule — so it means *a payment
     * has actually failed* rather than *something might be wrong*. The panel
     * needs it because the two populations are owed different sentences: one is
     * fixing a failure and one is changing a card before it expires, and a single
     * wording would alarm the second or understate the first.
     *
     * @return array{
     *     cardOffer: CardReplacementOffer,
     *     cardFormReady: bool,
     *     paymentIsOutstanding: bool,
     *     acceptJsUrl: string,
     *     acceptApiLoginId: ?string,
     *     acceptClientKey: ?string,
     * }
     */
    private function cardPanel(
        PaymentMethodReplacement $replacements,
        Business $business,
        ?Subscription $subscription,
    ): array {
        $offer = $replacements->offer($business);

        // ⛔ **THIS ASKED FOR THE TWO ACCEPT.JS KEYS AND NOT FOR THE ONE THAT
        // MOVES MONEY** (9295). The panel it draws posts a nonce to
        // `PaymentMethodReplacement::replace()`, which reaches
        // {@see AuthorizeNetApi::send()} — signed with the **transaction key**,
        // which nothing in this application guarded anywhere. So with the two
        // pasted and the third missed, this panel rendered, a real card was
        // tokenised, and the exchange behind it failed. The question is derived
        // now rather than typed here, so a door's key set cannot be a subset of
        // the call's.
        $hasCredentials = AuthorizeNetApi::checkoutIsConfigured();

        return [
            'cardOffer' => $offer,
            'cardFormReady' => $offer->offersAForm() && $hasCredentials,
            'paymentIsOutstanding' => $subscription?->status === SubscriptionStatus::PastDue,
            'acceptJsUrl' => config('services.authorizenet.environment') === 'production'
                ? 'https://js.authorize.net/v1/Accept.js'
                : 'https://jstest.authorize.net/v1/Accept.js',
            'acceptApiLoginId' => $hasCredentials ? PlatformCredentials::get(AuthorizeNetApi::API_LOGIN_ID) : null,
            'acceptClientKey' => $hasCredentials ? PlatformCredentials::get(AuthorizeNetApi::PUBLIC_CLIENT_KEY) : null,
        ];
    }

    /**
     * What the plan costs, in the shape the template renders it.
     *
     * ⚠️ **A PRICE IS RENDERED ONLY WHERE THERE IS A PLAN TO PRICE, AND THE
     * QUESTION IS ASKED OF `preview()` RATHER THAN OF `status`.** That method is
     * already the one thing on this screen allowed an opinion about the
     * subscription row; a second reader growing its own view of what "on a plan"
     * means is what this class' docblock refuses. `agreedPriceFor()` would answer
     * happily for a `pending_checkout` row — it falls back to the registry — so
     * without this gate the screen would quote somebody a price for a plan they
     * never bought.
     *
     * ⛔ **ONE GATE AND SIX ANSWERS, NOT SIX GATES.** Asking the question per key
     * is how five of them stay right and the sixth renders a zero on a page about
     * somebody's bill.
     *
     * @return array{
     *     agreedPrice: ?string,
     *     payments: list<string>,
     *     extraLocations: int,
     *     extraLocationPrice: ?string,
     *     termCadence: ?string,
     *     priceIsAgreed: bool,
     * }
     */
    private function pricePanel(
        PlanCharges $charges,
        ?Subscription $subscription,
        CancellationOutcome $outcome,
    ): array {
        if (! $subscription instanceof Subscription || ! $outcome->impliesALivePlan()) {
            return [
                'agreedPrice' => null,
                'payments' => [],
                'extraLocations' => 0,
                'extraLocationPrice' => null,
                'termCadence' => null,
                'priceIsAgreed' => false,
            ];
        }

        // The tenant's own term, which is what an add-on would be quoted on —
        // `Account\Locations`' idiom, and the reason both screens agree.
        $selection = PlanSelection::fromInput($subscription->term?->value, false);

        return [
            // ⛔ THE ROW'S PRICE, NEVER THE REGISTRY'S (3443, 3444). See the class
            // docblock.
            'agreedPrice' => PlanPricing::format($charges->agreedPriceFor($subscription)),

            // The schedule that price is collected in, already formatted. Empty
            // for anything taken in one payment, which is every monthly plan and
            // every annual one not sold in instalments.
            'payments' => $this->instalments($charges, $subscription),

            // ⚠️ WHAT THE TOTAL ABOVE ALREADY INCLUDES. `agreedPriceFor()` adds
            // the extra locations on, so a total rendered without saying how many
            // are in it is the plausible-number failure this project keeps
            // meeting. Zero on every subscription either checkout can sell today;
            // non-zero is reachable through the operator screen (4347, 4642).
            'extraLocations' => $subscription->additional_locations ?? 0,

            'extraLocationPrice' => PlanPricing::format(
                $charges->agreedAdditionalLocationPriceFor($subscription, $selection),
            ),

            // "a month" / "a year", from the enum rather than from the template
            // (512's rule applied to words). `null` on a row with no term at all
            // prints the amount and no cadence, which is the honest shape: a row
            // that reached this panel with no term would otherwise be told it pays
            // monthly because that is the default a checkout applies, and nobody
            // chose it.
            'termCadence' => $subscription->term?->cadenceLabel(),

            // ⛔ WHETHER THE ROW ITSELF CARRIES THE PRICE, WHICH IS WHAT LICENSES
            // THE GRANDFATHERING SENTENCE. `agreedPriceFor()` falls back to the
            // registry for a row written before the columns existed (3530–3534),
            // and on that arm the figure is *today's* — so a page that said "this
            // is the price you signed up at" would make 3444's exact false
            // statement to that bounded population the first time a price moves.
            // The amount still renders; only the promise is withheld.
            'priceIsAgreed' => $subscription->price_cents !== null,
        ];
    }

    /**
     * The payments this plan is collected in, formatted — or nothing to show.
     *
     * ⛔ **AN EMPTY LIST FOR A SINGLE PAYMENT, RATHER THAN A LIST OF ONE.** A
     * monthly plan is *not* an instalment plan and a breakdown reading "Payment 1
     * — $179.99" under a heading that already says $179.99 a month is a second
     * copy of one figure, which is decision 754's trap: whichever is wrong is
     * invisible. The template renders the section only when this is non-empty, so
     * "is there a schedule" is decided once, here.
     *
     * ⚠️ **`agreedPaymentsFor()`, WHICH READS `subscriptions.instalment_payments`.**
     * `PlanCharges::paymentsFor()` would have asked the live offer for both the
     * amounts and the count — three payments for a founder tenant the day the
     * window closed, two for a retail tenant the day one opened — and the lint
     * that guards this file cannot see that call, because it happens inside
     * `PlanCharges`. See that method's docblock (4840).
     *
     * @return list<string>
     */
    private function instalments(PlanCharges $charges, Subscription $subscription): array
    {
        $payments = $charges->agreedPaymentsFor($subscription);

        if (count($payments) < 2) {
            return [];
        }

        return array_map(
            static fn (Money $amount): string => PlanPricing::format($amount),
            $payments,
        );
    }

    /**
     * ⚠️ 403 RATHER THAN LETTING `Tenancy::idOrFail()` THROW — `Account\Settings`'
     * reasoning. Internal staff belong to no business, so a signed-in support
     * agent typing this URL is the ordinary way to arrive with nothing resolved,
     * and a 500 reads as our page being broken.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        $business = Business::query()->find($id);

        abort_if(! $business instanceof Business, 404);

        return $business;
    }
}
