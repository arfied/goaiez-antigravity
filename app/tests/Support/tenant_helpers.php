<?php

declare(strict_types=1);

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Models\Location;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fixtures for tenant shapes the provisioner cannot make on its own
|--------------------------------------------------------------------------
|
| ⛔ **THE MULTI-LOCATION DEFECTS OF 3060–3064 WERE INVISIBLE BECAUSE OF THE
| HARNESS, NOT BECAUSE OF THE TESTS.** `BusinessFactory` creates no location and
| `TenantProvisioner` creates exactly one, so **every fixture in this repository
| produced a single-location tenant**. Four owner screens behaved differently on
| the second location and every one of their tests passed, because no test could
| build the shape. That is decision 272's shape moved into the fixtures.
|
| ⚠️ **A SHARED HELPER RATHER THAN A `BusinessFactory` STATE, AND THE REASON IS
| `LocationFactory`'s OWN DOCBLOCK.** That factory deliberately does not default
| `business_id` to `Business::factory()`, because a factory that creates its own
| parent creates a *second tenant* — so a test meaning "two locations in one
| business" would quietly get two businesses and prove nothing about isolation.
| `BelongsToTenant` fills the key from the tenant in context instead. A
| `BusinessFactory` state would have to establish that context itself, mid-build,
| which is the circularity `TenantProvisioner` exists to own. So the tenant is
| provisioned first, the context is set, and the extra locations arrive the way a
| real second branch would.
|
| ⚠️ **A PLAIN FUNCTION FILE, REQUIRED FROM `Pest.php`** — `tests/` is PSR-4 for
| classes, so these are loaded the way `architecture_helpers.php` is. Keep the
| names distinct: a duplicated global helper name is one of the four causes of a
| run that prints zero bytes and exits non-zero (694, 808).
|
*/

/**
 * A provisioned tenant with `$count` locations, deterministically named.
 *
 * ⚠️ **THE NAMES ARE EXPLICIT BECAUSE ORDER IS PART OF WHAT CALLERS ASSERT.**
 * `LocationFactory` takes `fake()->company()` and `LocationContext::options()`
 * orders by name, so unnamed fixtures order on the seed and any claim about
 * "the first location" passes or fails at random — 1888's lesson, one layer up.
 * `{$prefix} A` is the provisioned one; `B`, `C`, … are the added branches.
 *
 * @return array{user: User, business: int, locations: list<Location>}
 */
function tenantWithLocations(string $prefix = 'Ledger', int $count = 2): array
{
    $user = User::factory()->create(['name' => 'Owner of '.$prefix]);

    $business = app(TenantProvisioner::class)->provision($user);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    // Provisioning already made one; name it so the ordering is stable.
    Location::query()->update(['name' => $prefix.' A']);

    for ($i = 1; $i < $count; $i++) {
        Location::factory()->create(['name' => $prefix.' '.chr(65 + $i)]);
    }

    return [
        'user' => $user,
        'business' => (int) $business->id,
        'locations' => Location::query()->orderBy('name')->orderBy('id')->get()->all(),
    ];
}

/**
 * Grant the tenant in context `$hundredths` of AI credit, the way the monthly
 * reset does.
 *
 * ⛔ **THE GATE NEEDS A *FUNDED* TENANT, WHICH IS NOT THE SAME AS A TENANT WITH A
 * BALANCE** (decision 3609). `AiCredits::allowsAnotherCall()` permits an account
 * that has never been funded at all, so a fixture that merely leaves the balance
 * at zero proves nothing about the refusal — the call is allowed for the *other*
 * reason and the assertion passes for the wrong one (411). Every test that means
 * "this tenant is out of AI credit" must fund first and spend after, which is what
 * this helper and {@see exhaustAiCredit()} are for.
 *
 * ⚠️ **`resetMonthly()` RATHER THAN `record()`, SO THE ROW IS A REAL `Grant` IN
 * THE MONTHLY POOL.** A hand-written movement could land purchased credit in the
 * monthly pool or the reverse, and 3441's active-plan gate reads the pool.
 *
 * ⚠️ **HUNDREDTHS OF A CENT, ALREADY IN LEDGER UNITS** — the AI pool's own
 * denomination (3420). A cents figure passed here is out by a hundred and will
 * look entirely plausible.
 */
function fundAiCredit(int $hundredths): void
{
    app(CreditLedger::class)->resetMonthly(CreditProduct::Ai, $hundredths, 'test:fixture');
}

/**
 * Put `$hundredths` of purchased AI credit in the tenant's top-up pool.
 *
 * ⚠️ **NOT A SECOND `fundAiCredit()` CALL.** `CreditLedger::resetMonthly()` is
 * idempotent per product per calendar month (3429), so a fixture that granted
 * twice in one test would silently write nothing the second time and the
 * assertion after it would fail somewhere else entirely. A top-up is also the
 * honest shape for *"and then they had credit again"*: the monthly grant does not
 * arrive twice in a month, a purchase can.
 */
function topUpAiCredit(int $hundredths): void
{
    app(CreditLedger::class)->record(
        product: CreditProduct::Ai,
        kind: CreditKind::Purchase,
        delta: $hundredths,
        actor: 'test:fixture',
        reason: null,
        refType: 'credit_purchase',
        refId: 1,
    );
}

/**
 * Leave the tenant in context funded and with nothing left to spend.
 *
 * The state a real account reaches after a month of replies: `everFunded()` is
 * true, the spendable balance is zero, and the next call is refused because it
 * cannot be paid for rather than because nothing was ever granted.
 */
function exhaustAiCredit(): void
{
    fundAiCredit(1);

    app(CreditLedger::class)->record(
        product: CreditProduct::Ai,
        kind: CreditKind::Consume,
        delta: -1,
        actor: 'test:fixture',
        reason: null,
        refType: 'ai_call',
        refId: 1,
    );
}

/**
 * Register the two tenancy probes for the current test, and only for it.
 *
 * ⛔ **THESE WERE TWO LIVE ROUTES IN `routes/web.php` UNTIL 2026-08-25, ON THE
 * PUBLIC INTERNET, ON EVERY DEPLOYMENT** (9760–9765). Their own comment called
 * them *"Tenancy probes. Not a feature"*, and production's own
 * `bootstrap/cache/routes-v7.php` carried both names — so a debug surface with
 * no auth, no throttle and no environment guard shipped, and
 * `GET /_tenancy/locations` answered every unauthenticated caller with a 500
 * and a reported stack trace, unthrottled.
 *
 * ⚠️ **THE PROBES ARE KEPT AND THE DOOR IS NOT.** The three suites that drive
 * them are testing `ResolveTenant`, and the middleware's two load-bearing
 * properties are both about *ordering* — resolution after the session starts
 * and before route model binding — neither of which is visible when the
 * middleware is invoked in isolation. So the request stays real; what moves is
 * who registers the route. A test harness belongs in the harness.
 *
 * ⛔ **`Route::middleware('web')` IS THE WHOLE CLAIM THIS RESTS ON**, and it is
 * asserted rather than assumed: `TenancyProbeExposureTest` compares this
 * route's gathered middleware with that of `health`, which is declared in
 * `routes/web.php`, and requires them to be identical. If somebody moves
 * `ResolveTenant` off the `web` group and onto the route file, that reddens
 * naming the difference instead of these tests quietly proving less.
 *
 * ⚠️ **`refreshNameLookups()` IS NOT OPTIONAL AND ITS ABSENCE IS SILENT.**
 * `RouteCollection::addLookups()` runs inside `add()`, before `->name()` has
 * been chained, and the only thing that fixes that up for `routes/web.php` is
 * `RouteServiceProvider::boot()`'s `booted` callback — which has already run by
 * the time a test registers anything. Without this line `route('tenancy.current')`
 * throws *"Route [tenancy.current] not defined"* while `$this->get('/_tenancy/current')`
 * works, which is a confusing way to lose an afternoon.
 */
function tenancyProbeRoutes(): void
{
    Route::middleware('web')->group(function (): void {
        Route::get('/_tenancy/current', fn (): array => [
            'business_id' => Tenancy::id(),
            'user_id' => Tenancy::userId(),
        ])->name('tenancy.current');

        Route::get('/_tenancy/locations', fn (): array => [
            'names' => Location::pluck('name')->all(),
        ])->name('tenancy.locations');
    });

    Route::getRoutes()->refreshNameLookups();
}
