<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\WizardStep;
use App\Services\Setup\SetupFlow;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/setup` resolves to whichever step the owner is on.
 *
 * The one thing this does. `29` §7.2 requires the wizard resume from anywhere,
 * and a fixed landing route would restart it.
 *
 * A COMPLETED WIZARD ALWAYS RESOLVES TO `Done`, NEVER `current_step` DIRECTLY.
 * There is no dashboard to send a finished owner to yet, so `Done` is the
 * honest destination — the screen that already exists and already means
 * "you're set up". `SetupFlow::advanceTo()`'s forward-only guard should keep
 * `current_step` at `Done` once `complete()` has run, but this checks
 * `completed` explicitly rather than trusting that as the only thing standing
 * between a finished owner and a hop back into the wizard proper: a bookmark,
 * a support link, or a Back press to an earlier step's URL must never look
 * like "the wizard has more for you" to someone who is finished with it.
 */
final class SetupController extends Controller
{
    public function __invoke(Request $request, SetupFlow $flow): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        // ⛔ A SIGNED-IN ACCOUNT WITH NO TENANT IS A REAL SHAPE, NOT A BROKEN
        // ONE, AND THIS SCREEN USED TO ANSWER IT WITH A STACK TRACE — 5450.
        // Platform staff have no business and never will: staff reach tenant
        // data through impersonation, which is why `impersonation_sessions` is
        // the one table that names a tenant without taking the tenancy trait.
        // `ResolveTenant` deliberately passes such a request through with no
        // tenant established — its docblock says so, and says the destination
        // "belongs with auth" rather than with the middleware — so the decision
        // is this route's to make, and it was not being made.
        //
        // 403 rather than a redirect: every other tenant-only surface in this
        // application answers a tenantless request the same way, eight of them
        // before this one, and a wizard that bounced staff somewhere else would
        // be inventing a destination for an account the wizard has nothing to
        // say to.
        abort_if(Tenancy::id() === null, 403);

        // A teammate signed in through a business membership never ran the
        // setup wizard and has no progress row; the wizard is the owner's.
        if (! $user->hasRole(UserRole::Owner)) {
            return redirect()->route('account.home');
        }

        $progress = $flow->progressFor($user);

        if ($progress->completed) {
            return redirect()->route(WizardStep::Done->routeName());
        }

        return redirect()->route($progress->current_step->routeName());
    }
}
