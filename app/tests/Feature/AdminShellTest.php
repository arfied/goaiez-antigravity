<?php

declare(strict_types=1);

use App\Enums\AutomationRunStatus;
use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Livewire\Admin\AutomationRuns;
use App\Models\AuditLogEntry;
use App\Models\AutomationRun;
use App\Models\Business;
use App\Models\User;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\ProbeAdminTable;

/*
|--------------------------------------------------------------------------
| The admin shell
|--------------------------------------------------------------------------
|
| `28` Parts 9-11 assume a Filament panel that does not exist and is not the
| stack (decision 87), so the table, filters and bulk actions are ours.
| BUILD-PLAN §4.1: build the shell once here or rows 6, 17 and 22 pay for it
| again.
|
| The tests that matter most are the URL-injection ones. Sort column, direction
| and filters all bind straight from the query string, so they are attacker
| input — a sortable column the screen never renders is a way to infer the
| values it holds.
|
| ⛔ **AND ONE OF THEM IS NOW THE PRODUCTION BUG OF 2026-08-20** (decision
| 5730): every staff sign-in ended in a 500 here, because `AutomationRun` is
| tenant-owned, `rows()` fails closed with no tenant, and platform staff have
| none — while `LoginResponse` sends them to the first thing `AdminNav` grants
| them, which is this screen. Three correct parts composing into a screen that
| could not render for the people it is for. The tests below therefore start
| with **no tenant in context**, which is what a member of staff actually has.
|
*/

/** The account whose runs this screen shows. Its owner is not the admin. */
function adminShellAccount(string $name): Business
{
    $business = Business::provision([
        'owner_user_id' => User::factory()->create(['role' => UserRole::Owner])->id,
        'name' => $name,
    ]);

    // ⛔ `Business::provision()` LEAVES THE TENANT IT CREATED IN CONTEXT and
    // never restores what was there before, which is how a cross-tenant test
    // passes for the wrong reason (multi-tenancy skill, decision 5535). Every
    // caller here re-establishes deliberately or asks with none at all.
    Tenancy::forgetAll();

    return $business;
}

/** A run belonging to one account, filed inside that account's tenancy. */
function adminShellRun(Business $business, string $key, AutomationRunStatus $status): AutomationRun
{
    return Tenancy::actingAs((int) $business->id, fn (): AutomationRun => AutomationRun::create([
        'automation_key' => $key,
        'status' => $status,
        'started_at' => now(),
        'finished_at' => now(),
    ]));
}

/** The screen with an account named, which is the only way it queries anything. */
function adminShellScreen(User $staff, Business $business): Testable
{
    return Livewire::actingAs($staff)
        ->test(AutomationRuns::class)
        ->set('reference', (string) $business->id)
        ->call('resolve');
}

beforeEach(function (): void {
    // Platform staff. The gate asks what the role may do, so the panel is
    // reachable only to the one role that reaches the control plane.
    //
    // ⚠️ THEY OWN NO BUSINESS, which is the fact this screen fell over on and
    // is what every real staff account looks like. `withSecondFactor()` because
    // `28` §9.1's mandatory 2FA refuses an internal account holding none, which
    // the route tests below travel through.
    $this->user = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    $this->business = adminShellAccount('Admin Co');

    foreach ([
        ['gbp_sync', AutomationRunStatus::Succeeded],
        ['review_reply', AutomationRunStatus::Failed],
        ['seo_sweep', AutomationRunStatus::Skipped],
    ] as [$key, $status]) {
        adminShellRun($this->business, $key, $status);
    }

    Tenancy::forgetAll();
});

/*
|--------------------------------------------------------------------------
| The production bug — a screen that could not render for the people it is for
|--------------------------------------------------------------------------
*/

test('a member of staff with no tenant is asked for an account rather than shown a 500', function (): void {
    // ⛔ THE LOAD-BEARING ONE. This is the production journey verbatim: a
    // member of staff, no tenant in context, opening the first screen their nav
    // grants them. It threw `TenantNotResolved` out of `AdminTable::rows()`.
    //
    // ⚠️ MUTATION: put `'runs' => $this->rows()` back into `render()` — the
    // shape this screen shipped with — and this reddens with that exception.
    expect(Tenancy::id())->toBeNull();

    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class)
        ->assertOk()
        ->assertSee('Account number');
});

test('the route a staff sign-in lands on renders as a real page, layout and all', function (): void {
    // ⚠️ Decision 570: admin screens 500'd for weeks with green tests, because
    // Livewire::test() renders no layout and the only real GETs asserted
    // Forbidden or Redirect, which short-circuit in middleware. The 500 this
    // slice fixes is that lesson repeating one layer up — the redirect was
    // asserted and the page it pointed at never was.
    $this->actingAs($this->user)
        ->get(route('admin.automation-runs'))
        ->assertOk()
        ->assertSee('Account number');
});

test('an ambient tenant is never quietly borrowed as the account', function (): void {
    // ⚠️ THE FIX THAT PRESENTS ITSELF AND IS WRONG. Letting `rows()` run under
    // whatever tenant happens to be in context makes the 500 go away, and on a
    // member of staff who also owns a business it silently shows *their own*
    // account's runs on a screen labelled for somebody else's. No account
    // named means no query at all.
    Tenancy::set((int) $this->business->id);

    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class)
        ->assertOk()
        ->assertDontSee('gbp_sync')
        ->assertSee('Account number');
});

/*
|--------------------------------------------------------------------------
| Naming the account
|--------------------------------------------------------------------------
*/

test('naming an account shows its runs and no other account than that one', function (): void {
    // ⛔ THE ISOLATION ASSERTION. `AutomationRun` is RLS-FORCEd on
    // `app.business_id` and the screen reads inside `Tenancy::actingAs()` for
    // the account somebody named, so another account's runs are absent rather
    // than filtered out.
    //
    // ⚠️ MUTATION: drop the `Tenancy::actingAs()` in `runs()` and read under
    // the ambient tenant instead. This reddens on `their_backfill` — which it
    // can only do because the ambient tenant below is deliberately the *other*
    // account.
    $theirs = adminShellAccount('Northside Plumbing');
    adminShellRun($theirs, 'their_backfill', AutomationRunStatus::Succeeded);

    // ⛔ THE OTHER ACCOUNT IS LEFT IN CONTEXT ON PURPOSE. It is the state
    // `Business::provision()` leaves behind of its own accord, and a
    // cross-tenant test that quietly asks as the account it is asserting about
    // passes with the guard deleted (multi-tenancy skill, decision 5535).
    Tenancy::set((int) $theirs->id);

    adminShellScreen($this->user, $this->business)
        ->assertSee('gbp_sync')
        ->assertSee('review_reply')
        ->assertDontSee('their_backfill');
});

test('opening an account is recorded in that account own trail', function (): void {
    // `28` §14.1 attributes internal *reads* of sensitive objects, not only
    // writes, and every other staff surface that names an account files this.
    adminShellScreen($this->user, $this->business);

    $entries = Tenancy::actingAs(
        (int) $this->business->id,
        fn () => AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->get(),
    );

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->actor)->toBe('user:'.$this->user->id)
        ->and($entries->first()->metadata['surface'])->toBe('admin.automation-runs');
});

test('a reference that names nothing is refused the way a wrong number is, and files no row', function (): void {
    // Decision 569: a wrong id and an id belonging to nobody are deliberately
    // indistinguishable. And a refused reference must write nothing, or the
    // attribution rule becomes a way to put rows into an account by guessing
    // at its number.
    $count = fn (): int => Tenancy::actingAs(
        (int) $this->business->id,
        fn (): int => AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count(),
    );

    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class)
        ->set('reference', '999999')
        ->call('resolve')
        ->assertHasErrors('reference')
        ->assertSet('businessId', null);

    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class)
        ->set('reference', 'not-a-number')
        ->call('resolve')
        ->assertHasErrors('reference')
        ->assertSet('businessId', null);

    expect($count())->toBe(0);

    // The positive control on the same component, so the assertion above cannot
    // be green because nothing ever writes.
    adminShellScreen($this->user, $this->business);

    expect($count())->toBe(1);
});

test('the account cannot be set from the browser, so the audit row cannot be walked around', function (): void {
    // ⚠️ WITHOUT `#[Locked]` THE READ AUDIT DOES NOT HOLD: `businessId` would
    // arrive in the update payload, so anybody who can reach this component
    // could point it at any account with `resolve()` never called and nothing
    // recorded anywhere.
    //
    // ⚠️ MUTATION: remove the attribute from `AutomationRuns::$businessId` and
    // this reddens.
    expect(fn (): Testable => Livewire::actingAs($this->user)
        ->test(AutomationRuns::class)
        ->set('businessId', (int) $this->business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('choosing a different account clears the one in view', function (): void {
    adminShellScreen($this->user, $this->business)
        ->assertSee('gbp_sync')
        ->call('clearAccount')
        ->assertSet('businessId', null)
        ->assertDontSee('gbp_sync')
        ->assertSee('Account number');
});

/*
|--------------------------------------------------------------------------
| The shell itself
|--------------------------------------------------------------------------
*/

test('the shell lists rows for the account that was named', function (): void {
    adminShellScreen($this->user, $this->business)
        ->assertOk()
        ->assertSee('gbp_sync')
        ->assertSee('review_reply');
});

test('a screen composed from the shell writes no pagination or sorting of its own', function (): void {
    // The point of the shell. If this ever needs its own paginator, sort state
    // or selection handling, the abstraction is wrong and row 6 pays for it.
    //
    // ⚠️ `resetPage` LEFT THIS LIST ON 2026-08-20 AND THE CLAIM IS NARROWER FOR
    // IT (decision 5736). Naming a second account while on page four of the
    // first must not open it on a page it may not have, so `resolve()` calls
    // `resetPage()` — which is the *shell's own* method, published by
    // `WithPagination` through the trait, exactly as `clearFilters()` is.
    // Calling one is composing the shell; declaring pagination is not, and it
    // is the second that this test is about. `AccountAudit` has called it since
    // the day it was written.
    $source = file_get_contents(app_path('Livewire/Admin/AutomationRuns.php'));

    foreach ([
        '->paginate(',
        'orderBy',
        'use WithPagination',
        'public int $perPage',
        '$sortColumn =',
        '$sortDirection =',
    ] as $shellConcern) {
        expect($source)->not->toContain($shellConcern);
    }

    // The positive half, so the test cannot pass by the screen having stopped
    // composing the shell at all.
    expect($source)->toContain('use AdminTable;');
});

test('sorting is restricted to columns the screen declared', function (): void {
    $component = adminShellScreen($this->user, $this->business);

    // Declared sortable — accepted.
    $component->call('sortBy', 'automation_key')
        ->assertSet('sortColumn', 'automation_key')
        ->assertSet('sortDirection', 'asc');

    // Same column again flips direction rather than re-sorting ascending.
    $component->call('sortBy', 'automation_key')
        ->assertSet('sortDirection', 'desc');

    // Not declared at all — silently ignored, and the previous sort stands.
    // Silence rather than an error: this is reachable by editing the URL, and
    // the difference between "not sortable" and "does not exist" is itself a
    // disclosure.
    $component->call('sortBy', 'idempotency_key')
        ->assertSet('sortColumn', 'automation_key');
});

test('a sort column injected through the URL cannot reach an undeclared column', function (): void {
    // The property binds from the query string, so setting it directly is the
    // realistic attack, not calling sortBy().
    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class, ['sortColumn' => 'idempotency_key'])
        ->set('reference', (string) $this->business->id)
        ->call('resolve')
        ->assertOk()
        // Renders rather than erroring, and the undeclared sort is not applied.
        ->assertSee('gbp_sync');
});

test('a filter value the screen never offered is ignored', function (): void {
    // applyFilters accepts only values present in filters(), so a crafted value
    // cannot become a WHERE clause.
    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class, ['activeFilters' => ['status' => 'not_a_status']])
        ->set('reference', (string) $this->business->id)
        ->call('resolve')
        ->assertOk()
        ->assertSee('gbp_sync')
        ->assertSee('review_reply');
});

test('a filter key the screen never declared is ignored', function (): void {
    Livewire::actingAs($this->user)
        ->test(AutomationRuns::class, ['activeFilters' => ['automation_key' => 'gbp_sync']])
        ->set('reference', (string) $this->business->id)
        ->call('resolve')
        ->assertOk()
        // Undeclared key not applied, so the other rows are still present.
        ->assertSee('review_reply');
});

test('a declared filter does filter', function (): void {
    adminShellScreen($this->user, $this->business)
        ->set('activeFilters', ['status' => AutomationRunStatus::Failed->value])
        ->assertSee('review_reply')
        ->assertDontSee('gbp_sync');
});

test('search matches only searchable columns', function (): void {
    adminShellScreen($this->user, $this->business)
        ->set('search', 'gbp')
        ->assertSee('gbp_sync')
        ->assertDontSee('review_reply');
});

test('search wildcards are escaped rather than interpreted', function (): void {
    // '%' would otherwise match everything, which turns an empty-looking search
    // into a full listing.
    adminShellScreen($this->user, $this->business)
        ->set('search', '%')
        ->assertDontSee('gbp_sync');
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('the admin gate denies every role but platform staff', function (): void {
    // An agency manages tenants; it is not the platform, and that distinction is
    // the difference between one customer's data and everyone's.
    foreach ([UserRole::Agency, UserRole::Owner, UserRole::Manager, UserRole::Staff] as $role) {
        $user = User::factory()->create(['role' => $role]);

        expect($user->can(AdminAccess::GATE))->toBeFalse("role {$role->value} must not reach the panel");
    }

    expect($this->user->can(AdminAccess::GATE))->toBeTrue();
});

test('the admin route is unreachable when the gate denies', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get(route('admin.automation-runs'))
        ->assertForbidden();
});

test('the component refuses the same roles on its own, not only through the route', function (UserRole $role): void {
    // ⚠️ WITHOUT THIS THE TEST ABOVE IS VACUOUS ABOUT THE COMPONENT (398, 630):
    // `can:` refuses during route matching, so `mount()` never runs and
    // widening it would leave the route test green.
    Livewire::actingAs(User::factory()->create(['role' => $role]))
        ->test(AutomationRuns::class)
        ->assertForbidden();
})->with([
    'an owner' => [UserRole::Owner],
    'a support lead' => [UserRole::SupportLead],
]);

test('an unauthenticated visitor cannot reach the admin route', function (): void {
    $this->get(route('admin.automation-runs'))->assertRedirect();
});

test('the shell fails closed with no tenant established', function (): void {
    // Reaching the trait's own read without a tenant must not paginate every
    // tenant's rows. Driven through an authenticated staff user on purpose: an
    // unauthenticated `Livewire::test()` throws out of `mount()`'s authorize()
    // first, which is an exception for the wrong reason.
    Tenancy::forgetAll();

    expect(fn () => Livewire::actingAs($this->user)->test(AutomationRuns::class)->call('rows'))
        ->toThrow(TenantNotResolved::class);
});

test('the shell query cannot be built outside the tenancy that names an account', function (): void {
    // The belt to `rows()`'s brace. `runBulkAction()` also calls `query()`, and
    // it is not wrapped by `runs()` — so a screen returning
    // `AutomationRun::query()` unconditionally would build one under whatever
    // tenant happened to be in context.
    Tenancy::set((int) $this->business->id);

    $component = Livewire::actingAs($this->user)->test(AutomationRuns::class);

    $query = new ReflectionMethod(AutomationRuns::class, 'query');

    expect(fn () => $query->invoke($component->instance()))->toThrow(LogicException::class);
});

/*
|--------------------------------------------------------------------------
| The trait, through its own probe
|--------------------------------------------------------------------------
*/

test('a bulk action resolves ids through the scoped query, not from the browser', function (): void {
    // `selected` arrives from the client, so it is attacker input. Resolving
    // through query() means an id from outside the tenant simply does not come
    // back, rather than being trusted because it was submitted.
    ProbeAdminTable::clearCaptured();

    $mine = Tenancy::actingAs((int) $this->business->id, fn () => AutomationRun::first());

    // The probe is the shell's own double and legitimately runs inside a
    // tenant: it is the trait under test here, not the screen that names one.
    Tenancy::set((int) $this->business->id);

    Livewire::actingAs($this->user)
        ->test(ProbeAdminTable::class)
        ->set('selected', [$mine->getKey(), 999999])
        ->call('runBulkAction', 'capture');

    expect(ProbeAdminTable::$captured)->toBe([$mine->automation_key]);
});

test('a destructive bulk action refuses to run without a typed reason', function (): void {
    // `29` §19.5. Enforced in the component, not only in the UI, because the UI
    // is not the boundary.
    ProbeAdminTable::clearCaptured();

    $mine = Tenancy::actingAs((int) $this->business->id, fn () => AutomationRun::first());

    Tenancy::set((int) $this->business->id);

    $component = Livewire::actingAs($this->user)
        ->test(ProbeAdminTable::class)
        ->set('withDestructive', true)
        ->set('selected', [$mine->getKey()]);

    expect(fn () => $component->call('runBulkAction', 'capture'))
        ->toThrow(RuntimeException::class, 'requires a reason');

    // With a reason, it proceeds.
    $component->set('selected', [$mine->getKey()])
        ->call('runBulkAction', 'capture', 'cleaning up test data');

    expect(ProbeAdminTable::$captured)->toBe([$mine->automation_key]);
});
