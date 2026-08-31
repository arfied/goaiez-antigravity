<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Http\Controllers\Account\CancelSubscriptionController;

/**
 * What a person is told when this platform cannot take their money.
 *
 * ⛔ **FOUR OF THE FIVE SENTENCES THESE REPLACE CLAIMED A NOTIFICATION THAT
 * NEVER HAPPENED — 9297.** *"Our team has been notified"*, *"our team has been
 * told and will finish it for you"*, twice more in the same words, said to a
 * customer at the moment they failed to pay us or failed to stop being charged.
 * **Nothing notified anybody.** `OperatorAlertKind::VendorErrorRate` reads
 * `PlatformHealthSignal::VendorCall`, and the only writer of that signal in the
 * whole tree is the AI router — 9235 records it about Zernio and it is true of
 * both payment gateways as well, so **no billing failure of any kind rings a
 * bell on any deployment**. `VendorLog` writes to the log and nowhere else.
 *
 * ⚠️ **"FOUR OF THE FIVE" ABOVE IS A DIFFERENT FIVE FROM THE ONE
 * {@see self::cannotReachGateway()} IS ABOUT** — this paragraph counts the
 * sentences that falsely claimed a notification; that method counts the
 * copies of the unreachable-gateway sentence. **They overlap in no site and
 * the numbers coinciding is an accident** (12424).
 *
 * ⚠️ **THE SENTENCES LIVE IN ONE PLACE BECAUSE THE CLAIM INSIDE THEM IS ONE
 * CLAIM.** `AuthorizeNetCheckoutController::priceMoved()` made the same choice
 * for the same reason — *"two spellings of the same message would be two things
 * to keep in step for no gain"* — and here the cost of drift is not tidiness: a
 * fifth copy is a fifth chance to promise something this application cannot do.
 * ⛔ **A bell is what would make the old sentence true, and building one is not
 * this lane's** — `OperatorAlertKind` and the alert board belong to two other
 * lanes this wave. It is raised at 9297 and owed.
 *
 * ⚠️ **`22`'s outcome language: each names what the reader can do**, and none
 * invites a retry. Nothing a customer does can paste a credential, and *"try
 * again"* on this fault is the loop `/billing` was in — press, bounce, press.
 *
 * ⛔ **NO PERSONAL DATA AND NO VENDOR TEXT** (104, and both exception classes'
 * rule). These are fixed strings; the classified reason goes to the log.
 */
final class GatewayRefusals
{
    /**
     * The buying doors — `/billing/card`, `/billing/checkout`, and the credit
     * top-up press.
     *
     * ⚠️ **"Nothing has been charged" IS A CLAIM AND IT IS EARNED HERE.** Both
     * gateway clients now refuse *before* a socket is opened
     * ({@see AuthorizeNetApi::isConfigured()}, {@see StripeApi::isConfigured()}),
     * and both typed exceptions carry a `clientRefused` flag that says so, so
     * every caller reaching this sentence knows the request never left. ⛔ It
     * must not be reused for a failure whose outcome is unknown —
     * `Account\Credit` keeps a separate sentence for exactly that case and its
     * docblock is emphatic about why.
     */
    public static function cannotTakePayment(): string
    {
        return 'We cannot take payments just now. Nothing has been charged — reply to '
            .'any email from us and we will sort it out with you.';
    }

    /**
     * Nothing came back from the gateway at all — the one arm where waiting
     * could plausibly change the answer.
     *
     * ⛔ **THE FIFTH COPY, AND IT WAS THE ONE THAT DID NOT MATCH** (12424).
     * `Account\Credit`'s own docblock had recorded this as owed: four sites
     * carried this sentence **verbatim** and a fifth —
     * {@see CancelSubscriptionController} — carried *"We could not reach **your**
     * payment provider just now — please try again in a moment"*. Different
     * pronoun, different punctuation, same fault, same page position.
     * ⚠️ **A lane grepping the exact string finds four and reports the move
     * complete.**
     *
     * ## Why "our", which is a correction and not a merge
     *
     * ⛔ **THE BUYER HAS NO RELATIONSHIP WITH THE THING THAT DID NOT ANSWER.**
     * What is unreachable on this arm is Authorize.Net or Stripe — **our**
     * merchant gateway, on **our** contract. The person pressing the button has
     * never heard of it, and on the statutory cancel path they are ending a
     * subscription *we* hold there. *"Your payment provider"* invites them to go
     * and look at a provider of theirs, which is `22`'s outcome-language rule
     * inverted: the only thing they control on this arm is pressing again, and
     * that is what the second clause names.
     *
     * ✅ **AND THE TREE ALREADY DRAWS THE LINE THIS RESTORES.** Every sentence
     * on a fault at *their* end says so — *"That card was not accepted"*,
     * *"That card could not be accepted"*, *"Your payment provider did not
     * accept that … check the card on your plan"* — and every sentence on a
     * fault at *ours* says "our" or says nothing about whose. **The pronoun is
     * carrying a real distinction**, so the odd copy out was not a stylistic
     * variant; it made the wrong claim about whose the failure was.
     *
     * ## What this does NOT say, and it is the cancel path's question
     *
     * ⚠️ **{@see self::cannotCancel()} PROMISES A RECORD AND THIS DOES NOT**,
     * and both are shown on the same statutory path one flag apart.
     * {@see SubscriptionCancellation::request()} writes
     * `cancellation_requested_at` **before** the vendor call, so the record that
     * they asked survives this arm exactly as it survives that one — the person
     * is simply not told. **This method does not add the clause**: it is
     * customer-facing copy on California's Automatic Renewal Law path, a
     * sentence that ends *"try again in a moment"* and one that ends *"we have
     * recorded that you asked"* invite different next moves, and no lane should
     * pick between them on its own. **Raised at 12425 and owed.**
     */
    public static function cannotReachGateway(): string
    {
        return 'We could not reach our payment provider just now. Please try again in a moment.';
    }

    /**
     * Replacing a card on file, which charges nothing.
     *
     * ⚠️ **A DIFFERENT SENTENCE BECAUSE "nothing has been charged" WOULD BE TRUE
     * AND IRRELEVANT HERE**, and a reassurance nobody asked for reads as one
     * covering something.
     */
    public static function cannotSaveCard(): string
    {
        return 'We cannot save a card just now. Reply to any email from us and we '
            .'will sort it out with you.';
    }

    /**
     * The statutory cancel path, which is the one that answered a 500.
     *
     * ⛔ **IT MAY NOT SAY THE CHARGING HAS STOPPED, AND THE OLD SENTENCE CAME
     * CLOSE.** *"our team has been told and will finish it for you"* promised
     * both a notification that did not happen and a completion nobody was
     * arranging, on California's Automatic Renewal Law path.
     *
     * ✅ **WHAT IT SAYS INSTEAD IS TRUE AND IS THE PART THAT PROTECTS THEM.**
     * {@see SubscriptionCancellation::request()} writes
     * `cancellation_requested_at` **before** the vendor call, so the record that
     * they asked survives the failure — which is the record that matters if the
     * next charge lands anyway, and the controller's own docblock says so.
     */
    public static function cannotCancel(): string
    {
        return 'We could not pass that to your payment provider, and we have recorded '
            .'that you asked to end your plan. Reply to any email from us and we will '
            .'finish it.';
    }
}
