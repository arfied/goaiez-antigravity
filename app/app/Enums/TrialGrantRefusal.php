<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Support\CreditGrants;

/**
 * Why an account may not receive the no-card trial's SMS allowance.
 *
 * ⚠️ **EVERY CASE WITHHOLDS AN ALLOWANCE. NONE OF THEM STOPS A SIGNUP, PAUSES AN
 * ACCOUNT, OR TOUCHES A SEND.** That separation is decision 2066's own — the
 * trial is fourteen days of the product and costs almost nothing; the fraud
 * surface is the 500 real, billable SMS (2060). A control that refused
 * registration on any of these signals would lock a co-working space out of the
 * product on the strength of a shared network address, which is a support ticket
 * with no good answer. Withholding an allowance is recoverable in one line by a
 * support operator; a refused registration is not recoverable at all.
 *
 * ⛔ **AND NOTHING HERE MAY EVER BE CONSULTED BY A CONSENT OR SUPPRESSION PATH**
 * (2066: "suppression and consent unchanged"). STOP, HELP, the Do Not Call
 * register and every recorded sending basis behave identically for a refused
 * account and an eligible one, because eligibility is about *funding* a balance
 * and consent is about *permission to send*. `TrialAbuseControlsTest` holds a
 * lint on that boundary in the direction that can actually fail: no file under
 * the consent, compliance, messaging, SMS, reviews, campaigns or mail services —
 * nor under `app/Jobs` — may reference `TrialEligibility`.
 *
 * The strings are persisted nowhere. They exist so that a refusal reads the same
 * in a log line, a test and the support console.
 */
enum TrialGrantRefusal: string
{
    /**
     * Nothing has verified this account as a business at all.
     *
     * ⚠️ **THIS IS THE REFUSAL A HONEST READING OF 2066 FORCES, AND IT IS THE ONE
     * MOST WORTH ARGUING ABOUT.** "One trial per **verified** business" cannot be
     * enforced when nothing verifies a business, and the alternative — grant to
     * anyone who can type an email address — is precisely the surface 2066 names.
     * The one verification this codebase performs is a confirmed Google listing
     * ({@see TrialClaimKind::GoogleListing}), so that is what "verified" means
     * here until something stronger exists.
     *
     * ✅ **IT COSTS A LEGITIMATE TENANT NOTHING THEY WERE NOT ALREADY DOING.**
     * Review invitations are the allowance's main consumer and they cannot be
     * sent to Google without a confirmed listing at all — `review_destinations`
     * seeds Google *disabled* and `PlaceConfirmation` is the only thing that
     * enables it (312). So the step this refusal waits for is a step the product
     * already requires.
     *
     * ⚠️ **IT IS ALSO THE ONE CASE HERE THE OWNER MAY SIMPLY DISAGREE WITH**, and
     * reversing it is a one-line change with the rest of the controls intact —
     * see decision 3223.
     */
    case NoConfirmedListing = 'no_confirmed_listing';

    /**
     * Another account already claimed one of this account's listings.
     *
     * The literal reading of "one trial per verified business": a Google place id
     * names one physical business, so the second account to claim it is not a
     * second business. **The first claimant keeps the entitlement** — a rule that
     * punished both would let anyone burn a competitor's allowance by confirming
     * their listing.
     *
     * ⚠️ **A LEGITIMATE CASE LANDS HERE AND HAS TO BE RECOVERABLE**: an owner who
     * abandons an account and signs up again, or a business sold to a new owner.
     * Both are a support conversation and a manual credit, which already exists
     * ({@see CreditGrants}) — which is why the verdict is
     * surfaced on the support console rather than only thrown.
     */
    case ListingClaimedByAnother = 'listing_claimed_by_another';

    /**
     * Too many accounts registered from this network in one window.
     *
     * The weakest of the three and the only one that can be wrong about an
     * innocent account, so it is sized to be wrong rarely rather than to catch
     * everything — see `TrialEligibility::SIGNUP_ORIGIN_ACCOUNT_LIMIT` for the
     * numbers and for who owns them.
     */
    case SignupOriginVelocity = 'signup_origin_velocity';

    /**
     * One sentence for the person on Account 360, in plain words.
     *
     * ⚠️ **WRITTEN FOR AN OPERATOR AND NEVER FOR A CUSTOMER**, on
     * `CreditMovementRefused`' rule: these name internal fraud signals, and the
     * account holder is shown none of them. The support console is the only
     * surface that renders one.
     *
     * ⚠️ **EACH SAYS WHAT WOULD CLEAR IT.** An agent reading "not eligible" has
     * to go and ask somebody; an agent reading "they have not confirmed a Google
     * listing yet" can finish the call.
     */
    public function forOperator(): string
    {
        return match ($this) {
            self::NoConfirmedListing => 'No Google listing confirmed yet, so nothing has verified this business.',
            self::ListingClaimedByAnother => 'Another account confirmed this Google listing first.',
            self::SignupOriginVelocity => 'Several accounts registered from the same network at about the same time.',
        };
    }
}
