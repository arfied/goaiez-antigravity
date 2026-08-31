<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\ExportSource;
use App\Exceptions\ImpersonationRefused;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\Export\ExportBuilder;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Masmerise\Toaster\Toaster;

/**
 * The one place a tenant starts their own export (`28` §3.7).
 *
 * ## ⚠️ WHY THIS IS A PLAIN FORM POST AND NOT A LIVEWIRE ACTION (1900)
 *
 * It was a Livewire action on `Account\Settings`, and a suspended owner could
 * not reach it. `SuspendedTenantStatus` runs on the whole `web` group and
 * redirects them away from `/account`, which is `28` §3.7's rule inverted at the
 * one moment portability is worth anything — *"exporting is never delayed, gated
 * on retention offers, or degraded"*, quoted in three docblocks of this slice
 * while the HTTP layer gated it anyway. Decisions 314–316's shape, inside the
 * slice that cites them.
 *
 * ⚠️ **AND EXEMPTING `account.settings` WOULD NOT HAVE FIXED IT.** A Livewire
 * button posts to Livewire's own endpoint, not to the route that rendered the
 * page — so the exemption that looks right exempts a name the request never
 * carries, and the only one that would work exempts *every* action on *every*
 * screen from the suspension. A named POST route is exemptible at exactly the
 * width of the thing being promised. It is also what lets the suspended-account
 * page carry the control at all, which is the page a suspended owner is
 * actually looking at.
 *
 * ⚠️ **THAT ENDPOINT IS NAMED `default-livewire.update` IN THIS APPLICATION,
 * NOT `livewire.update`** (1997) — `php artisan route:list` says
 * `POST livewire-16868c99/update … default-livewire.update`. This docblock,
 * `SuspendedTenantStatus`, `routes/web.php` and decision 1900 all said the
 * latter, which is nothing: somebody later trying the "obvious" exemption
 * would have added a name that matches no route and got a hole that silently
 * does nothing, which is decision 256's vacuous-lint shape wearing a
 * middleware. The exemption list is now held to real route names by a lint in
 * `tests/Feature/Architecture/TenancyTest.php`.
 *
 * ## What it does not do
 *
 * **No form request, because there is no input.** The tenant comes from
 * `ResolveTenant` and the requester from the session; there is nothing a client
 * supplies and therefore nothing to validate. CSRF and `auth` are the whole
 * gate, and the service behind it carries its own refusals.
 *
 * **No policy, for the reason every owner surface in this application gives.**
 * `Tenancy::id()` being null *is* the authorisation question here: internal
 * staff belong to no business by design (`28` §9.1), so a signed-in support
 * agent arriving here has nothing to resolve and gets a 403.
 */
final class TenantExportRequestController extends Controller
{
    public function __invoke(ExportBuilder $exports): RedirectResponse
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        $user = auth()->user();

        abort_if(! $user instanceof User, 403);

        $business = Business::query()->findOrFail($id);

        try {
            $export = $exports->request($business, $user, ExportSource::Owner);
        } catch (ImpersonationRefused $refusal) {
            // `28` §9.4's blocklist, rendered as a sentence the agent can act on
            // rather than as the 500 an unhandled RuntimeException would be.
            abort(403, $refusal->getMessage());
        }

        // Outcome language, and it tells the truth about which of the two things
        // just happened — a fresh build, or the one they already had inside the
        // cool-down. A toast saying "building" over a link that is already there
        // is how somebody waits for an email that is not coming.
        Toaster::success($export->isDownloadable()
            ? 'Your download is ready — the link is on this page'
            : 'Building your download — we will email a link when it is ready');

        return back();
    }
}
