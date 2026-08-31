<?php

declare(strict_types=1);

use App\Enums\AutomationMode;
use App\Enums\UserRole;
use App\Livewire\Admin\LocationSettings;
use App\Models\AuditLogEntry;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\AdminNav;
use App\Support\Tenancy;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The rest of the admin shell — forms, detail layouts, role-filtered nav
|--------------------------------------------------------------------------
|
| BUILD-PLAN §4.1: build these once or rows 6, 17 and 22 pay for them again.
|
| Each piece has one failure that matters more than the rest:
|
|   form    writing a field the screen never declared
|   detail  rendering a field the screen never declared — which on
|           oauth_connections means credentials
|   nav     showing a door the viewer cannot open, or worse, enumerating the
|           platform's capabilities to someone who should not know they exist
|
*/

/*
| ⛔ THIS FIXTURE PUTS PLATFORM STAFF IN A STATE NO REAL STAFF ACCOUNT IS EVER
| IN, AND IT IS KEPT DELIBERATELY — decision 5795.
|
| `Business::provision()` leaves its tenant established, and `$this->admin`
| *owns* that business, so every test below this line runs a `super_admin` with
| a tenant in context. **That is precisely the assumption that hid the
| 2026-08-20 sign-in 500** (5730–5739): a tenant-scoped screen reached by staff
| who own nothing threw, and nothing in this file could see it because nothing
| in this file was ever without a tenant.
|
| It stays because it is load-bearing rather than convenient: `Location`,
| `AutopilotSettings` and `AuditLogEntry` are all tenant-scoped under FORCE
| row-level security, and `Livewire::test()` runs no middleware (809), so
| `ResolveTenant` never fires and the form and detail tests have nowhere else to
| get a tenant from.
|
| ⚠️ **WHAT IS NOT ALLOWED IS FOR IT TO BE INVISIBLE.** Every test in this file
| that does *not* need it now calls `Tenancy::forgetAll()` as its first line and
| says so — so the split between "needs a tenant" and "must work without one" is
| a property the suite proves rather than a fact about which fixture happened to
| run. The nav, the gate and the render sweep are all in the second group; the
| form and detail tests are in the first.
*/
beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $this->business = Business::provision([
        'owner_user_id' => $this->admin->id,
        'name' => 'Shell Co',
    ]);

    $this->location = Location::factory()->create(['name' => 'Riverside']);

    $this->settings = AutopilotSettings::create([
        'location_id' => $this->location->id,
        'automation_mode' => AutomationMode::Auto,
    ]);
});

/**
 * The settings screen with its account named, exactly as an operator reaches it.
 *
 * ⛔ **NAMING THE ACCOUNT IS THE SCREEN, NOT A TEST DETAIL** (9236).
 * `LocationSettings` reads one tenant's settings row, its location, its indexing
 * lines and its gating answer, from a path that names only a location — and the
 * people it is built for hold no tenant. So it renders an invitation and runs no
 * query until somebody says whose location this is. Everything below that
 * expects a form or a detail row goes through here.
 *
 * ⚠️ **IT ALSO REMOVES THE FIXTURE'S GRIP ON THESE TESTS.** The `beforeEach`
 * above leaves a tenant in context and 5795 keeps it deliberately; from here on
 * the screen no longer reads it, so what these tests exercise is the account the
 * operator names rather than the one the fixture happened to leave behind.
 */
function locationSettingsFor(User $admin, Business $business, int $locationId): Testable
{
    return Livewire::actingAs($admin)
        ->test(LocationSettings::class, ['location' => $locationId])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors();
}

test('the form renders the fields a screen declared', function (): void {
    // ⚠️ **THIS ASSERTED "How much runs on its own" UNTIL 8582** — the label on
    // `automation_mode`, now removed, because the column has no reader in
    // `app/` and the control promised an operator something no automation
    // honoured. The specimen moved to `brand_voice`: an enum field like the one
    // it replaces, and read by `ReplyGenerator`. Two declared fields are the
    // minimum this subject needs — one alone passes against a layout that
    // happens to print the page title.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->assertOk()
        ->assertSee('How replies sound')
        ->assertSee('Auto-post replies at this star rating or above')
        ->assertSet('state.reply_auto_post_min', 5);
});

test('saving writes only declared fields, whatever else the payload carries', function (): void {
    // `state` arrives from the browser, so a crafted payload can carry any key.
    // Guarded columns stop the worst of it; this is the layer that means a
    // screen showing five fields cannot write a sixth.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->set('state.reply_auto_post_min', 4)
        ->set('state.triage_threshold', 1)
        ->call('save')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $fresh = AutopilotSettings::sole();

        expect($fresh->reply_auto_post_min)->toBe(4)
            // Never declared as a field, so never written — it keeps its default.
            ->and($fresh->triage_threshold)->toBe(3);
    });
});

test('validation comes from the field declaration', function (): void {
    // One declaration drives the control and the rules. Two lists drift, and the
    // direction they drift in is a field that renders but is never validated.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->set('state.reply_auto_post_min', 999)
        ->call('save')
        ->assertHasErrors('state.reply_auto_post_min');

    // ⚠️ **THE ENUM SPECIMEN WAS `automation_mode` UNTIL 8582 AND IS NOW
    // `brand_voice`.** The subject is that `->enum()` on a field declaration
    // produces a validation rule, so it needs *an* enum field rather than that
    // one — and `brand_voice` has a reader, which the field it replaces did not.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->set('state.brand_voice', 'not_a_voice')
        ->call('save')
        ->assertHasErrors('state.brand_voice');
});

test('a save is audited with both sides of the change', function (): void {
    // `29` §2 rule 42, and §19.3 for thresholds specifically — an entry that
    // records only the new value cannot answer what happened.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->set('state.reply_auto_post_min', 3)
        ->call('save');

    Tenancy::actingAs($this->business->id, function (): void {
        $entry = AuditLogEntry::where('action', 'autopilot_settings.updated')->sole();

        expect($entry->metadata['before']['reply_auto_post_min'])->toBe(5)
            ->and($entry->metadata['after']['reply_auto_post_min'])->toBe(3)
            ->and($entry->actor)->toBe('user:'.$this->admin->id);
    });
});

test('a save that changes nothing writes no audit entry', function (): void {
    // An audit log full of "saved, no change" is one nobody reads.
    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->call('save');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::where('action', 'autopilot_settings.updated')->count())->toBe(0);
    });
});

test('the detail layout renders only what a section named', function (): void {
    // The failure this prevents: a layout that iterates getAttributes() and
    // prints every column. On this model that is merely noisy; on
    // oauth_connections it is credentials.
    $component = locationSettingsFor($this->admin, $this->business, $this->location->id);

    // ⚠️ **THE SECOND POSITIVE EXAMPLE WAS "Threshold acknowledged" UNTIL
    // 2026-08-12.** Decision 2074 removed the gating acknowledgement and 2660
    // dropped the column, so that row is gone from `LocationSettings` — and
    // this test sits outside the twenty files that lane ran filtered, so it
    // went red for the first time at the merge, on the full suite. That is the
    // step doing its job: a lane cannot see what it did not think to look at.
    //
    // Replaced rather than dropped. The subject is that the layout prints only
    // what a section *declared*, and it needs at least two declared fields to
    // mean anything — one alone would pass against a layout that happened to
    // print the page title. "Filtering who gets asked" is the Consent section's
    // declared row and survives the removal. Driven red by pointing it at a
    // string the screen does not render.
    $component->assertSee('Riverside')              // declared
        ->assertSee('Filtering who gets asked')     // declared
        ->assertDontSee('hold_window_overrides')    // real column, never declared
        ->assertDontSee('require_confirm_for');
});

test('the Rating row shows the number the Places sweep wrote, and says nothing when nothing has', function (): void {
    // ⛔ **THE COLUMN THIS ROW RENDERS WAS BRIEFED AS WRITERLESS AND IS NOT**
    // (6805). `locations.current_rating` is Google's own star rating — the
    // migration says so — and `CompetitorSignals::refresh()` writes it nightly
    // off `visibility:sync-competitors`. Nothing asserted that this screen
    // showed it, so the claim was true of the tests and unproven of the screen.
    //
    // ⚠️ **AND `LocationFactory` SETS IT TO A RANDOM 3.0–5.0**, which is exactly
    // how a writerless column hides: every test screen shows a plausible
    // rating whether or not anything in `app/` ever wrote one. Both rows below
    // set the value explicitly for that reason.
    //
    // ⚠️ **THE SECOND HALF IS THE ONE THAT MATTERS TO AN OPERATOR** (2496's
    // lesson on a screen): a location we have never asked Google about must not
    // render as a business with no rating. It renders the shared em dash — the
    // same one every unset row on this layout uses — which says "no value" and
    // does not invent a number. Driven red by asserting the em dash against the
    // rated location.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->location->forceFill(['current_rating' => 4.3, 'review_count' => 128])->save();
    });

    locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->assertOk()
        ->assertSee('Rating')
        ->assertSee('4.3')
        ->assertSee('128');

    $unasked = Tenancy::actingAs($this->business->id, fn (): Location => Location::factory()->create([
        'name' => 'Never synced',
        'current_rating' => null,
    ]));

    AutopilotSettings::create([
        'location_id' => $unasked->id,
        'automation_mode' => AutomationMode::Auto,
    ]);

    locationSettingsFor($this->admin, $this->business, $unasked->id)
        ->assertOk()
        ->assertSee('Rating')
        // ⚠️ `assertDontSee` alone would be pinned by nothing (6706), so the
        // positive assertion above carries the claim and this only says the
        // screen has not invented a zero.
        ->assertDontSee('0.0');
});

/**
 * The rendered value of one `DetailSection` entry, read off the live component
 * instance rather than grepped out of HTML.
 *
 * ⚠️ **DELIBERATELY NOT `assertDontSee('0')`.** A location settings screen
 * carries plenty of other digits — ids, thresholds, percentages — and a bare
 * "0" is a substring of most of them. Reading `detailSections()` back through
 * reflection is what `current_rating`'s sibling test settles for
 * (`assertDontSee('0.0')`, a string specific enough that a decimal-cast rating
 * is the only thing that could produce it); an integer count has no such
 * fingerprint, so this reads the value directly instead of guessing at a
 * pattern nothing else on the page will ever coincidentally match.
 */
function detailEntryValue(Testable $component, string $label): mixed
{
    $sections = (new ReflectionMethod($component->instance(), 'detailSections'))
        ->invoke($component->instance());

    foreach ($sections as $section) {
        foreach ($section['entries'] as $entry) {
            if ($entry['label'] === $label) {
                return $entry['value'];
            }
        }
    }

    throw new RuntimeException("No detail entry labelled \"{$label}\".");
}

test('the Reviews row shows the count the Places sweep wrote, and says nothing when nothing has', function (): void {
    // ⛔ **`locations.review_count` WAS `integer` NOT NULL `default(0)` UNTIL
    // WAVE 35 LANE E.** `CompetitorSignals::recordOurOwnRating()` only touches
    // it when Google's own listing carries a rating, so a location whose sync
    // has never run — or whose every sync so far returned a listing with no
    // rating — kept the schema default of `0` for ever, and this screen
    // rendered that `0` with no distinction from a business that genuinely has
    // none. `current_rating` got this exact repair first; this is the sibling
    // column the same writer, the same `forceFill()` and the same docblock
    // point at.
    Tenancy::actingAs($this->business->id, function (): void {
        $this->location->forceFill(['current_rating' => 4.3, 'review_count' => 128])->save();
    });

    $known = locationSettingsFor($this->admin, $this->business, $this->location->id)
        ->assertOk()
        ->assertSee('Reviews')
        ->assertSee('128');

    expect(detailEntryValue($known, 'Reviews'))->toBe(128);

    $unasked = Tenancy::actingAs($this->business->id, fn (): Location => Location::factory()->create([
        'name' => 'Never synced for reviews',
        'current_rating' => null,
        'review_count' => null,
    ]));

    AutopilotSettings::create([
        'location_id' => $unasked->id,
        'automation_mode' => AutomationMode::Auto,
    ]);

    $unread = locationSettingsFor($this->admin, $this->business, $unasked->id)
        ->assertOk()
        ->assertSee('Reviews');

    // The screen has not invented a zero: the entry's own value is null, which
    // is what the shared `{{ $entry['value'] ?? '—' }}` renders as the em dash
    // every other unset row on this layout uses.
    expect(detailEntryValue($unread, 'Reviews'))->toBeNull();
});

test('the nav shows platform staff what they can reach', function (): void {
    // Driven without a tenant, deliberately: this is the state real platform
    // staff are in, and `AdminNav` must answer for one (5796).
    Tenancy::forgetAll();

    $groups = AdminNav::for($this->admin);

    expect($groups)->not->toBeEmpty()
        ->and($groups->flatten()->pluck('route'))->toContain('admin.automation-runs');
});

test('the nav shows nothing to a user who cannot reach the panel', function (): void {
    // Without a tenant, for the sibling above's reason (5796).
    Tenancy::forgetAll();

    // An agency manages tenants; it is not the platform. Hiding is a courtesy —
    // and stops the nav enumerating capabilities to someone who should not know
    // they exist.
    foreach ([UserRole::Agency, UserRole::Owner, UserRole::Manager, UserRole::Staff] as $role) {
        $user = User::factory()->create(['role' => $role]);

        expect(AdminNav::for($user))->toBeEmpty("role {$role->value} should see no admin nav");
    }
});

test('the nav shows nothing to a guest', function (): void {
    // Without a tenant, for the sibling above's reason (5796).
    Tenancy::forgetAll();

    expect(AdminNav::for(null))->toBeEmpty();
});

test('hiding a nav item is not the authorization', function (): void {
    // Without a tenant, for the sibling above's reason (5796) — and here it is
    // more than tidiness: this drives a real HTTP request, so leaving one in
    // context would have the 403 asserted against a connection that a real
    // agency request could never reach.
    Tenancy::forgetAll();

    // The route is still gated. A hidden item typed into the address bar 403s,
    // which is the actual boundary — the nav is presentation.
    $agency = User::factory()->create(['role' => UserRole::Agency]);

    expect(AdminNav::for($agency))->toBeEmpty();

    $this->actingAs($agency)
        ->get(route('admin.automation-runs'))
        ->assertForbidden();
});

test('every declared nav route resolves', function (): void {
    // Without a tenant, for the sibling above's reason (5796).
    Tenancy::forgetAll();

    // ⛔ **THIS TEST AND THE ONE BELOW ARE NAV → ROUTE, AND NEITHER IS A
    // COVERAGE LINT** (9289). Their subject set is `AdminNav::all()` — the
    // declaration itself — so a console route that is in the router and not in
    // the nav contributes nothing to either offender list, and two screens sat
    // with no inbound link anywhere in the tree for months with both of these
    // green. The route → nav direction is
    // `tests/Feature/Architecture/AdminNavTest.php`, derived from
    // `Route::getRoutes()`. **Both directions are needed and neither replaces
    // the other**: this one catches a nav item pointing at nothing, that one
    // catches a screen nothing points at.
    //
    // An item pointing at a route that does not exist is a broken link rather
    // than a security problem, which makes it exactly the sort of thing to catch
    // automatically instead of by clicking.
    foreach (AdminNav::all() as $item) {
        expect(fn () => route($item->route))->not->toThrow(Exception::class);
    }
});

test('every screen the nav grants a member of staff renders for one', function (): void {
    // ⛔ THE TEST THAT WOULD HAVE CAUGHT THE 2026-08-20 PRODUCTION 500, AND ITS
    // ABSENCE IS THE WHOLE DEFECT (decision 5731). The sibling above proves a
    // nav item's route *resolves*, which is a fact about `routes/web.php` and
    // says nothing about whether the screen behind it can render. `LoginResponse`
    // sends staff to the first item this nav grants them, so a nav item that
    // cannot render is not a broken link — it is the landing page of every staff
    // sign-in.
    //
    // ⚠️ **THE FIXTURE IN THIS FILE IS WHY NOTHING HERE SAW IT.** `beforeEach`
    // provisions a business owned by `$this->admin`, so every test above runs
    // with a tenant in context — a state no real platform account is ever in.
    // This one builds its own staff user, who owns nothing, and forgets the
    // tenant first.
    //
    // ⚠️ MUTATION: return `$this->rows()` from `Admin\AutomationRuns::render()`
    // and this reddens naming `admin.automation-runs` and a 500.
    $staff = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Tenancy::forgetAll();

    $items = AdminNav::for($staff)->flatten();

    // 256's shape: a loop over an empty list passes vacuously.
    expect($items)->not->toBeEmpty();

    $broken = [];

    foreach ($items as $item) {
        $status = $this->actingAs($staff)->get(route($item->route))->status();

        if ($status !== 200) {
            $broken[] = $item->route.' → '.$status;
        }
    }

    expect($broken)->toBe([], implode("\n", [
        'These screens are in a member of staff\'s nav and do not render for one:',
        ...array_map(static fn (string $line): string => '  - '.$line, $broken),
        '',
        'A tenant-scoped screen reached by platform staff has to name the account',
        'it reads, and render an invitation when none is named. See',
        'Admin\\AutomationRuns and Admin\\AccountAudit.',
    ]));
});

test('the admin gate now asks the role, and only platform staff pass', function (): void {
    // Without a tenant, for the sibling above's reason (5796). The gate asks the
    // role and nothing else, and a tenant in context must not be what makes that
    // true.
    Tenancy::forgetAll();

    // Wired here rather than in FOUND-04, which landed before AdminAccess
    // existed. The placeholder denied everyone; this is the real predicate.
    expect($this->admin->can(AdminAccess::GATE))->toBeTrue();

    foreach ([UserRole::Agency, UserRole::Owner, UserRole::Manager, UserRole::Staff] as $role) {
        $user = User::factory()->create(['role' => $role]);

        expect($user->can(AdminAccess::GATE))->toBeFalse("role {$role->value} must not reach the panel");
    }
});
