<?php

declare(strict_types=1);

use App\Enums\AutomationMode;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\LocationSettings;
use App\Livewire\Admin\ReviewQueue;
use App\Models\AuditLogEntry;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The two admin screens that are keyed on a location — wave 24 lane E
|--------------------------------------------------------------------------
|
| ⛔ **BOTH ANSWERED A 500 TO THEIR ENTIRE INTENDED AUDIENCE FROM THE DAY THEY
| SHIPPED, AND EVERY TEST OF THEM WAS GREEN THROUGHOUT** (9236).
| `Admin\LocationSettings` and `Admin\ReviewQueue` are behind `AdminAccess::GATE`,
| which is `super_admin` alone; platform staff own no business, so `ResolveTenant`
| establishes no tenant for them, so the first tenant-scoped read on either
| screen threw `TenantNotResolved` — for which `bootstrap/app.php` registers no
| renderer.
|
| ⛔ **THE REASON NOBODY SAW IT IS THIS FILE'S SUBJECT.** Every existing test of
| either screen reaches it through `Livewire::test()` wrapped in
| `Tenancy::actingAs()`, and **`Livewire::test()` runs no middleware** (809), so
| not one of them was ever in the state a real member of staff is in. The only
| two HTTP drives that existed used a `staff` and an `owner` actor, both of whom
| the ability gate refuses — *a test named for a population its own fixture does
| not build*. **So every drive below that means "a real member of staff" is an
| HTTP request**, and the component drives are only for what a request cannot
| reach.
|
| ⚠️ **`withSecondFactor()` IS LOAD-BEARING AND IS 398's SHAPE.**
| `RequiresTwoFactor` runs on the whole web group and **redirects** an internal
| account holding no factor, so a bare `SuperAdmin` fixture would be bounced
| before reaching a line of either screen and every assertion here would be green
| against an application that had never been asked the question. The test named
| *the second factor is what these drives clear* keeps that honest by asserting
| the bounce happens when the factor is missing.
|
*/

/**
 * A customer account with one location and its settings row, and no tenant left
 * in context afterwards.
 *
 * ⚠️ `Business::provision()` leaves *its* tenant established (5795), which is
 * the harness trap that hid the 2026-08-20 sign-in 500. Every helper here ends
 * with `Tenancy::forgetAll()` so that a test asserting the tenantless state gets
 * one rather than whichever account was built last.
 *
 * @return array{0: Business, 1: Location}
 */
function adminLocationAccount(string $name = 'Ledger Diner'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $business = Business::provision(['owner_user_id' => $owner->id, 'name' => $name]);

    /** @var Location $location */
    $location = Tenancy::actingAs(
        (int) $business->id,
        fn (): Location => Location::factory()->create(['name' => $name.' — Riverside']),
    );

    Tenancy::actingAs((int) $business->id, function () use ($location): void {
        AutopilotSettings::create([
            'location_id' => $location->id,
            'automation_mode' => AutomationMode::Auto,
        ]);
    });

    Tenancy::forgetAll();

    return [$business, $location];
}

/**
 * Platform staff exactly as production holds them: no business, a second factor.
 */
function adminLocationStaff(): User
{
    $staff = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Tenancy::forgetAll();

    return $staff;
}

/**
 * One first-party review waiting on a decision, written as the location's tenant.
 */
function adminLocationPendingReview(Location $location, string $comment): Review
{
    return Tenancy::actingAs((int) $location->business_id, fn (): Review => Review::factory()->create([
        'location_id' => $location->id,
        'source' => ReviewSource::FirstParty,
        'status' => ReviewStatus::Pending,
        'rating' => 4,
        'comment' => $comment,
        'reviewer_name' => 'Sam Rivers',
        'display_on_website' => false,
        'flagged_at' => null,
        'moderation_flags' => [],
    ]));
}

/*
|--------------------------------------------------------------------------
| Over HTTP, as the people these screens are for
|--------------------------------------------------------------------------
*/

test('a member of platform staff opens the settings screen and is asked which account', function (): void {
    [, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    // The state the whole defect turns on, asserted rather than assumed.
    expect(Tenancy::id())->toBeNull();

    $this->actingAs($staff)
        ->get(route('admin.location-settings', ['location' => $location->id]))
        // ⛔ 200 IS THE CLAIM. It was `TenantNotResolved` — a 500 — and it is
        // also the anti-vacuity floor for the second factor: an actor without
        // one is redirected, which is not 200.
        ->assertOk()
        ->assertSee('Account number')
        // Nothing of the account is on the page until somebody names it.
        ->assertDontSee('How replies sound');
});

test('a member of platform staff opens the review queue and is asked which account', function (): void {
    [, $location] = adminLocationAccount();
    $staff = adminLocationStaff();
    adminLocationPendingReview($location, 'Good, not perfect.');

    Tenancy::forgetAll();

    $this->actingAs($staff)
        ->get(route('admin.review-queue', ['location' => $location->id]))
        ->assertOk()
        ->assertSee('Account number')
        // ⛔ AND THE REVIEW IS NOT ON IT. A screen that stopped crashing by
        // reading whatever tenant was in context would satisfy `assertOk()`.
        ->assertDontSee('Good, not perfect.');
});

test('the second factor is what these drives clear, so the assertions above are not vacuous', function (): void {
    // ⛔ 398's SHAPE, PINNED. `RequiresTwoFactor` runs on the whole web group,
    // so a `super_admin` fixture built without `withSecondFactor()` never
    // reaches a line of either screen — and every assertion in this file would
    // pass against an application that had never been asked the question.
    [, $location] = adminLocationAccount();

    $unenrolled = User::factory()->role(UserRole::SuperAdmin)->create();

    Tenancy::forgetAll();

    $this->actingAs($unenrolled)
        ->get(route('admin.location-settings', ['location' => $location->id]))
        ->assertRedirect(route('two-factor.setup'));
});

test('a location id that belongs to nobody is still answered rather than crashed', function (): void {
    // The census drives every route with the parameter `1`, and a screen that
    // resolved the path segment before naming an account would 404 or 500 here.
    // Nothing is read, so there is nothing to miss.
    $staff = adminLocationStaff();

    $this->actingAs($staff)
        ->get(route('admin.review-queue', ['location' => 987654]))
        ->assertOk()
        ->assertSee('Account number');
});

/*
|--------------------------------------------------------------------------
| The path segment — 9286, and the one thing reading could not settle
|--------------------------------------------------------------------------
*/

test('a path segment that is not a location id is refused by the router rather than crashed', function (): void {
    // ⛔ **EVERY ONE OF THESE WAS A 500 UNTIL 2026-08-24, AND NO TEST IN THIS
    // REPOSITORY HAD EVER MADE THE REQUEST** (9286). Neither route constrained
    // `{location}`, both components declare `mount(int $location)`, and
    // Livewire calls that through `BoundMethod` — a vendor file with no
    // `strict_types` — so an uncoercible segment raised
    // `TypeError: …mount(): Argument #1 ($location) must be of type int, string
    // given` with nothing registered to render it. `TenantlessAccessTest`
    // substitutes `1` for every parameter, so the census drove the one value
    // that could not fail.
    //
    // ⚠️ **404 IS THE CLAIM AND IT IS DELIBERATELY NOT 403.** `CLAUDE.md`
    // records that *"fails closed"* meant a 403 in twenty-eight files and a 500
    // page in six, with nothing in the tree telling them apart. This is neither
    // of those: `/admin/locations/abc/settings` is not an address this
    // application has, so the router declining to match it is the honest
    // answer, and it is reached before any component is constructed.
    //
    // ⚠️ MUTATION: drop either `->where('location', …)` in `routes/web.php` and
    // the first two rows below fail — as **500**, naming the `TypeError`.
    $staff = adminLocationStaff();

    foreach (['admin.location-settings', 'admin.review-queue'] as $name) {
        $path = str_contains($name, 'settings') ? 'settings' : 'reviews';

        foreach ([
            // A word. The plain case, and the one that was measured at 500.
            'abc',
            // A number with a tail — `(int) '1abc'` is 1, so this is the shape
            // that would coerce silently to somebody else's location if the
            // constraint were a cast rather than a match.
            '1abc',
            // ⛔ **THE CASE `whereNumber()` WOULD NOT HAVE CAUGHT.** That helper
            // is `[0-9]+`; this is twenty-two digits, it matched, and the
            // coercion overflowed `int` — a second 500 behind the first fix.
            '9999999999999999999999',
            // Negative ids do not exist here, and `-1` is not `[0-9]+` either.
            '-1',
            // The literal word, which is what a hand-edited URL tends to carry.
            'null',
        ] as $segment) {
            $this->actingAs($staff)
                ->get('/admin/locations/'.$segment.'/'.$path)
                ->assertNotFound();
        }
    }
});

test('the constraint refuses the segment and never the id, so a real location still opens', function (): void {
    // 256's floor under the test above: a pattern narrow enough to refuse every
    // segment would make all ten of those assertions pass and the screens
    // unreachable. Eighteen digits is the widest span that always fits
    // `PHP_INT_MAX`, so it is asserted rather than assumed.
    [, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    $this->actingAs($staff)
        ->get(route('admin.location-settings', ['location' => $location->id]))
        ->assertOk();

    foreach (['0', '1', '999999999999999999'] as $segment) {
        $this->actingAs($staff)
            ->get('/admin/locations/'.$segment.'/settings')
            ->assertOk()
            ->assertSee('Account number');
    }
});

/*
|--------------------------------------------------------------------------
| Naming the account — 5732, and the row 5734 requires
|--------------------------------------------------------------------------
*/

test('naming the account opens the settings, and the read is filed in that account\'s own trail', function (): void {
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->assertSet('businessId', (int) $business->id)
        ->assertSee('Ledger Diner')
        ->assertSee('How replies sound');

    Tenancy::actingAs((int) $business->id, function () use ($staff, $location): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->actor)->toBe('user:'.$staff->id)
            ->and($entry->metadata['surface'])->toBe('admin.location-settings')
            // The location is in the row because the row is the record of what
            // was opened, and this screen opens one location of the account.
            ->and($entry->metadata['location_id'])->toBe($location->id);
    });
});

test('naming the account opens the queue, and the read is filed in that account\'s own trail', function (): void {
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();
    adminLocationPendingReview($location, 'Good, not perfect.');

    Tenancy::forgetAll();

    Livewire::actingAs($staff)
        ->test(ReviewQueue::class, ['location' => $location->id])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->assertSee('Good, not perfect.');

    Tenancy::actingAs((int) $business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->metadata['surface'])->toBe('admin.review-queue');
    });
});

test('one entry per resolved lookup, never per render', function (): void {
    // ⚠️ 5734. Livewire re-renders on every property update, and a row per
    // keystroke is a log nobody can read — which is its own kind of unaudited.
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->call('$refresh')
        ->set('state.reply_auto_post_min', 4)
        ->call('$refresh');

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(1);
    });
});

test('a number belonging to nobody is refused and writes nothing anywhere', function (): void {
    // ⚠️ 5734's other half: a miss writes nothing, or the attribution rule
    // becomes a way to put rows into an account by guessing at its number.
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('reference', (string) ($business->id + 5000))
        ->call('resolve')
        ->assertHasErrors('reference')
        ->assertSet('businessId', null)
        ->assertDontSee('How replies sound');

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(0);
    });
});

test('the queue refuses an account that does not own the location in the path', function (): void {
    // The pairing, on the smaller screen. `ReviewHubToggleTest` holds the same
    // claim for the settings form, with the row-level-security probe beside it.
    [$accountA] = adminLocationAccount('Ledger Diner');
    [, $locationB] = adminLocationAccount('Harbour Dental');
    $staff = adminLocationStaff();

    adminLocationPendingReview($locationB, 'Harbour words.');

    Tenancy::forgetAll();

    Livewire::actingAs($staff)
        ->test(ReviewQueue::class, ['location' => $locationB->id])
        ->set('reference', (string) $accountA->id)
        ->call('resolve')
        ->assertHasErrors('reference')
        ->assertSet('businessId', null)
        ->assertDontSee('Harbour words.');
});

/*
|--------------------------------------------------------------------------
| 5733 — the ambient tenant grants nothing
|--------------------------------------------------------------------------
*/

test('a tenant left in context does not open the queue, and does not decide anything either', function (): void {
    // ⛔ **THE FIX THAT PRESENTS ITSELF AND IS WRONG** (5733): letting the reads
    // run under whatever tenant is in context makes the 500 go away and
    // silently shows decision 621's `super_admin`-who-also-owns-a-business
    // *their own* account's reviews on a screen labelled for somebody else's.
    // This test establishes exactly that state and asserts the screen shows
    // none of it.
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();
    $review = adminLocationPendingReview($location, 'Good, not perfect.');

    Tenancy::actingAs((int) $business->id, function () use ($staff, $location, $review): void {
        $component = Livewire::actingAs($staff)
            ->test(ReviewQueue::class, ['location' => $location->id])
            ->assertOk()
            ->assertSee('Account number')
            ->assertDontSee('Good, not perfect.');

        // And the actions are refused loudly rather than acting on the ambient
        // tenant — the buttons only exist on a screen that named an account, so
        // this state is a hand-posted update.
        expect(fn () => $component->call('approve', $review->id))->toThrow(LogicException::class);
    });

    // ⚠️ READ BACK INSIDE THE TENANCY, BECAUSE `refresh()` DOES NOT SCOPE AND
    // THE DATABASE DOES. `Model::refresh()` builds a query without global
    // scopes, so outside a tenancy `reviews` — `ENABLE`+`FORCE` row-level
    // security — returns nothing and the read is a `ModelNotFoundException`
    // rather than the assertion this test is making.
    Tenancy::actingAs((int) $review->business_id, function () use ($review): void {
        expect(Review::query()->findOrFail($review->id)->display_on_website)->toBeFalse();
    });
});

test('a tenant left in context does not open the settings, and cannot be saved through', function (): void {
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    Tenancy::actingAs((int) $business->id, function () use ($staff, $location): void {
        $component = Livewire::actingAs($staff)
            ->test(LocationSettings::class, ['location' => $location->id])
            ->assertOk()
            ->assertSee('Account number')
            ->assertDontSee('How replies sound');

        expect(fn () => $component->set('state.reply_auto_post_min', 1)->call('save'))
            ->toThrow(LogicException::class);
    });

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AutopilotSettings::query()->sole()->reply_auto_post_min)->toBe(5);
    });
});

/*
|--------------------------------------------------------------------------
| The account cannot be moved from the browser
|--------------------------------------------------------------------------
*/

test('the account cannot be set from the browser on either screen', function (): void {
    // ⚠️ 5735's shape: without `#[Locked]` this arrives in the update payload,
    // so anybody who can reach the component points it at any account with
    // `resolve()` never called and nothing recorded anywhere.
    //
    // ⚠️ MUTATION: remove the attribute from either `$businessId` and this
    // reddens.
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    expect(fn () => Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('businessId', (int) $business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::actingAs($staff)
        ->test(ReviewQueue::class, ['location' => $location->id])
        ->set('businessId', (int) $business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the location cannot be set from the browser on either screen', function (): void {
    // ⚠️ THE RECORD OF WHAT WAS READ IS WHAT THIS PROTECTS, RATHER THAN THE
    // TENANT BOUNDARY. `Tenancy::actingAs()` holds either way; what an unlocked
    // path segment would allow is moving an opened account's screen to a
    // different location of that account while the `business.viewed_by_staff`
    // row still names the one the operator opened — and, on the form, saving
    // state read from the first record onto the second.
    //
    // ⚠️ MUTATION: remove the attribute from either `$locationId` and this
    // reddens.
    [, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    expect(fn () => Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('locationId', $location->id + 1))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::actingAs($staff)
        ->test(ReviewQueue::class, ['location' => $location->id])
        ->set('locationId', $location->id + 1))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

/*
|--------------------------------------------------------------------------
| The work the screens exist to do, done by somebody with no tenant
|--------------------------------------------------------------------------
*/

test('staff with no tenant can publish a waiting review into the named account', function (): void {
    // ⛔ **THE SENTENCE `ReviewQueue`'s DOCBLOCK WROTE ITSELF TO PREVENT** — *"a
    // queue with no way to approve would leave every non-5-star review at
    // pending forever"* — was true anyway for as long as the screen 500'd, and
    // `ReviewRouter` auto-approves 5-star alone. This is the first test in the
    // repository that moves a review with no tenant in context.
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();
    $review = adminLocationPendingReview($location, 'Good, not perfect.');

    Tenancy::forgetAll();

    Livewire::actingAs($staff)
        ->test(ReviewQueue::class, ['location' => $location->id])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->call('approve', $review->id)
        ->assertHasNoErrors();

    Tenancy::actingAs((int) $business->id, function () use ($review): void {
        $fresh = Review::query()->findOrFail($review->id);

        expect($fresh->display_on_website)->toBeTrue()
            ->and($fresh->status)->toBe(ReviewStatus::Approved);
    });
});

test('staff with no tenant can take a review hub down in the named account', function (): void {
    [$business, $location] = adminLocationAccount();
    $staff = adminLocationStaff();

    Livewire::actingAs($staff)
        ->test(LocationSettings::class, ['location' => $location->id])
        ->set('reference', (string) $business->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->set('state.update_review_hub', false)
        ->call('save')
        ->assertHasNoErrors();

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AutopilotSettings::query()->sole()->update_review_hub)->toBeFalse();

        // The save's own audit row lands in the account it was made in (419),
        // beside the read that opened it.
        expect(AuditLogEntry::query()->where('action', 'autopilot_settings.updated')->count())->toBe(1);
    });
});
