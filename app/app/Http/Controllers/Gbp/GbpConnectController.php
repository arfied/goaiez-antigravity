<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gbp;

use App\Exceptions\GbpConnectionRefused;
use App\Exceptions\GbpRequestFailed;
use App\Http\Controllers\Controller;
use App\Models\GbpConnection;
use App\Models\Location;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Masmerise\Toaster\Toaster;

/**
 * Where Zernio sends an owner back after they authorise Google.
 *
 * ⚠️ **THIS CALLBACK CARRIES NOTHING OF OURS UNLESS WE PUT IT THERE, AND THAT IS
 * THE WHOLE SECURITY STORY OF THIS FILE.** Zernio's connect flow appends its
 * result to whatever `redirect_url` we hand it — `connected`, `profileId`,
 * `accountId`, `username` — and passes no state of our own through the round
 * trip. A plain route would therefore accept `?accountId=<anything>` from any
 * signed-in owner, and `account_ref` is the single value that decides whose
 * Google reviews this application reads. Typing another tenant's account id into
 * the address bar would have been a working cross-tenant read.
 *
 * Four things stand between that and the row, and each catches what the others
 * cannot:
 *
 *   the signature      proves *we* minted this URL, for this location **and
 *                      this profile**, in the last thirty minutes.
 *                      {@see URL::temporarySignedRoute()}.
 *   the profile check  {@see GbpConnections::complete()} refuses a callback
 *                      whose signed `profile` is not the one this location's
 *                      connection was started against, and refuses a
 *                      *supplied* `profileId` that disagrees with it.
 *   the ownership call `ZernioGbpClient::profileOwnsAccount()` asks Zernio
 *                      whether the account named in the callback is on this
 *                      business's profile at all. ⛔ **This is the one that
 *                      survives a signature the owner legitimately holds**, and
 *                      it is the only check here whose other side is not
 *                      supplied by whoever sent the callback.
 *   the tenant scope   `Location` is `BelongsToTenant` and `gbp_connections` is
 *                      RLS-`FORCE`d, so a location id belonging to another
 *                      tenant is not found at all.
 *
 * ⛔ **THE THIRD LINE USED TO BE THE SECOND AND THE CLAIM WAS FALSE — CORRECTED
 * 2026-08-21 (6491, 6600–6604).** This block read *"the profile check … refuses
 * a `profileId` that is not this business's … so this is the check that
 * survives a signature the owner legitimately holds"*, and it survived one in
 * neither of the two ways it needed to. **(a)** The guard was
 * `$profileRef !== null && $profileRef !== …`, so a callback that simply
 * **omitted** `profileId` satisfied it vacuously and was never checked against
 * anything — 256's shape inside a security check. **(b)** Even supplied,
 * `profileId` is exactly as browser-controlled as `accountId`, and it is this
 * business's *own* reference: whoever holds the signature can send a matching
 * one alongside an arbitrary `accountId`. The check compared attacker-supplied
 * data with attacker-supplied data and read as a boundary. Because
 * `GbpConnections::bindAccount()` is an `updateOrCreate` keyed on
 * `account_ref`, the result was another tenant's binding **moved onto this
 * business** rather than refused.
 *
 * ⚠️ **THIS IS 314–316 AT ITS MOST EXPENSIVE AND THE PARAGRAPH IS KEPT FOR
 * THAT.** The sentence naming the hazard is what stopped anybody reading the
 * line beside it. The defect was found by reading rather than by a test, and
 * every test of the slice that shipped it was green.
 *
 * ⚠️ **The four provider parameters are excluded from the signature and must be
 * treated as untrusted for exactly that reason.** They have to be — Zernio
 * appends them after we sign, so validating them would fail every real callback
 * — which is why nothing here trusts `accountId` on its own. ⚠️ **`profile` is
 * deliberately NOT one of them**: we put it there before signing, so altering
 * it fails the signature rather than reaching a comparison.
 *
 * WHY A CONTROLLER RATHER THAN THE LIVEWIRE SCREEN. A third party redirects a
 * browser here; that is a plain GET arriving cold, with no component state and
 * no Livewire request. The screen starts the flow and this ends it.
 */
final class GbpConnectController extends Controller
{
    /**
     * The middleware a callback route needs, named here so the route file and
     * the tests cannot disagree about which parameters are excluded.
     *
     * @return list<string>
     */
    public static function middleware(): array
    {
        return ['auth', ValidateSignature::absolute(self::PROVIDER_PARAMETERS)];
    }

    /**
     * Everything Zernio appends to the redirect after a successful connect.
     */
    public const array PROVIDER_PARAMETERS = ['connected', 'profileId', 'accountId', 'username'];

    /**
     * Mint the URL Zernio should return the owner to.
     *
     * Thirty minutes: long enough to read a consent screen and pick a location
     * out of a list, short enough that a URL left in a browser history is not a
     * standing invitation.
     *
     * ⚠️ **`$profileRef` IS SIGNED, WHICH IS THE WHOLE POINT OF PASSING IT**
     * (6601). Zernio's `profileId` is appended after we sign and is therefore
     * untrusted; this is our own copy of the same value, minted here and
     * verified by `ValidateSignature` before the controller runs. It exists so
     * that {@see GbpConnections::complete()} has a profile expectation it never
     * has to take from the request — which is what makes the comparison
     * unskippable, where reading the vendor's parameter made it optional.
     *
     * ⚠️ **Only `GbpConnections::begin()` calls this**, and that is deliberate
     * (6602): the profile reference is not knowable before `begin()` resolves
     * it at the vendor, and a caller that could mint a callback URL without one
     * would be a caller that could mint one for the wrong profile.
     */
    public static function callbackUrlFor(Location $location, string $profileRef): string
    {
        return URL::temporarySignedRoute(
            'gbp.connect.callback',
            now()->addMinutes(30),
            ['location' => $location->id, 'profile' => $profileRef],
        );
    }

    /**
     * ⛔ **THE ROLE GATE IS HERE AS WELL AS ON THE SCREEN, AND NEITHER IS THE
     * OTHER'S OUTER GUARD** (6441, 398). This is the method that writes
     * `account_ref` and binds the account, and the signed URL it arrives on is
     * minted for a **location** rather than for a person — so any signed-in user
     * of the tenant holding that URL can complete the flow, for thirty minutes,
     * without ever having been permitted to start one.
     *
     * ⚠️ **REFUSING HERE HAS A COST AND IT IS WRITTEN DOWN RATHER THAN
     * DISCOVERED.** `GbpConnections::begin()`'s own docblock refuses to put the
     * *spend* check on this path, on the grounds that the grant — and therefore
     * the subprocessor relationship — begins at Zernio's consent screen and not
     * here; the same is true of a role refusal, so a 403 at this point leaves a
     * live grant we hold no row for. Three things make that trade payable:
     * `complete()` refuses a location with no `pending` row, so this method can
     * only ever finish a flow somebody *already* passed the gate to start; the
     * only arm that refuses a person who passed it is a role changed inside the
     * thirty-minute window, and `users.role` has no writer that can produce one
     * (6444); and the `pending` row survives, so a permitted person pressing
     * Connect again re-binds the same account and the record heals itself.
     *
     * ⛔ **A SIGNED-IN READER WITH NO TENANT REACHED THE `Location` LOOKUP AND
     * GOT A 500 — 9148.** `Location` is `BelongsToTenant`, so `findOrFail()`
     * runs `Tenancy::idOrFail()` inside `TenantScope`; the fourth of the four
     * layers this class's header names — *"the tenant scope"* — **throws rather
     * than refusing** when there is no tenant at all, which is a case that
     * paragraph does not distinguish from a location belonging to somebody
     * else. The role gate above does not cover it: `canManageConnections()`
     * answers true for `SuperAdmin`.
     *
     * ⚠️ **THE SIGNATURE IS NOT AN OUTER GUARD FOR THIS ONE** (398). It proves
     * *we* minted the URL, not that whoever is holding it now has a tenant — a
     * thirty-minute link pasted into a support thread is answered by whichever
     * account is signed in on the machine that opens it. `TenantlessAccessTest`
     * therefore signs the URL before driving it, or the census would be
     * measuring `ValidateSignature`.
     *
     * ⚠️ **403 RATHER THAN THE `redirect()->route('account.connections')` EVERY
     * OTHER ARM USES** (9149), for `Account\Connections`' reason: that screen
     * now aborts 403 for the same reader, so the redirect would be a hop to the
     * same status with the toast discarded.
     */
    public function __invoke(Request $request, GbpConnections $connections, int $location): RedirectResponse
    {
        // Before the gate and before the lookup — see the docblock. The signed
        // URL says the link is ours; it says nothing about who opened it.
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', GbpConnection::class);

        // ⚠️ `$location` is declared even though it is used only to find the
        // model, and its position is load-bearing: Laravel splices
        // container-resolved arguments into the *positional* parameter list, so
        // an undeclared route parameter lands in a slot meant for something
        // else and dies with a TypeError naming the wrong class entirely
        // (decision 396).
        $model = Location::query()->findOrFail($location);

        $accountRef = $request->string('accountId')->trim()->value();

        if ($accountRef === '') {
            // The owner declined at Google, closed the tab, or Zernio ended the
            // flow without a selection. Not an error and not worth a scary
            // message: nothing was written and pressing Connect again is the
            // whole remedy.
            Toaster::info('Google was not connected');

            return redirect()->route('account.connections');
        }

        try {
            $connections->complete(
                location: $model,
                accountRef: $accountRef,
                // ⚠️ **THE SIGNED ONE, NOT THE VENDOR'S** (6601). `profile` is
                // inside the signature, so `ValidateSignature` has already
                // refused any request in which it was altered; reaching for
                // `profileId` here instead would put the expectation back under
                // the sender's control, which is 6491.
                expectedProfileRef: $request->string('profile')->trim()->value(),
                profileRef: $request->string('profileId')->trim()->value() ?: null,
                label: $request->string('username')->trim()->value() ?: null,
                actor: 'user:'.(int) $request->user()?->getAuthIdentifier(),
            );
        } catch (GbpConnectionRefused $e) {
            // The refusal's own words, which name what the owner can do about
            // it and never why we think it happened. "That connection belongs
            // to a different account" is true and actionable; naming the
            // profile would print another tenant's identifier on this screen.
            Toaster::error($e->getMessage());

            return redirect()->route('account.connections');
        } catch (GbpRequestFailed) {
            // ⛔ **FAILING CLOSED IS THE POINT AND IT IS NEW AT 6603.**
            // `complete()` now asks Zernio whether this account is on this
            // business's profile, so a vendor outage, an expired platform key
            // or the integration being switched off mid-flow all reach here.
            // Writing `account_ref` anyway would be writing the one value that
            // decides whose reviews we read **without the check that was the
            // reason for the call** — so nothing is written, the `pending` row
            // survives, and pressing Connect again completes the flow.
            //
            // Never the vendor's reason: `Account\Connections::connect()` uses
            // this same sentence for the same class, because from where the
            // owner is sitting the four things that produce one have a single
            // remedy.
            Toaster::error('We could not confirm that connection. Please try again shortly.');

            return redirect()->route('account.connections');
        }

        Toaster::success('Google is connected');

        return redirect()->route('account.connections');
    }
}
