<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plugin;
use App\Models\User;

/**
 * Who may say which websites this business's review feed will answer.
 *
 * `AutopilotSettingsPolicy`'s shape, and its reasoning about the tenant applies
 * here word for word: a policy answers *may this role do this?*, the tenant
 * boundary answers *is this row yours?*, and conflating them makes this class
 * the place people trust instead of the scope. A `Plugin` can only be resolved
 * inside its own tenant — `TenantScope` hides it and `tenant_write` blocks the
 * UPDATE underneath — so by the time a model reaches this file the boundary has
 * already held.
 *
 * ⚠️ **THE ALLOWLIST IS NOT AN ACCESS CONTROL AND THIS IS NOT PROTECTING ONE.**
 * `WidgetPlugins::originIsAllowed()` records at length that `Origin` is trivially
 * forged by anything that is not a browser, so adding a domain does not disclose
 * anything a holder of the embed key could not already read with curl. What this
 * policy protects is the *act*: naming a website that may publish this business's
 * reviews under its own brand is a standing decision about where the business
 * appears, which is the same class of act as changing what the automation does.
 * So it sits with `canConfigureAutomation()` rather than with everyone signed in.
 *
 * ⚠️ **AND IT IS NOT `canManageConnections()`, WHICH WAS THE OTHER CANDIDATE.**
 * That one is scoped to minting a credential that *acts as the business* — an
 * OAuth token — and it deliberately excludes a manager. A domain name is not a
 * credential, and a manager who is trusted to change what the automation says to
 * customers is trusted to say which of the business's own sites shows it.
 */
final class PluginPolicy
{
    /**
     * Everyone signed in to the business can see the install snippet.
     *
     * Staff included, deliberately: the person who pastes a line into the
     * website is very often not the person who decides policy, and hiding the
     * snippet from them turns a two-minute job into a request to the owner.
     * Reading it discloses nothing — the embed key is published on the page it
     * is installed on, by design.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plugin $plugin): bool
    {
        return true;
    }

    /**
     * The criterion. Staff install; they do not decide where this appears.
     */
    public function update(User $user, Plugin $plugin): bool
    {
        return $user->role->canConfigureAutomation();
    }

    /**
     * Nobody, by design.
     *
     * `embed_key` is a permanent public identifier — it lives in a `<script>`
     * tag on somebody's website, outside anything we control. Deleting the row
     * does not retract the line that was pasted; it turns a live feed into a
     * 404 on a page nobody will think to look at. The operation that is actually
     * wanted here is emptying the allowed domains, which is an update.
     */
    public function delete(User $user, Plugin $plugin): bool
    {
        return false;
    }
}
