<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\Config\DefaultsRegistry;
use App\Services\Export\ExportBuilder;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * `28` §9.5's *"plain status page with the support path"*.
 *
 * The one screen in this application an owner is sent to rather than choosing,
 * and the only state they cannot leave on their own. Three things it
 * deliberately does not do:
 *
 * ⚠️ **IT DOES NOT SHOW THE REASON.** `suspension_reason` is an internal note,
 * typed by an agent for an auditor, unreviewed, and quite possibly repeating a
 * third party's complaint verbatim. Putting it here would turn one person's
 * working note into a statement this company makes to a customer — and the
 * customer's remedy for "why" is a conversation with a human, which is what the
 * support path is for. The same judgement is made in `TenantSuspension`, where
 * the feed entry omits it too.
 *
 * ⚠️ **IT DOES NOT PRINT AN ADDRESS NOBODY READS.** Decision 482's rule, from
 * the crawler disclosure page: `support.contact_email` seeds empty and the
 * contact line renders only when an operator has set it. An unmonitored mailbox
 * on the one page whose entire job is to give somebody a way to reach us is
 * worse than no address, because it looks like an answer.
 *
 * ⚠️ **AND IT DOES NOT OFFER A WAY OUT.** No appeal form, no "request a
 * review" button. `28` §9.5 gates lifting a suspension on `super_admin` /
 * `ops_admin`, and a control that files a request into a queue nobody has built
 * would be decision 272's shape on the page least able to afford it.
 *
 * A suspended tenant is redirected here from anywhere; an unsuspended one is
 * redirected away, so the page cannot be linked to as a way of telling somebody
 * their account is fine.
 *
 * ## ⚠️ IT DOES OFFER THEIR DATA, AND THAT IS THE ONE THING IT MUST (1900)
 *
 * `28` §3.7: *"exporting is never delayed, gated on retention offers, or
 * degraded"*, and §9.5's cooling window is specified *"with export offered"* —
 * a suspension is precisely the moment that promise is worth something, and
 * this is the only page a suspended owner can see. `SuspendedTenantStatus`
 * redirects them off `/account`, where the control used to be and stays for
 * everybody else; without the panel below, a suspended owner could neither
 * start an export nor fetch one already built, and the trail would show that we
 * offered nothing.
 *
 * Two controls on a page whose docblock above says there is one. Both are ways
 * *out* rather than ways to argue: leave the session, or take your data with
 * you. Neither asks us for anything.
 */
final class SuspendedAccountController extends Controller
{
    public function __invoke(
        TenantSuspension $suspension,
        DefaultsRegistry $registry,
        ExportBuilder $exports,
    ): View|RedirectResponse {
        $id = Tenancy::id();

        $business = $id === null ? null : Business::query()->find($id);

        if (! $business instanceof Business || ! $suspension->isSuspended($business)) {
            return redirect()->route('account.home');
        }

        $contact = $registry->stringOrNull('support.contact_email');

        // Through the builder rather than the model, so `CrmTest.php`'s
        // `tenant_exports` chokepoint keeps meaning what it says — see
        // `ExportBuilder::mostRecent()`.
        $latestExport = $exports->mostRecent();

        return view('account.suspended', [
            'suspendedAt' => $suspension->suspendedAt($business),
            // Empty is absent — the seed is `''` rather than null, because
            // `stringOrNull()` returns null only for a non-string and an
            // operator clearing the box in Ops writes an empty string.
            'supportEmail' => $contact === null || trim($contact) === '' ? null : trim($contact),
            'latestExport' => $latestExport,
            'exportDownloadUrl' => $latestExport === null ? null : $exports->downloadUrl($latestExport),
        ]);
    }
}
