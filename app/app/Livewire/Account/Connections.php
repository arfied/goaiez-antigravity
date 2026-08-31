<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\GbpConnectionStatus;
use App\Exceptions\GbpConnectionRefused;
use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Http\Controllers\Gbp\GbpConnectController;
use App\Models\GbpConnection;
use App\Models\GscSiteProperty;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\User;
use App\Policies\GbpConnectionPolicy;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Gsc\SiteProperty;
use App\Services\Visibility\SearchConsoleProperties;
use App\Services\Visibility\VisibilitySyncHistory;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Connecting Google, per location — row 3 slice H's owner-facing half.
 *
 * ⚠️ **THE SCREEN SHIPS WITH THE SERVICE, AND THAT IS NOT A PREFERENCE.**
 * `GbpConnections` with no door would be decision 272's shape inside the slice
 * built to close decision 531 — and 531 is 272's shape in the first place. The
 * store, its writer and the way an owner reaches it land together or the table
 * joins the fourteen that did not.
 *
 * ⚠️ **AND A COMPONENT TEST PROVES NOTHING ABOUT WHETHER THIS RENDERS.**
 * `Livewire::test()` never renders the layout (570: every `/admin` screen 500'd
 * for as long as the admin shell existed, because the configured layout pointed
 * at a directory that did not exist). There is a real `GET` asserting `assertOk()`
 * for that reason, and it is not optional.
 *
 * ## Who may press what
 *
 * ⛔ **NOTHING ON THIS SCREEN ASKED ANYTHING ABOUT THE ACTOR UNTIL 6480** —
 * `connect()`, `check()` and `disconnect()` mint, verify and revoke a Zernio
 * grant holding `business.manage`, read *and write*, on a customer's Google
 * listing, and there was no `Gate::` call anywhere in the file (6455).
 * {@see GbpConnectionPolicy} is the rule; these three methods and
 * {@see GbpConnectController} are where it is asked.
 *
 * ⚠️ **`check()` IS GATED TOO AND ANSWERS YES TO EVERYONE**, which is a decision
 * rather than a gap — the policy carries the argument. Writing the open door
 * down is what stops the next reader mistaking it for the omission this slice
 * closed.
 *
 * ## What it deliberately does not offer
 *
 * **A list of accounts to choose from.** Zernio's `GET /v1/accounts` would
 * answer it in one call and would return **every account every tenant has
 * connected**, because our key is a platform key. The picker belongs on their
 * side of the flow, where the person authorising is the only one it can show —
 * which is also why the connect call runs in standard rather than headless mode.
 *
 * **Anything at all when the integration is off.** `gbp.zernio_enabled` seeds
 * false and this is a dark launch: the screen says so plainly rather than
 * offering a button that ends in a vendor error nobody can act on. The service
 * refuses independently — decision 391's rule, that a control which is not
 * rendered but is still honoured is a side door.
 *
 * ## ⛔ A signed-in reader with no tenant, and why every method here says so
 *
 * ⛔ **THIS SCREEN WAS THE LAST OWNER-FACING COMPONENT IN THE APPLICATION WITH
 * NO TENANTLESS GUARD, AND IT ANSWERED SUCH A READER WITH A 500** (9148). Its
 * twenty-six siblings under `App\Livewire\Account` all carry
 * `abort_if(Tenancy::id() === null, 403)`; this one did not, so `render()`'s
 * `Location::query()` reached `TenantScope`, which calls `Tenancy::idOrFail()`,
 * which throws `TenantNotResolved` — and `bootstrap/app.php`'s `withExceptions`
 * block registers no renderer for it. **"Fails closed on their first query" and
 * "is refused" are two different user experiences and this codebase spells them
 * the same way**: `ResolveTenant`'s docblock, and `routes/web.php`'s comment
 * over this very route, both describe the first as though it were the second.
 *
 * ⚠️ **THE GUARD IS ON ALL FOUR ENTRY POINTS RATHER THAN ON THE SCREEN**, on
 * 398's rule. `connect()`, `check()` and `disconnect()` arrive over
 * `/livewire/update` and never through `render()`, so a guard only where the
 * page is drawn would be a guard on the polite path — the same reasoning
 * `Gsc\SearchConsoleConnectController` gives for gating both halves of its
 * OAuth flow.
 *
 * ⚠️ **BEFORE `Gate::authorize`, DELIBERATELY, AND IT CHANGES NO STATUS CODE.**
 * `UserRole::canManageConnections()` answers **true** for `SuperAdmin`, so the
 * role gate does not refuse the population this guard is for; what the ordering
 * decides is the *reason*, and asking a policy whose subject is a
 * `GbpConnection` while there is no tenant for one to belong to is a question
 * with no subject.
 *
 * ⚠️ **403 RATHER THAN A REDIRECT, AND THE POPULATION IS LARGER THAN 5450 SAYS**
 * (9149). `SetupController`'s comment argues the majority shape from *"platform
 * staff have no business and never will"*. There is a second, non-staff
 * population it does not name: `Tenant\TenantDeletion`'s section 3 deliberately
 * leaves the owner's `users` row intact when a statutory erasure destroys their
 * account, so a former customer's login survives with no tenant. Both get 403,
 * and for the same reason — every destination a redirect could name refuses
 * them one hop later, so a redirect would spend a hop to arrive at this answer
 * with the sentence lost on the way.
 *
 * ## The Search Console door — added wave 38, lane B
 *
 * ⛔ **`SearchConsoleProperties::choose()` HAD ZERO CALLERS IN `app/` UNTIL
 * THIS SLICE.** The OAuth chain (`Gsc\SearchConsoleConnectController`, routes
 * `gsc.connect.redirect`/`gsc.connect.callback`) was complete and reachable by
 * a hand-typed URL; nothing in `resources/` or `app/Livewire/` linked to it, and
 * the property picker `choose()`'s own docblock describes — *"since the picker
 * only rendered real ones"* — had never been built. Three live consumers
 * (`SyncSearchConsoleJob`, `ChangeMeasurer`, `ContentSelfAudit`) therefore ran
 * dark on every deployment: `connectionState()` could reach `Usable`, but
 * `forLocation()` could never return a row, so the search arm of `29` §2 rule
 * 32's *measure 14–30 days, auto-rollback on regression* was permanently
 * `[null, null]` regardless of whether an owner connected.
 *
 * ⚠️ **WHY HERE AND NOT A NEW SCREEN.** `livewire.account.visibility`'s own
 * copy already said *"Connect Google Search Console under Google reviews"* —
 * this screen, under its actual title — before this slice existed to make that
 * true. `SearchConsoleProperties` is business-scoped (one grant) while the
 * property mapping is per-location, which is exactly this screen's existing
 * shape: one connect affordance, one card per location.
 *
 * ⚠️ **THE PROPERTY LIST IS FETCHED ON DEMAND, NEVER ON RENDER.** `available()`
 * makes a live `sites.list` call; `render()` here follows `connect()`'s and
 * `check()`'s own rule of never calling a vendor synchronously on page draw.
 * {@see openSearchConsolePicker()} is the one place that call is made, behind
 * an explicit press.
 *
 * ⚠️ **GATED WITH `OauthConnectionPolicy`, NOT `GbpConnectionPolicy`** — a
 * different table, a different model, and `GbpConnectionPolicy`'s own docblock
 * is the argument for why sharing one policy across the two stores would need
 * `Gate::policy()` wiring this application does not have. The predicate is the
 * same one, `canManageConnections()`, on 2965's reasoning: choosing which
 * property a location reads decides what this platform tells an owner and a
 * regulator about their own visibility, which is the credential-adjacent class
 * of act `OauthConnectionPolicy` already gates the grant itself with.
 */
#[Layout('components.account.layout')]
final class Connections extends Component
{
    /**
     * The location currently offering its Search Console property picker, or
     * null when none is open.
     *
     * ⚠️ **ONE OPEN AT A TIME, DELIBERATELY.** `gscAvailableProperties` holds one
     * fetched list; a second concurrently open picker would need a list per
     * location and a second Google call this screen exists to avoid making on
     * every render.
     */
    public ?int $gscPropertyPickerLocationId = null;

    /**
     * The tenant's connected Google account's own list, as {@see openSearchConsolePicker()}
     * last fetched it — never persisted, never trusted back from the browser.
     * {@see chooseSearchConsoleProperty()} re-asks {@see SearchConsoleProperties::choose()}
     * to re-fetch and re-check before writing anything, on decision 1083's rule.
     *
     * @var list<array{site_url: string, permission_level: string, readable: bool}>
     */
    public array $gscAvailableProperties = [];

    /**
     * The `<select>` value chosen for each location, keyed by location id.
     *
     * @var array<int, string>
     */
    public array $gscSelectedSiteUrl = [];

    public function connect(GbpConnections $connections, int $locationId): void
    {
        // A signed-in reader with no tenant — the class docblock carries the
        // argument. First, so that no lookup and no policy question runs for a
        // request that has no tenant to be about.
        abort_if(Tenancy::id() === null, 403);

        // ⛔ **THE ROLE GATE, AND IT WAS MISSING ALTOGETHER UNTIL 6480** (6455).
        // Before the location lookup, deliberately: a person who may not connect
        // an account should be told that, not handed a 404 for a location id
        // they never chose — `SearchConsoleConnectController::callback()`'s
        // ordering, and `Account\Calls`' rule that a refusal for the wrong
        // reason is two errors for one action.
        Gate::authorize('create', GbpConnection::class);

        $location = Location::query()->findOrFail($locationId);

        try {
            // ⚠️ **THE CALLBACK URL IS NO LONGER THIS SCREEN'S TO BUILD** (6602).
            // It now carries the Zernio profile reference inside its signature,
            // and that reference is not knowable until `begin()` has resolved it
            // at the vendor — so minting it here would mean signing a profile
            // nobody had chosen yet.
            $url = $connections->begin(
                location: $location,
                actor: $this->actor(),
            );
        } catch (GbpRequestFailed) {
            // Never the vendor's reason. `GbpRequestFailed` carries a machine
            // code for logs, and the four things that produce one here — our
            // key, their outage, our plan limit, the flag — all have the same
            // remedy from where the owner is sitting, which is to try later or
            // tell us.
            Toaster::error('We could not start that connection. Please try again shortly.');

            return;
        } catch (ImpersonationRefused $refused) {
            // ⚠️ **CAUGHT, NOT LET FLY** (6483). The reader here is a support
            // agent driving a customer's screen, and
            // `ImpersonationCapability::refusal()` is written for exactly them:
            // it names the rule and says what to do instead — walk the owner
            // through it on the call. An uncaught `RuntimeException` would be a
            // 500 on the customer's own page.
            Toaster::warning($refused->getMessage());

            return;
        } catch (GbpConnectionRefused $refused) {
            // ⚠️ **A DIFFERENT SENTENCE, DELIBERATELY, AND NOT AN ERROR TOAST**
            // (4721). The ceiling refusal is not this owner's problem and will
            // not clear by trying again, so "try again shortly" would be false
            // twice over. Their reviews and replies continue on rule 44's
            // handoff path, and the message names what still works rather than
            // what did not — `22`'s outcome-language rule.
            $refused->reason === 'zernio_ceiling_reached'
                ? Toaster::info($refused->getMessage())
                : Toaster::error('We could not start that connection. Please try again shortly.');

            return;
        }

        // ⚠️ Livewire's own redirect, not `redirect()->away()`. Livewire swaps
        // the redirector for one of its own, so `away()` returns a `Redirector`
        // rather than a `RedirectResponse` and the declared return type is a
        // TypeError at the moment the owner presses the button — a failure with
        // no test coverage anywhere but on this exact call.
        $this->redirect($url);
    }

    public function check(GbpConnections $connections, int $connectionId): void
    {
        // See `connect()`. Before `find()`, which is tenant-scoped and is what
        // threw.
        abort_if(Tenancy::id() === null, 403);

        $connection = $connections->find($connectionId);

        // ⚠️ **`check` RATHER THAN `create`, AND IT ANSWERS TRUE FOR EVERYONE.**
        // The ability exists so that the open door is a written decision rather
        // than an omission — see `GbpConnectionPolicy`. It is asked with the row
        // because the row is what a health check is about.
        Gate::authorize('check', $connection);

        try {
            $connection = $connections->refreshHealth($connection, $this->actor());
        } catch (GbpRequestFailed|GbpConnectionRefused) {
            Toaster::error('We could not check that connection just now.');

            return;
        }

        $connection->status === GbpConnectionStatus::Connected
            ? Toaster::success('Google is connected')
            : Toaster::warning('Google needs reconnecting');
    }

    public function disconnect(GbpConnections $connections, int $connectionId): void
    {
        // See `connect()`. Before `find()`, which is tenant-scoped and is what
        // threw.
        abort_if(Tenancy::id() === null, 403);

        $connection = $connections->find($connectionId);

        // ⛔ **AT LEAST AS TIGHT AS `connect()`, AND IT IS THE SAME PREDICATE.**
        // Disconnecting calls the vendor first and writes second, so the grant
        // really ends here; getting it back means the owner consenting again at
        // Google. Asked with the row rather than the class because the row has
        // already passed the global scope and row-level security, which a class
        // name would not have (6447).
        Gate::authorize('delete', $connection);

        try {
            $connections->disconnect($connection, $this->actor());
        } catch (ImpersonationRefused $refused) {
            // See `connect()`. The agent gets the sentence they can act on.
            Toaster::warning($refused->getMessage());

            return;
        } catch (GbpRequestFailed) {
            // ⚠️ Deliberately not "disconnected anyway". Our row is not what
            // holds the grant — the vendor's is — so writing one while their
            // call failed would tell an owner their listing was no longer
            // reachable by a third party when it still is.
            Toaster::error('We could not disconnect that account. Please try again shortly.');

            return;
        }

        Toaster::success('Google is disconnected');
    }

    /**
     * Fetch the tenant's connected Google account's own Search Console
     * properties and open the picker for one location.
     *
     * ⚠️ **THE ONE PLACE THIS SCREEN CALLS A VENDOR ON A PRESS RATHER THAN A
     * RENDER.** `available()` re-fetches `sites.list` every time, deliberately —
     * see its own docblock — so a stale list is never rendered as though it
     * were current.
     *
     * ⚠️ **UNREADABLE PROPERTIES ARE KEPT, NOT DROPPED**, and shown disabled in
     * the `<select>` with the reason: `SearchConsoleProperties::available()`'s
     * own docblock is the argument — filtering silently leaves an owner staring
     * at a list missing the property they came to choose.
     */
    public function openSearchConsolePicker(SearchConsoleProperties $properties, int $locationId): void
    {
        // See `connect()`. First, so that no lookup and no vendor call runs for
        // a request that has no tenant to be about.
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', OauthConnection::class);

        $location = Location::query()->findOrFail($locationId);

        try {
            $available = $properties->available($location);
        } catch (ProviderNotConnected) {
            // The grant broke between page load and this press — a revoke at
            // Google, or the request lost the race with a background refresh
            // failure. Never the vendor's reason: the remedy is the same one
            // `connectionState()` already renders on this screen.
            Toaster::error('Search Console needs reconnecting before you can choose a site.');

            return;
        } catch (SearchConsoleRequestFailed) {
            Toaster::error('We could not reach Search Console just now. Please try again shortly.');

            return;
        }

        $this->gscPropertyPickerLocationId = $locationId;

        $this->gscAvailableProperties = array_map(
            static fn (SiteProperty $property): array => [
                'site_url' => $property->siteUrl,
                'permission_level' => $property->permissionLevel->value,
                'readable' => $property->permissionLevel->canReadPerformance(),
            ],
            $available,
        );
    }

    /**
     * Close the picker without choosing anything.
     */
    public function closeSearchConsolePicker(): void
    {
        $this->gscPropertyPickerLocationId = null;
        $this->gscAvailableProperties = [];
    }

    /**
     * Point one location at one of the tenant's own Search Console properties.
     *
     * ⚠️ **`choose()` RE-FETCHES AND RE-CHECKS**; the `siteUrl` this method reads
     * from `$gscSelectedSiteUrl` is a `<select>` value a browser could in
     * principle alter, and decision 1083's whole design is that nothing here
     * has to trust it — {@see SearchConsoleProperties::choose()}'s own docblock
     * carries the argument.
     */
    public function chooseSearchConsoleProperty(SearchConsoleProperties $properties, int $locationId): void
    {
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', OauthConnection::class);

        $location = Location::query()->findOrFail($locationId);

        $siteUrl = $this->gscSelectedSiteUrl[$locationId] ?? '';

        if ($siteUrl === '') {
            Toaster::error('Choose a site from the list first.');

            return;
        }

        try {
            $properties->choose($location, $siteUrl, $this->chooser());
        } catch (InvalidArgumentException $e) {
            // ⚠️ Outcome language per case, never the machine code — `22`'s
            // rule, and {@see App\Enums\GscPermissionLevel::refusalReason()}
            // documents the two reasons a re-fetch can refuse a value this
            // picker itself just rendered: the list moved under the owner
            // between the fetch and the press, or the property needs
            // verifying at Google before it can be read.
            Toaster::error(match ($e->getMessage()) {
                'property_not_verified' => 'Finish verifying that property in Search Console before choosing it.',
                'permission_level_unreadable' => 'We could not tell whether your Google account can read that property. Please try again.',
                default => 'That site is no longer on your Google account\'s list. Please choose again.',
            });

            return;
        }

        $this->gscPropertyPickerLocationId = null;
        $this->gscAvailableProperties = [];
        unset($this->gscSelectedSiteUrl[$locationId]);

        Toaster::success('Search Console is set for this location');
    }

    /**
     * Unmap a location's Search Console property.
     *
     * ⚠️ **A CONFIGURATION CHOICE, NOT A CREDENTIAL REVOCATION** —
     * {@see SearchConsoleProperties::clear()}'s own docblock: it deletes rather
     * than soft-deletes, and gated the same as choosing it, on the reasoning
     * `GbpConnectionPolicy` gives for treating create and delete alike — undoing
     * this is as consequential as doing it, so it needs the same permission.
     */
    public function clearSearchConsoleProperty(SearchConsoleProperties $properties, int $locationId): void
    {
        abort_if(Tenancy::id() === null, 403);

        Gate::authorize('create', OauthConnection::class);

        $location = Location::query()->findOrFail($locationId);

        $properties->clear($location, $this->chooser());

        Toaster::success('Search Console property removed for this location');
    }

    public function render(
        DefaultsRegistry $defaults,
        GbpConnections $connections,
        VisibilitySyncHistory $history,
        SearchConsoleProperties $searchConsoleProperties,
    ): View {
        // See the class docblock. This is the line that produced the 500: both
        // `Location::query()` and `forLocations()` below are tenant-scoped.
        abort_if(Tenancy::id() === null, 403);

        // Hoisted rather than read twice: the whole list below is behind it, and
        // the review-absence read is skipped entirely when it is false.
        $available = $defaults->value('gbp.zernio_enabled') === true;

        $locations = Location::query()->orderBy('id')->get();

        return view('livewire.account.connections', [
            'available' => $available,

            // ⚠️ **THE AFFORDANCE IS ASKED THE SAME WAY THE ACTION IS** (6446),
            // and the sibling pattern exactly — `Calls`' `mayChoose`,
            // `Knowledge`'s `mayUpload`, `WidgetInstall`'s `mayEdit`. Never a
            // disabled button: a greyed-out control reads as a bug in our page,
            // where a sentence naming who can do this reads as the truth and
            // tells them who to ask.
            'mayManage' => Gate::allows('create', GbpConnection::class),
            'locations' => $locations,

            // ⚠️ Asked of the service rather than eager-loaded through a
            // relation on `Location`. A `hasOne` here would be a second reader
            // of `gbp_connections`, and widening the chokepoint lint for a
            // screen is decision 624's finding exactly — reasonable on its own
            // diff, and unrecognisable as a security change.
            'connections' => $connections->forLocations(),

            // ⛔ **"Connected." WAS THE WHOLE OF WHAT THIS SCREEN SAID ABOUT A
            // CONNECTION THAT HAD NOT READ A THING IN A WEEK** (10120–10139).
            // `SyncGoogleReviewsJob` records why every run of
            // `reviews.google_sync` read nothing — per run, per location, under
            // this tenant — and the one place an owner comes to ask *"why have
            // no reviews arrived since Tuesday"* read the connection row and
            // stopped. This is that record, and it is the same instrument
            // `Account\Home` reads.
            'reviewAbsences' => $available ? $this->reviewAbsences($connections, $history) : [],

            // ⚠️ **NEVER A VENDOR CALL.** `connectionState()` and `forLocation()`
            // both read only `oauth_connections` and `gsc_site_properties` — see
            // the class docblock for why `available()` is not asked here.
            'mayManageSearchConsole' => Gate::allows('create', OauthConnection::class),
            'gscConnectionState' => $locations->isEmpty()
                ? null
                : $searchConsoleProperties->connectionState($locations->first()),
            'gscProperties' => $this->searchConsoleMappings($searchConsoleProperties, $locations),
        ]);
    }

    /**
     * Each location's own Search Console property, keyed by location id.
     *
     * ⛔ **ONE INDEXED READ PER LOCATION, ON A SCREEN AN OWNER OPENS BY HAND** —
     * `reviewAbsences()`'s own reasoning, above. `SearchConsoleProperties` has
     * no bulk `forLocations()` for the reason its own file states: a second
     * per-location method there for the sole benefit of this loop is decision
     * 755's shape, a reader written for exactly one caller.
     *
     * @param  Collection<int, Location>  $locations
     * @return array<int, ?GscSiteProperty>
     */
    private function searchConsoleMappings(SearchConsoleProperties $properties, Collection $locations): array
    {
        $mappings = [];

        foreach ($locations as $location) {
            $mappings[$location->id] = $properties->forLocation($location);
        }

        return $mappings;
    }

    /**
     * The sentence each currently-connected location has earned, keyed by
     * location id, and absent for every location whose last read read.
     *
     * ⛔ **DERIVED HERE, NEVER IN THE BLADE** — `Account\ReplyQueue`'s own rule:
     * a template that asked the history per card would be a query per row and a
     * second place the answer could be got wrong.
     *
     * ⚠️ **THE ENFORCEMENT IS THE BLADE'S *Connected* BRANCH, NOT THIS
     * POPULATION, AND SAYING IT THE OTHER WAY ROUND WOULD BE A PROTECTION
     * CLAIMED WHERE IT IS NOT.** `Enums\ReviewSyncAbsenceReason::NotConnectedAtLastLook`
     * is in the past tense and may only be shown to somebody whose connection
     * works now; what guarantees that is that the template renders these
     * **inside** `@if ($connection?->isUsable())`, and a disconnected location
     * takes the `@elseif` branch and says its own piece there. **Measured:
     * widening this loop to every location leaves every test green**, because
     * the extra entries have nowhere to render.
     *
     * ⚠️ **SO `usableLocationIds()` IS HERE FOR TWO SMALLER REASONS AND BOTH ARE
     * REAL.** It is one indexed read per location that can actually render a
     * sentence rather than per location on the account, and it is the same
     * predicate `gbp:sync` fans out over and the same one `Account\Home` asks —
     * three readers, one definition of *usable* (9917).
     *
     * ⚠️ **A READ PER USABLE LOCATION, ON AN INDEX, ON A SCREEN AN OWNER OPENS
     * BY HAND.** Batching it into one grouped query is possible and is not worth
     * a second copy of `lastRun()`'s ordering — the `NULLS LAST`, the `id`
     * tiebreak and the `Running` exclusion are three correctnesses this screen
     * would then own a duplicate of.
     *
     * @return array<int, string>
     */
    private function reviewAbsences(GbpConnections $connections, VisibilitySyncHistory $history): array
    {
        $sentences = [];

        foreach ($connections->usableLocationIds() as $locationId) {
            $reason = $history->reviewAbsence($locationId);

            if ($reason !== null) {
                $sentences[$locationId] = $reason->sentence();
            }
        }

        return $sentences;
    }

    /**
     * `audit_log`'s actor vocabulary.
     *
     * Always `user:` here. Support acts through the console and never through
     * this screen — decision 1137 records what happens when one actor reaches
     * `audit_log` under two labels, and the fix was at the writer.
     */
    private function actor(): string
    {
        return 'user:'.(int) auth()->id();
    }

    /**
     * The signed-in owner, typed rather than `?Authenticatable`.
     *
     * `SearchConsoleProperties::choose()` records `chosen_by_user_id` from
     * whoever presses the button, and a request that reaches this far has
     * already passed the `auth` middleware — `InternalUsers::actor()`'s pattern,
     * narrowing rather than trusting.
     */
    private function chooser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
