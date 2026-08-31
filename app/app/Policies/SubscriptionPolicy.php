<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may end the business's plan (2980–2999).
 *
 * ⚠️ **THE FIRST AUTHORIZATION QUESTION THE BILLING SCREENS HAVE EVER ASKED.**
 * `/billing`, `/billing/checkout` and `/billing/card` sit behind `auth` and
 * nothing else, which is defensible for *adding* a card — a staff user who does
 * that has spent nobody's money — and is not defensible for cancellation, which
 * on Authorize.Net cannot be undone.
 *
 * ⚠️ **OWNER ONLY, AND `canConfigureAutomation()` WAS DELIBERATELY NOT REUSED.**
 * That predicate includes `manager`, which is right for "decide what the
 * automation does" and wrong for "end the commercial relationship". `agency` is
 * excluded for the sharper version of the same reason: an agency manages a
 * client's marketing, and a client discovering that their agency cancelled their
 * subscription is a support call this product should not be able to generate.
 * Widening this is a decision somebody makes deliberately, on this class, with a
 * test that changes.
 *
 * ⚠️ **THE ABILITY TAKES NO MODEL, WHICH IS NOT AN OVERSIGHT.** A tenant with no
 * subscription at all still presses the button, and a gate that is only asked
 * when a row happens to exist is a gate whose coverage depends on data. So this
 * is a class-level ability — `Gate::authorize('cancel', Subscription::class)` —
 * and it is asked before anything is looked up.
 *
 * THE TENANT IS NOT CHECKED HERE — `AutopilotSettingsPolicy`'s rule, for its
 * reason. A policy answers "may this role do this?"; the tenant boundary answers
 * "is this row yours?", and `subscriptions` is `BelongsToTenant` and
 * RLS-`FORCE`d. Conflating the two makes this class read as the boundary and
 * quietly become the place people trust instead of the scope.
 */
final class SubscriptionPolicy
{
    public function cancel(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }

    /**
     * Who may buy credit (3482's screen).
     *
     * ⛔ **OWNER ONLY, ON THE SAME GROUNDS AS `cancel()` AND NOT AS A COPY OF
     * IT.** Buying a top-up is *"anything that spends money"* — the second of the
     * three things `CLAUDE.md` reserves CONFIRM for — and it charges the card the
     * owner put on file, immediately. `canConfigureAutomation()` includes
     * `manager` and would have been the convenient predicate; a manager deciding
     * what the automation does is a different question from a manager charging
     * somebody else's card. **`agency` is excluded for the sharper version of the
     * same reason**: an agency spending a client's money without being asked is
     * precisely the support call this product should not be able to generate.
     *
     * ⚠️ **WIDENING IT IS CHEAP AND NARROWING IT IS A REFUND**, which is why it
     * starts here. `CLAUDE.md`'s tiebreak — least support surface — points the
     * same way, and the screen names who can rather than showing a button that
     * 403s (1220).
     *
     * ⚠️ **THE ABILITY TAKES NO MODEL, FOR `cancel()`'s REASON.** A tenant with no
     * subscription row still buys credit, and a gate only asked when a row happens
     * to exist is a gate whose coverage depends on data.
     */
    public function purchaseCredit(User $user): bool
    {
        return $user->role === UserRole::Owner;
    }
}
