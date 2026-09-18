<?php

declare(strict_types=1);

use App\Enums\GbpRevocationOutcome;
use App\Enums\UserRole;
use App\Livewire\Admin\GbpGrantRevocations;
use App\Models\Business;
use App\Models\GbpAccountBinding;
use App\Models\GbpGrantRevocationAttempt;
use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\ZernioReconciliation;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\AdminNav;
use App\Support\Admin\NavItem;
use App\Support\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The Google grants a deleted tenant left behind, given a screen — 4888(a), (b)
|--------------------------------------------------------------------------
|
| 4880-4884 built the recording and the nightly retry with no way for a person
| to see either. 4888 named the gap directly: "nothing surfaces an outstanding
| grant on a screen ... an operator who does not read cron output learns
| nothing." This is that screen.
|
| ⚠️ THE ROUTE GATE IS DRIVEN THROUGH A REAL GET, NOT THROUGH `Livewire::test()`
| — decision 809, `SendingControlsAdminTest`'s own rule: a component test runs
| no middleware, so an authorization test through it says nothing about the
| route.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'name' => 'Platform Staff',
        'role' => UserRole::SuperAdmin,
    ]);

    // `GbpGrantRevocationTest`'s own precondition: `ZernioGbpClient::assertEnabled()`
    // throws before any HTTP call while this is off, which every retry-path
    // test here would misread as "the vendor refused it".
    config(['credentials.zernio_api_key' => 'sk_'.str_repeat('a', 64)]);
    (new DefaultsRegistry)->set('gbp.zernio_enabled', true, 'test');

    Tenancy::forgetAll();
});

/**
 * The toast payload as the browser receives it — `SendingControlsAdminTest`'s
 * shape: `Toaster::fake()`'s collector is drained by the Livewire relay on
 * dehydrate, so a test holding the fake finds it empty.
 *
 * @return callable(string, array<string, mixed>): bool
 */
function gbpToast(string $type, string $contains): callable
{
    return fn (string $name, array $params): bool => ($params['type'] ?? null) === $type
        && is_string($params['message'] ?? null)
        && str_contains($params['message'], $contains);
}

test('nothing owed renders as an invitation to do nothing, not an empty grid', function (): void {
    // ⚠️ THE SECOND LIST IS *OFFERED* RATHER THAN ANSWERED (5076). "None found."
    // is what it says once somebody has asked; before that it says what asking
    // costs, because an empty list nobody requested reads as an all-clear nobody
    // checked for.
    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->assertSee('Nothing is currently owed a revocation.')
        ->assertSee('Check for orphaned bindings')
        ->assertDontSee('None found.');
});

test('an owed grant is listed by number, with when it was deleted and what has been tried', function (): void {
    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0001',
        'business_id' => 99101,
        'location_id' => 88101,
        'revocation_owed_at' => now()->subHours(3),
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->assertSee('Business #99101')
        ->assertSee('location #88101')
        ->assertSee('Not tried yet.')
        ->assertSee('Retry now');
});

test('a failed nightly attempt is shown with its count and the vendor code, never the response body', function (): void {
    // ⚠️ MUTATION: delete `'reason' => $e->reason` from `attemptRevocation()`'s
    // failure branch in GbpConnections. This reddens on the vendor code.
    $binding = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0002',
        'business_id' => 99102,
        'location_id' => 88102,
        'revocation_owed_at' => now(),
    ]);

    GbpGrantRevocationAttempt::query()->create([
        'account_ref' => $binding->account_ref,
        'business_id' => $binding->business_id,
        'location_id' => $binding->location_id,
        'outcome' => GbpRevocationOutcome::Failed,
        'reason' => 'server_error',
        'actor' => GbpConnections::SYSTEM_ACTOR,
        'created_at' => now()->subMinutes(10),
    ]);

    $html = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->html();

    expect($html)->toContain('1 failed attempt')
        ->and($html)->toContain('Zernio refused it')
        ->and($html)->toContain('server_error')
        ->and($html)->toContain(GbpConnections::SYSTEM_ACTOR);
});

test('an operator retries an owed grant and it is no longer listed as owed', function (): void {
    // ⚠️ MUTATION: delete the `$connections->retryRevocation(...)` call from
    // GbpGrantRevocations::retry(). This reddens — the row stays on the screen.
    Http::preventStrayRequests();
    Http::fake(['zernio.com/api/v1/accounts/*' => Http::response([], 200)]);

    $binding = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0003',
        'business_id' => 99103,
        'location_id' => 88103,
        'revocation_owed_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('retry', $binding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', gbpToast('success', 'Zernio accepted the request'))
        ->assertDontSee('Business #99103');

    expect(GbpAccountBinding::query()->count())->toBe(0);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/v1/accounts/acct_screen_0003'));
});

test('a retry the vendor refuses leaves the grant on the screen, attributed to the operator', function (): void {
    Http::preventStrayRequests();
    Http::fake(['zernio.com/api/v1/accounts/*' => Http::response(['code' => 'server_error'], 500)]);

    $binding = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0004',
        'business_id' => 99104,
        'location_id' => 88104,
        'revocation_owed_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('retry', $binding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', gbpToast('error', 'Zernio refused the request'))
        ->assertSee('Business #99104');

    expect(GbpAccountBinding::query()->count())->toBe(1);

    $attempt = GbpGrantRevocationAttempt::query()->sole();

    expect($attempt->outcome)->toBe(GbpRevocationOutcome::Failed)
        ->and($attempt->actor)->toBe('user:'.$this->admin->id);
});

test('retrying a grant nothing recorded as owed is refused with no vendor call at all', function (): void {
    Http::preventStrayRequests();

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('retry', 999999)
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', gbpToast('error', 'not currently recorded as owed'));
});

test('a retry targets exactly the row pressed, not every owed grant on the same business', function (): void {
    // ⚠️ THE UI-LEVEL PROOF OF `GbpConnections::retryRevocation()`'s own claim.
    Http::preventStrayRequests();
    Http::fake(['zernio.com/api/v1/accounts/*' => Http::response([], 200)]);

    $pressed = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0005a',
        'business_id' => 99105,
        'location_id' => 88105,
        'revocation_owed_at' => now(),
    ]);

    $sibling = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0005b',
        'business_id' => 99105,
        'location_id' => 88106,
        'revocation_owed_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('retry', $pressed->id)
        ->assertHasNoErrors();

    expect(GbpAccountBinding::query()->whereKey($pressed->id)->exists())->toBeFalse()
        ->and(GbpAccountBinding::query()->whereKey($sibling->id)->exists())->toBeTrue();

    Http::assertSentCount(1);
});

test('a possibly-orphaned binding is shown with no way to act on it, beside an owed row that has one', function (): void {
    // ⛔ 4884's own words: "a sweep that revoked on it disconnects a live
    // customer's Google listing the first time a filter is wrong ... a human
    // reads the count and decides." Nothing here may offer a click.
    //
    // ⛔ **AND THE OWED ROW IS WHAT MAKES THAT FALSIFIABLE** (5077). This test
    // asserted `assertDontSee('Retry now')` on a page with no owed rows at all,
    // so it could not tell "the orphan list offers no button" from "there were
    // no buttons on this page to begin with" — 256's shape wearing 744's
    // costume, and it would have passed against a component that rendered a
    // retry control on every orphan.
    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_owed_beside_orphan',
        'business_id' => 99108,
        'location_id' => 88109,
        'revocation_owed_at' => now()->subHour(),
    ]);

    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_orphan',
        'business_id' => 99106,
        'location_id' => 88107,
        // No revocation_owed_at — this is the shape an orphan predates.
    ]);

    $html = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('findOrphans')
        ->assertSee('Possibly orphaned')
        ->assertSee('Business #99106')
        ->assertSee('never acted on automatically')
        ->assertSee('Business #99108')
        ->html();

    // Exactly one, and it belongs to the owed row.
    expect(substr_count($html, 'Retry now'))->toBe(1);
});

test('the orphan probe does not run until an operator asks for it', function (): void {
    // ⛔ **IT RAN IN `render()`, ONCE PER LIVE BINDING, ON EVERY ROUND TRIP**
    // (5076). `bindingsWithNoSurvivingBusiness()` probes every binding with no
    // revocation stamp — every connected location of every paying customer — and
    // each probe is a `Tenancy::actingAs()`, whose own docblock says it is "for
    // jobs, console commands, and tests — not a way around the boundary in
    // request code". 4887 declined to schedule this nightly on exactly that
    // cost, and a page pays it far more often than a cron does.
    //
    // ⚠️ MUTATION: pass `bindingsWithNoSurvivingBusiness()` back into the view
    // from `render()`. This reddens twice — the tenancy-switch count on the
    // first render, and the orphan appearing before anybody asked.
    $live = Business::factory()->create();

    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_live_0001',
        'business_id' => $live->id,
        'location_id' => 88110,
    ]);

    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_orphan_probe',
        'business_id' => 99109,
        'location_id' => 88111,
    ]);

    Tenancy::forgetAll();

    // Every tenancy switch is a `set_config` on this connection — see
    // `Tenancy::applyToDatabase()`. Counting them is what makes the cost claim
    // a fact rather than a paragraph.
    $switches = 0;

    DB::listen(function (QueryExecuted $query) use (&$switches): void {
        if (str_contains($query->sql, 'set_config')) {
            $switches++;
        }
    });

    $component = Livewire::actingAs($this->admin)->test(GbpGrantRevocations::class);

    $component->assertSee('Check for orphaned bindings')
        ->assertDontSee('Business #99109');

    expect($switches)->toBe(0);

    $component->call('findOrphans')
        ->assertSee('Business #99109')
        ->assertDontSee('Business #'.$live->id.'</li>', escape: false);

    expect($switches)->toBeGreaterThan(0);
});

test('a former tenant with two orphaned locations is listed once, not twice', function (): void {
    // ⚠️ **A LIST OF BUSINESSES, NOT OF BINDINGS** (5077). The service returns
    // one reference per orphaned *row*, which is what `--reconcile` counts and
    // prints. Rendered straight, a former tenant with two orphaned locations
    // printed "Business #7" twice under two identical `wire:key`s, and
    // Livewire's morphing on duplicate keys is undefined.
    foreach ([88120, 88121] as $index => $locationId) {
        GbpAccountBinding::query()->create([
            'account_ref' => 'acct_screen_dupe_'.$index,
            'business_id' => 99110,
            'location_id' => $locationId,
        ]);
    }

    $html = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('findOrphans')
        ->html();

    expect(substr_count($html, 'Business #99110'))->toBe(1)
        ->and(substr_count($html, 'wire:key="orphan-99110"'))->toBe(1);
});

test('an owed grant whose business still exists is flagged and offers no retry', function (): void {
    // ⛔ **THE STAMP CAN NAME A LIVE CUSTOMER** (5074, 5073). Nothing clears
    // `revocation_owed_at`, and `bindAccount()` used to rewrite `business_id` on
    // a re-connect while leaving it set — so an account connected again by a
    // surviving business carried the departed tenant's obligation onto it.
    // Pressing Retry there disconnects a paying customer's Google Business
    // Profile, which they cannot restore without consenting again at Zernio.
    //
    // ⚠️ MUTATION: drop the `businessSurvives` branch from the Blade view. This
    // reddens on the button count. (The service refuses it too — see
    // `GbpGrantRevocationTest` — because a withheld button is a rendering
    // decision and not a refusal.)
    Http::preventStrayRequests();

    $live = Business::factory()->create();

    GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_stale',
        'business_id' => $live->id,
        'location_id' => 88130,
        'revocation_owed_at' => now()->subDays(4),
    ]);

    Tenancy::forgetAll();

    $html = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->assertSee('This business still exists')
        ->html();

    expect(substr_count($html, 'Retry now'))->toBe(0);
});

test('a retry on a stale obligation is refused at the service, not merely unrendered', function (): void {
    // ⚠️ **THE COMPONENT METHOD TAKES A BINDING ID FROM THE BROWSER**, so the
    // missing button above proves nothing about what happens when `retry` is
    // called with that id anyway. 398's shape inverted: here the *outer* guard
    // is the one that does not exist.
    Http::preventStrayRequests();

    $live = Business::factory()->create();

    $binding = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_stale_2',
        'business_id' => $live->id,
        'location_id' => 88131,
        'revocation_owed_at' => now()->subDays(4),
    ]);

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('retry', $binding->id)
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', gbpToast('error', 'names a business that still exists'));

    expect(GbpAccountBinding::query()->sole()->revocation_owed_at)->not->toBeNull();
});

test('the admin gate is checked before a retry runs', function (): void {
    // `SendingControlsAdminTest`'s own falsifiability note: called on the
    // instance, because a real GET would answer 403 under either ordering.
    //
    // ⚠️ MUTATION: move `$this->authorize(AdminAccess::GATE);` below the try
    // block in `retry()`. This reddens with the wrong exception, or none.
    $binding = GbpAccountBinding::query()->create([
        'account_ref' => 'acct_screen_0006',
        'business_id' => 99107,
        'location_id' => 88108,
        'revocation_owed_at' => now(),
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->instance();

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    expect(fn (): mixed => $component->retry($binding->id, app(GbpConnections::class)))
        ->toThrow(AuthorizationException::class);

    expect(GbpAccountBinding::query()->whereKey($binding->id)->exists())->toBeTrue();
});

test('the admin gate is checked before the orphan probe runs', function (): void {
    // ⚠️ **A NEW PUBLIC ENTRY POINT IS A NEW THING TO GATE** (5076). `retry()`
    // has had this test since W25; `findOrphans()` arrived in the fix wave and a
    // fix wave earns the same pass as any other change. Called on the instance
    // rather than through a GET, on `SendingControlsAdminTest`'s falsifiability
    // note: a real request answers 403 under either ordering, so it cannot tell
    // an authorized action from an unauthorized one.
    $component = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->instance();

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    expect(fn (): mixed => $component->findOrphans(app(GbpConnections::class)))
        ->toThrow(AuthorizationException::class);
});

test('the component is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->assertForbidden();
});

test('the route is gated too, because a component test runs no middleware', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get(route('admin.gbp-grant-revocations'))
        ->assertForbidden();
});

test('an unauthenticated visitor cannot reach the screen', function (): void {
    $this->get(route('admin.gbp-grant-revocations'))->assertRedirect();
});

test('the screen actually renders over a real request, behind a second factor', function (): void {
    // `SendingControlsAdminTest`'s own defect note: `Livewire::test()` renders
    // no layout and no route middleware, so a real GET is what proves this
    // renders at all and that `RequiresTwoFactor` still applies to it.
    $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

    $this->actingAs($admin)
        ->get(route('admin.gbp-grant-revocations'))
        ->assertOk()
        ->assertSee('Google grants left behind by deleted accounts');
});

test('an internal account without a second factor cannot reach the screen', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.gbp-grant-revocations'))
        ->assertRedirect(route('two-factor.setup'));
});

test('the nav offers the screen to platform staff and to nobody else', function (): void {
    expect(AdminNav::for($this->admin)->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.gbp-grant-revocations'))
        ->toBeTrue();

    expect(AdminNav::for(User::factory()->create(['role' => UserRole::Owner]))->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.gbp-grant-revocations'))
        ->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The third list — connected at Zernio, unused here (6779(d))
|--------------------------------------------------------------------------
|
| ⚠️ A DIFFERENT QUESTION FROM THE TWO ABOVE, ON THE SAME SCREEN. Those are
| about businesses that no longer exist. This is about accounts we are paying
| for right now — an owner who granted `business.manage` at Zernio's consent
| screen and never came back leaves a live grant, a line on the invoice, and a
| `pending` row here that nothing revisits, invisible to `ZernioSpend` because
| that counts bindings.
|
*/

/**
 * Zernio's account list, faked for the screen.
 *
 * ⚠️ Deliberately local rather than reusing the service suite's fixture. Pest
 * loads every test file, so a helper declared in another one *is* reachable —
 * and a screen test silently depending on a service test's fixture is how a
 * change to one reddens the other for reasons nobody can see from the diff.
 * Decisions 694 and 808 are the same lesson about the collision itself.
 *
 * @param  list<array<string, mixed>>  $accounts
 */
function gbpScreenFakeVendorAccounts(array $accounts): void
{
    Http::preventStrayRequests();

    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => $accounts]),
    ]);
}

test('what Zernio is billing for is offered rather than answered', function (): void {
    // ⛔ **NOT RENDERED BY DEFAULT, AND NOT FOR THE REASON THE SECTION ABOVE IT
    // IS NOT.** That one costs a tenancy switch per binding (5076); this one
    // costs a single vendor request, which 4720 established is free. It waits
    // for a button because it reaches a third party, and a page that calls
    // somebody else's API on every render is a page whose availability is
    // theirs.
    //
    // ⚠️ `Http::preventStrayRequests()` WITH NO FAKE IS THE ASSERTION HERE. A
    // render that asked the vendor fails loudly rather than passing quietly.
    Http::preventStrayRequests();

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->assertSee('Connected at Zernio, unused here')
        ->assertSee('Check what Zernio is billing for')
        ->assertDontSee('In use here');
});

test('an abandoned connect flow is named on the screen with its business and its monthly cost', function (): void {
    // ⛔ **THE OPERATOR-FACING HALF OF 6779(d).** Four accounts connected at
    // Zernio, three of them in use here: 4 × 600 − 1200 = 1200¢ against
    // 3 × 600 − 1200 = 600¢, so the one nobody is using costs $6.00 a month.
    //
    // ⚠️ MUTATION: delete the `$this->reconciliation = [...]` assignment from
    // `GbpGrantRevocations::reconcile()`. This reddens on the first assertion —
    // the section stays on its "not asked" arm.
    gbpScreenFakeVendorAccounts([
        ['_id' => 'acct-screen-bound-1', 'profileId' => 'profile-live', 'platform' => 'googlebusiness'],
        ['_id' => 'acct-screen-bound-2', 'profileId' => 'profile-live', 'platform' => 'googlebusiness'],
        ['_id' => 'acct-screen-bound-3', 'profileId' => 'profile-live', 'platform' => 'googlebusiness'],
        [
            '_id' => 'acct-screen-orphan-9',
            'profileId' => 'profile-abandoned',
            'platform' => 'googlebusiness',
            'username' => 'someones-business',
            'displayName' => 'Someone Real',
        ],
    ]);

    foreach (['acct-screen-bound-1', 'acct-screen-bound-2', 'acct-screen-bound-3'] as $index => $ref) {
        GbpAccountBinding::query()->create([
            'account_ref' => $ref,
            'business_id' => 99201 + $index,
            'location_id' => 88201 + $index,
        ]);
    }

    GbpProfileBinding::query()->create([
        'profile_ref' => 'profile-abandoned',
        'business_id' => 99209,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('reconcile');

    $component->assertSee('Business #99209 started this connection')
        ->assertSee('profile-abandoned')
        ->assertSee('$6.00')
        ->assertSee('Confirm each one before disconnecting anything at Zernio');

    // ⛔ **NO ACCOUNT ID AND NO VENDOR-SUPPLIED NAME ANYWHERE ON THE PAGE.**
    // 4884's discipline — *"the probe returns business references, never account
    // ids, so its caller never holds the value that decides whose listing a call
    // reaches"* — and `ZernioAccount`'s refusal to carry `username` or
    // `displayName` at all. The fixture supplies both, so this asserts the code
    // rather than the fixture.
    $component->assertDontSee('acct-screen-orphan-9')
        ->assertDontSee('acct-screen-bound-1')
        ->assertDontSee('Someone Real')
        ->assertDontSee('someones-business');
});

test('an account on a profile we never recorded is shown as unattributable, not as somebody', function (): void {
    // **Never guessed.** An account connected from Zernio's own console, or one
    // on a profile made before the index existed, cannot be attributed — and
    // naming a business anyway is the sentence that sends an operator to
    // disconnect a stranger's listing from inside a customer's account.
    gbpScreenFakeVendorAccounts([
        ['_id' => 'acct-screen-unknown-1', 'profileId' => 'profile-nobody-recorded', 'platform' => 'googlebusiness'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('reconcile')
        ->assertSee('Not attributable to a business here')
        ->assertSee('profile-nobody-recorded')
        ->assertDontSee('started this connection');
});

test('everything accounted for reads as an all clear, and only after somebody asked', function (): void {
    gbpScreenFakeVendorAccounts([
        ['_id' => 'acct-screen-tidy-1', 'profileId' => 'profile-live', 'platform' => 'googlebusiness'],
    ]);

    GbpAccountBinding::query()->create([
        'account_ref' => 'acct-screen-tidy-1',
        'business_id' => 99301,
        'location_id' => 88301,
    ]);

    Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('reconcile')
        ->assertSee('Everything Zernio has connected is in use here.')
        ->assertDontSee('Check what Zernio is billing for');
});

test('a vendor failure leaves the question unanswered rather than answering it clean', function (): void {
    // ⛔ **THE FAILURE THIS WHOLE SECTION EXISTS TO AVOID, ARRIVING THROUGH A
    // `catch`.** An empty list rendered after a failed call reads as an
    // all-clear, closes the question, and is 4720's permanently-zero meter with
    // a tick beside it. The section must go back to saying *nobody has checked*.
    Http::preventStrayRequests();
    Http::fake(['zernio.com/api/v1/accounts*' => Http::response(['code' => 'server_error'], 500)]);

    $component = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->call('reconcile')
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', gbpToast('error', 'Zernio could not be asked'))
        ->assertSee('Check what Zernio is billing for')
        ->assertDontSee('Everything Zernio has connected is in use here.');

    expect($component->get('reconciliation'))->toBeNull();
});

test('the admin gate is checked before the reconciliation runs', function (): void {
    // ⚠️ **A NEW PUBLIC ENTRY POINT IS A NEW THING TO GATE** (5076, applied to
    // the third one). Called on the **instance** and with the gate redefined
    // after mounting, on this file's own falsifiability note and 398's shape:
    // `mount()` already authorizes, so an unauthorized `Livewire::test()` never
    // reaches the method at all and would report the guard working whether or
    // not `reconcile()` had one. Driving the instance is the only way this
    // assertion is about `reconcile()`.
    //
    // ⚠️ MUTATION: delete `$this->authorize(AdminAccess::GATE);` from
    // `reconcile()`. This reddens — no exception is thrown.
    //
    // ⚠️ `Http::preventStrayRequests()` WITH NO FAKE IS THE SECOND HALF: a
    // refusal that happened *after* the vendor call would fail here rather than
    // pass, which is what "checked before it runs" has to mean on a method whose
    // whole cost is a third party's.
    Http::preventStrayRequests();

    $component = Livewire::actingAs($this->admin)
        ->test(GbpGrantRevocations::class)
        ->instance();

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    expect(fn (): mixed => $component->reconcile(app(ZernioReconciliation::class)))
        ->toThrow(AuthorizationException::class);
});
