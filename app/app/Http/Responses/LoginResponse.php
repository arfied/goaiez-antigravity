<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Services\Setup\SetupFlow;
use App\Support\Admin\AdminNav;
use App\Support\Admin\NavItem;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a person goes after they sign in.
 *
 * ⛔ **`config/fortify.php`'s `home` IS ONE STRING AND THERE ARE THREE ACCOUNT
 * SHAPES** — decision 5490. It reads `/setup`, set when the onboarding wizard
 * was the only authenticated route in the application, and its own comment says
 * so. Every signed-in person has landed there since: a tenant owner mid-wizard,
 * for whom it is right; a tenant owner who finished months ago, who gets the
 * wizard's terminus instead of their dashboard; and **platform staff, who have
 * no business and never will**, for whom `/setup` can only refuse.
 *
 * ⚠️ **THE STAFF CASE IS WHY THIS EXISTS AND IT IS NOT COSMETIC.** 5450 gave
 * `/setup` a `403` for a tenantless account, which is correct — 5451's argument
 * is that staff having no tenant is permanent and legitimate, and the wizard has
 * nothing to say to them. But a guard that fires on the *happy path of every
 * staff login* is a sign something upstream is misrouting, not that the guard is
 * wrong. Before 5450 the same journey was a 500.
 *
 * ⚠️ **THE CODEBASE HAD ALREADY SAID WHERE THIS BELONGS.** `ResolveTenant`'s
 * docblock, explaining why that middleware deliberately does not redirect:
 * *"this middleware does not know what the right destination is — that belongs
 * with auth (FOUND-04)"*. This is auth. A `LoginResponse` binding is Fortify's
 * own seam for it, and {@see RegisterResponse} — bound beside this one — already
 * chose that seam over moving `fortify.home`, noting in as many words that the
 * key "is also where *login* goes".
 *
 * ⚠️ **`home` IS KEPT AS THE FALLBACK RATHER THAN CHANGED**, which is what a
 * fallback is for: anything this method cannot place lands where it always did.
 *
 * ⛔ **AND IT WAS REACHED BY TWO OF THE FOUR SIGN-IN DOORS UNTIL 9156.**
 * `OauthLoginController::callback()` and `MagicLinkController::show()` — the two
 * that are ours rather than Fortify's — both ended on
 * `redirect()->intended(config('fortify.home', '/'))`, so they answered the
 * question above with the constant this class exists to replace. **A finished
 * owner signing in with Google or a magic link was returned to the wizard's
 * terminus on every sign-in.** Both now return
 * `app(LoginResponseContract::class)->toResponse($request)`, and
 * `tests/Feature/Architecture/StaffTest.php` fails the build on the next door
 * that decides for itself — beside 661's lint, which is the same rule about the
 * same four doors one concern over.
 *
 * ⚠️ **THE STAFF HALF OF THAT WAS INVISIBLE, AND KNOWING WHY MATTERS MORE THAN
 * THE FIX.** `SecondFactor::challenge()` runs *before* `Auth::login()` on both
 * of those doors and `28` §9.1 makes a factor mandatory for every internal
 * account — so a staff sign-in never reached the last line of either controller
 * and completed on `TwoFactorLoginResponse`, which is this same class. **Staff
 * were routed correctly by accident of a neighbouring rule**, which is why the
 * defect survived a slice written specifically about them.
 *
 * ⛔ **IT IMPLEMENTS *BOTH* LOGIN CONTRACTS, AND BINDING ONLY THE FIRST WOULD
 * HAVE MISSED EVERY ACCOUNT THIS EXISTS FOR.** Fortify routes a second-factor
 * sign-in through `TwoFactorLoginResponse`, a separate contract whose default
 * *also* redirects to `fortify.home`. Staff are required to carry a second
 * factor (`RequiresTwoFactor`), so their journey is POST → challenge →
 * **TwoFactorLoginResponse** — and a fix bound only to `LoginResponse` would
 * never fire for them while looking correct in every test that called the class
 * directly. Both contracts resolve here.
 *
 * ⚠️ **STAFF GO TO THE FIRST THING THEY MAY SEE, NOT TO A FIXED ADMIN URL.**
 * There is no `/admin` index route in this application — the admin surface is
 * many screens and no landing page — so inventing one here would be a screen
 * nobody asked for. `AdminNav::for()` already answers "what may this person
 * see", role-filtered, and a support agent's first item is legitimately not a
 * super-admin's. If the nav ever grants them nothing, the fallback carries them.
 */
final class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            // Fortify's headless contract, matching RegisterResponse: a JSON
            // client is not a browser and has nowhere to be redirected to.
            return new JsonResponse('', 200);
        }

        return redirect()->intended($this->destinationFor($request));
    }

    /**
     * ⚠️ `intended()` above, not a bare redirect: somebody who asked for a page,
     * was bounced to the sign-in screen and authenticated should arrive where
     * they were going. This method answers only the case where there is no such
     * page — which is every ordinary sign-in.
     */
    private function destinationFor(Request $request): string
    {
        $user = $request->user();
        $fallback = (string) config('fortify.home', '/');

        if ($user === null) {
            return $fallback;
        }

        // ⛔ **A JUST-CREATED USER CARRIES NO ROLE IN MEMORY, AND THIS CLASS
        // MET ONE THE MOMENT THE SSO DOOR STARTED ASKING IT** (753, 9156).
        // `users.role` is populated by the migration's column default, so the
        // first-time SSO signup — the one request where somebody is created and
        // signed in together — hands this method a model whose `role` is null,
        // and `$user->role->isTenantRole()` is a 500 on the login path.
        // `OauthLoginController` now re-reads the row so that cannot happen;
        // this is the inner guard, on 398's rule that an inner guard must not
        // depend on an outer one.
        //
        // ⚠️ **THE FALLBACK IS THE ANSWER RATHER THAN A GUESS.** Treating an
        // unreadable role as staff would send a brand-new business owner into
        // the admin console, and treating it as a tenant would guess. The
        // fallback is what this class already promises for anything it cannot
        // place — see the class docblock — and it is `/setup`, which is where a
        // person in this state actually belongs.
        //
        // ⚠️ **`getAttribute()` RATHER THAN `$user->role`, AND THE READ IS THE
        // POINT.** `User::$role` is documented non-nullable, so a plain
        // `$user->role instanceof UserRole` is `instanceof.alwaysTrue` to
        // Larastan and `$user?->role` is `nullsafe.neverNull` — the docblock is
        // right about the column and wrong about the one request where the row
        // has not been read back. Asking the attribute bag is the honest form of
        // the question *"has this model got a role hydrated"*, and it needs no
        // ignore and no widening of a type every caller in the application
        // depends on. `StaffSession::applies()` reaches the same place through a
        // nullable parameter, which is not available here.
        $role = $user->getAttribute('role');

        if (! $role instanceof UserRole) {
            return $fallback;
        }

        if (! $role->isTenantRole()) {
            return $this->staffDestination($request) ?? $fallback;
        }

        // ⛔ THE TENANT IS NOT IN CONTEXT HERE AND ASSUMING IT IS WAS THIS
        // CLASS'S FIRST BUG. `ResolveTenant` runs at the *start* of the request,
        // when a sign-in POST is still unauthenticated, so it resolves nothing —
        // authentication happens afterwards, inside this same request. Every
        // direct-call test passed against a tenant the test had set by hand; a
        // real `POST /login` landed on the fallback. Establish it here.
        if (! $this->establishTenantFor($user)) {
            return $fallback;
        }

        return $this->wizardIsFinished($user) ? route('account.home') : route('setup.index');
    }

    /**
     * Establish the tenant the way `ResolveTenant` would have, had the person
     * been authenticated when it ran.
     *
     * ⚠️ **THE `oldest('id')` IS 3949's RULE AND IS NOT A STYLE CHOICE.**
     * `businesses.owner_user_id` carries no unique index, so an unordered
     * `first()` returns whichever row the plan reaches first — and the day a
     * user owns two, the tenant established for their request stops being
     * stable. Oldest rather than newest, because both are deterministic and only
     * one leaves existing users where they already are.
     *
     * ⚠️ **`withoutGlobalScopes()` IS LOAD-BEARING AND SAFE FOR THE SAME REASON
     * IT IS IN THE MIDDLEWARE**: the scope would call `Tenancy::idOrFail()`,
     * which is precisely what has not happened yet, and the database's
     * `owner_lookup` policy still restricts the query to businesses this user
     * owns — so it cannot return somebody else's row whatever this code asks.
     *
     * ⚠️ **THIS IS THE MIDDLEWARE'S TWIN AND A TEST HOLDS THEM TOGETHER** —
     * *"the login responder and ResolveTenant choose the same business"*, driven
     * against a user who owns two. Five console commands already repeat this
     * shape; what must not drift is the ordering.
     */
    private function establishTenantFor(User $user): bool
    {
        Tenancy::setUser($user->id);

        $business = Business::withoutGlobalScopes()
            ->where('owner_user_id', $user->id)
            ->oldest('id')
            ->first();

        if ($business === null) {
            return false;
        }

        Tenancy::set($business->id);

        return true;
    }

    /**
     * The first screen the nav grants this member of staff.
     */
    private function staffDestination(Request $request): ?string
    {
        $first = AdminNav::for($request->user())
            ->flatten()
            ->first();

        return $first instanceof NavItem ? route($first->route) : null;
    }

    /**
     * ⚠️ **A MISSING `wizard_progress` ROW MUST NOT BREAK A SIGN-IN.**
     * `SetupFlow::progressFor()` ends in `firstOrFail()`, which is right for the
     * wizard itself and wrong on the login path: an account provisioned before
     * that row existed would throw here, and the failure would present as
     * "signing in is broken" rather than as a missing row. Falling through to
     * the wizard is the honest answer — it is where somebody without progress
     * belongs, and `SetupController` decides what to do when they arrive.
     */
    private function wizardIsFinished(User $user): bool
    {
        try {
            return app(SetupFlow::class)->progressFor($user)->completed;
        } catch (ModelNotFoundException) {
            return false;
        }
    }
}
