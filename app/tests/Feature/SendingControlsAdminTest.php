<?php

declare(strict_types=1);

use App\Console\Commands\WatchPlatformComplaintRate;
use App\Enums\OutreachChannel;
use App\Enums\SendingPauseReason;
use App\Enums\SendRefusalReason;
use App\Enums\UserRole;
use App\Livewire\Admin\PlatformSettings;
use App\Livewire\Admin\SendingControls;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\RegistryChange;
use App\Models\SendingPause;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\PlatformComplaintRate;
use App\Services\Messaging\PlatformRateSample;
use App\Services\Messaging\SendingGuard;
use App\Services\Messaging\SendingHealth;
use App\Services\Tenant\TenantPause;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\AdminNav;
use App\Support\Admin\NavItem;
use App\Support\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The kill-switch screen — T137 `SL-8`, decisions 2630–2659
|--------------------------------------------------------------------------
|
| Both switches worked and neither could be operated. `SendingGuard::resume()`
| had NO caller anywhere in `app/`, `pause()`'s only caller was the guard's own
| automatic trip, and `SendingPauseReason::isSelfClearing()` is false for every
| case — so a tenant the platform stopped by itself could be released only by
| direct SQL. `history()` and `SendingRates` had no consumer at all.
|
| ⚠️ THE ROUTE GATE IS DRIVEN THROUGH A REAL GET, NOT THROUGH `Livewire::test()`.
| Decision 809: a Livewire component test runs NO middleware, so an authorization
| test through it says nothing about the route. Both are asserted, separately,
| and the real GET also proves the screen renders inside a layout at all — 570's
| defect, which no component test could see.
|
| ⚠️ NO TEST HERE SEEDS `sending_health_windows`. That is deliberate and it is
| the point of the rate assertions: 2498 records that the platform-halt slice
| *"read the same empty counters and its tests seeded them by hand, which is
| precisely how a writerless control survives review"*. What is asserted is that
| an unwritten counter renders as an absence.
|
*/

beforeEach(function (): void {
    // ⚠️ THE REAL ROLE, NOT `Gate::define(..., true)`. `PhiTenantsAdminTest`
    // stubs the gate open in its beforeEach, which is fine there and would be
    // wrong here: half of this file's subject is who may throw a kill switch,
    // and a stubbed gate answers "yes" for every role including the tenant's
    // own owner. Every test below goes through `AdminAccess`'s real predicate.
    $this->admin = User::factory()->create([
        'name' => 'Platform Staff',
        'role' => UserRole::SuperAdmin,
    ]);

    Tenancy::forgetAll();

    $this->business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Ledger Diner',
    ]);

    Tenancy::forgetAll();
});

/**
 * The toast payload as the browser receives it.
 *
 * ⚠️ THE PAYLOAD, NOT THE FACADE, for `PhiTenantsAdminTest`'s reason:
 * `Toaster::fake()`'s collector is drained by the Livewire relay on dehydrate,
 * so a test holding the fake finds it empty. And the type is half the assertion
 * — `success()` and `error()` differ only in that field, so a check on the
 * message alone cannot tell a refusal from a confirmation.
 *
 * @return callable(string, array<string, mixed>): bool
 */
function sendingToast(string $type, string $contains): callable
{
    return fn (string $name, array $params): bool => ($params['type'] ?? null) === $type
        && is_string($params['message'] ?? null)
        && str_contains($params['message'], $contains);
}

/**
 * The screen, with a business already in view.
 *
 * ⚠️ `Testable` IS IMPORTED RATHER THAN WRITTEN OUT, and the reason is a real
 * error this file hit: `use Livewire\Livewire;` aliases `Livewire` to the
 * facade, so a return type spelled `Livewire\Features\SupportTesting\Testable`
 * resolves to `Livewire\Livewire\Features\…` and every test using this helper
 * dies with a TypeError naming a class that does not exist.
 */
function sendingScreenFor(User $admin, Business $business): Testable
{
    return Livewire::actingAs($admin)
        ->test(SendingControls::class)
        ->set('lookup', (string) $business->id)
        ->call('lookUp');
}

test('an operator stops a business sending, and the very next send is refused', function (): void {
    // ⚠️ THE WHOLE REASON THIS SCREEN EXISTS, AND THE ASSERTION IS ON THE SEND
    // PATH RATHER THAN ON THE ROW. A test that only checked `sending_pauses`
    // would pass against a screen writing a row nothing reads — which is exactly
    // the shape 2450 found when the campaign runner consulted a different switch
    // entirely, with a green suite over it.
    //
    // ⚠️ MUTATION: delete the `$guard->pause(...)` call from
    // SendingControls::pauseTenant(). This reddens on the refusal.
    sendingScreenFor($this->admin, $this->business)
        ->set('pauseReason', SendingPauseReason::Compliance->value)
        ->set('pauseNote', 'Carrier escalation naming this list.')
        ->call('pauseTenant')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $guard = app(SendingGuard::class);

        expect($guard->isPaused())->toBeTrue()
            ->and($guard->refusalFor(OutreachChannel::Sms))->toBe(SendRefusalReason::TenantPaused);

        $pause = SendingPause::query()->sole();

        expect($pause->reason)->toBe(SendingPauseReason::Compliance)
            ->and($pause->tripped_by)->toBe('user:'.$this->admin->id)
            ->and($pause->note)->toBe('Carrier escalation naming this list.')
            // An operator pause carries no rate, because no rate caused it. The
            // automatic trip is the only thing that fills this in.
            ->and($pause->observed_rate_bp)->toBeNull();
    });
});

test('the release works and is attributed to the person who made it', function (): void {
    // 2408: release is a person's act, on a screen, with an actor. `released_by`
    // is the one fact this row exists to hold about the release, and the release
    // note is the second — 2474's *"what somebody wrote when they decided it was
    // safe to start again"*.
    //
    // ⚠️ MUTATION: delete the `$guard->resume(...)` call from
    // SendingControls::releaseTenant(). This reddens on `isPaused()`.
    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::ComplaintRate, SendingGuard::SYSTEM_ACTOR, 900));

    sendingScreenFor($this->admin, $this->business)
        ->set('releaseNote', 'List cleaned and the import withdrawn.')
        ->call('releaseTenant')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingGuard::class)->isPaused())->toBeFalse()
            ->and(app(SendingGuard::class)->refusalFor(OutreachChannel::Sms))->toBeNull();

        $pause = SendingPause::query()->sole();

        expect($pause->released_by)->toBe('user:'.$this->admin->id)
            ->and($pause->release_note)->toBe('List cleaned and the import withdrawn.')
            ->and($pause->released_at)->not->toBeNull();

        $entry = AuditLogEntry::query()->where('action', 'sending.resumed')->sole();

        expect($entry->actor)->toBe('user:'.$this->admin->id)
            ->and($entry->entity_type)->toBe(SendingPause::class)
            ->and($entry->entity_id)->toBe($pause->id);
    });
});

test('the release cannot be attributed to anybody but the signed-in person', function (): void {
    // ⚠️ "IMPOSSIBLE WITHOUT A RECORDED ACTOR" HAS TWO HALVES AND ONLY ONE IS
    // ABOUT THE DATABASE. The database half is below. This is the screen half:
    // no property on this component reaches `released_by`, so a client that
    // forges every writable field on the payload still cannot name who decided
    // sending was safe again. Without it, the actor would be one `wire:model`
    // away from being the client's to choose — and a release attributed to
    // somebody who did not make it is worse than an unattributed one, because it
    // reads as evidence.
    //
    // ⚠️ MUTATION: add `public string $actor = '';` to SendingControls and pass
    // it to `resume()` in place of `$this->actor()`. This reddens.
    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::Operator, 'user:99'));

    sendingScreenFor($this->admin, $this->business)
        // Every writable string on the component, set to a forged actor.
        ->set('lookup', (string) $this->business->id)
        ->set('pauseReason', SendingPauseReason::Operator->value)
        ->set('pauseNote', 'user:1')
        ->set('releaseNote', 'user:1')
        ->call('releaseTenant')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        expect(SendingPause::query()->sole()->released_by)->toBe('user:'.$this->admin->id);
    });
});

test('the database refuses a release that names nobody, whatever a screen does', function (): void {
    // The other half, and it is the one that survives this screen being
    // rewritten. `sending_pauses` has a CHECK requiring a release to be
    // attributed, so an unattributed release is refused beneath every layer
    // above it — 314–316's rule that layers are not substitutes.
    //
    // Driven at the database rather than through the component, because the
    // component cannot produce this call at all: that is 398's shape, and a test
    // named for the constraint has to reach the constraint.
    Tenancy::actingAs($this->business->id, function (): void {
        $pause = app(SendingGuard::class)->pause(SendingPauseReason::Operator, 'user:3');

        expect(fn () => DB::transaction(fn () => SendingPause::query()
            ->whereKey($pause->getKey())
            ->update(['released_at' => now(), 'release_note' => 'no actor'])))
            ->toThrow(QueryException::class);

        expect(app(SendingGuard::class)->isPaused())->toBeTrue();
    });
});

test('a released pause survives its own release and is still on the screen', function (): void {
    // 2119(a), driven through the surface rather than through the service.
    // `resume()` called `$pause->delete()` until 2470, so restarting a tenant
    // destroyed the reason, the actor that tells 2102's automatic trip from an
    // operator, and `observed_rate_bp` — a snapshot that CANNOT be recomputed,
    // because the window has rolled by the time anybody asks.
    //
    // ⚠️ THIS IS THE HALF A SERVICE TEST CANNOT MAKE: the row surviving is only
    // worth anything if a person can read it, and until this screen existed
    // nothing in `app/` called `history()` at all (2478).
    //
    // ⚠️ MUTATION: change `SendingGuard::history()`'s query to
    // `->whereNull('released_at')`. This reddens on the rendered history — the
    // live-state assertions above stay green, which is the finding in miniature.
    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::ComplaintRate, SendingGuard::SYSTEM_ACTOR, 900, 'Tripped overnight.'));

    $screen = sendingScreenFor($this->admin, $this->business)
        ->set('releaseNote', 'Import withdrawn.')
        ->call('releaseTenant')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $pause = SendingPause::query()->sole();

        expect($pause->reason)->toBe(SendingPauseReason::ComplaintRate)
            ->and($pause->tripped_by)->toBe(SendingGuard::SYSTEM_ACTOR)
            ->and($pause->observed_rate_bp)->toBe(900)
            ->and($pause->note)->toBe('Tripped overnight.')
            ->and($pause->release_note)->toBe('Import withdrawn.')
            ->and($pause->isLive())->toBeFalse();
    });

    // And it is legible: the incident, the rate that caused it, who stopped
    // them, who started them again, and both notes.
    $screen
        ->assertSee('Sending running')
        ->assertSee('Every time they were stopped')
        ->assertSee('Released')
        ->assertSee('Too many complaints')
        ->assertSee('Tripped overnight.')
        ->assertSee('Import withdrawn.')
        // The snapshot that cannot be worked out again afterwards.
        ->assertSee('9.0%');
});

test('an operator cannot stop a business as though the platform had done it', function (): void {
    // ⚠️ `ComplaintRate` IS THE ENUM'S OWN *"only one that happens without a
    // human"*. An operator choosing it would forge a machine measurement: the
    // row would assert a threshold was crossed with `observed_rate_bp` null, and
    // 2476's incident series would stop telling the automatic trip apart from a
    // person — which is the one distinction `tripped_by` and `reason` exist to
    // carry.
    //
    // ⚠️ DRIVEN THROUGH `call()` RATHER THAN BY CHECKING THE SELECT, because a
    // Livewire action is callable whatever rendered it (391). The markup not
    // offering it is a courtesy; the validator is the rule.
    //
    // ⚠️ MUTATION: drop `->except(SendingPauseReason::ComplaintRate)` from the
    // rule in SendingControls::pauseTenant(). This reddens on both halves.
    sendingScreenFor($this->admin, $this->business)
        ->set('pauseReason', SendingPauseReason::ComplaintRate->value)
        ->set('pauseNote', 'Pretending to be the machine.')
        ->call('pauseTenant')
        ->assertHasErrors('pauseReason')
        // And the option is not offered either.
        ->assertDontSee('Too many complaints');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(SendingPause::query()->count())->toBe(0);
    });
});

test('a reason for stopping is required and bounded before it reaches a permanent row', function (): void {
    // The row is never deleted now (2119(a)), so an unbounded box lets whoever
    // fills it paste a document into a record nothing prunes — 2475's argument,
    // and the same 500 the service truncates at and the database refuses past.
    // The screen refuses it earlier, where the operator can see which box is
    // wrong.
    sendingScreenFor($this->admin, $this->business)
        ->set('pauseNote', '   ')
        ->call('pauseTenant')
        ->assertHasErrors(['pauseNote' => 'required'])
        // ⚠️ ON THE RENDERED RESPONSE, NOT ONLY IN THE BAG. A validation message
        // that lands in an error bag no part of the screen renders makes the
        // button do nothing at all, silently — the regression 620's test in
        // `PhiTenantsAdminTest` exists to pin, one screen over.
        //
        // ⚠️ MUTATION: delete the `@error('pauseNote')` block from the blade.
        // This reddens on the assertSee while assertHasErrors stays green.
        ->assertSee('The note field is required.')
        ->set('pauseNote', str_repeat('a', 501))
        ->call('pauseTenant')
        ->assertHasErrors(['pauseNote' => 'max']);

    Tenancy::actingAs($this->business->id, function (): void {
        expect(SendingPause::query()->count())->toBe(0);
    });
});

test('a reason for starting again is required, and rendered when it is missing', function (): void {
    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::Operator, 'user:3'));

    sendingScreenFor($this->admin, $this->business)
        ->set('releaseNote', '  ')
        ->call('releaseTenant')
        ->assertHasErrors(['releaseNote' => 'required'])
        ->assertSee('The note field is required.');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingGuard::class)->isPaused())->toBeTrue();
    });
});

test('an operator stopping a business that is already stopped is told so, not silently ignored', function (): void {
    // `pause()` is idempotent and returns the existing row (2472), so without
    // this refusal the screen would report "Sending stopped for this business"
    // over a call that wrote nothing — outcome language naming an outcome that
    // did not occur, which is the defect `PhiTenants::raiseToPhi()` shipped with.
    //
    // ⚠️ MUTATION: delete the `$guard->isPaused()` refusal from
    // SendingControls::pauseTenant(). This reddens on the toast TYPE: the same
    // call then dispatches a `success`.
    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::ComplaintRate, SendingGuard::SYSTEM_ACTOR, 900));

    sendingScreenFor($this->admin, $this->business)
        ->set('pauseReason', SendingPauseReason::Billing->value)
        ->set('pauseNote', 'Double-clicked.')
        ->call('pauseTenant')
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', sendingToast('error', 'already stopped'));

    Tenancy::actingAs($this->business->id, function (): void {
        // The original incident is untouched — the 3am row keeps its rate.
        $pause = SendingPause::query()->sole();

        expect($pause->reason)->toBe(SendingPauseReason::ComplaintRate)
            ->and($pause->observed_rate_bp)->toBe(900);
    });
});

test('releasing a business that is not stopped is refused rather than reported as done', function (): void {
    sendingScreenFor($this->admin, $this->business)
        ->set('releaseNote', 'Nothing to release.')
        ->call('releaseTenant')
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', sendingToast('error', 'nothing to release'));

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'sending.resumed')->count())->toBe(0);
    });
});

test('one business incidents and rates are never visible under another business', function (): void {
    // Cross-tenant isolation on the screen. The aggregate 2101 contains belongs
    // to the platform; an incident, its note and its rate belong to one tenant,
    // and this screen is the first thing in `app/` that reads them across the
    // boundary at all.
    //
    // ⚠️ MUTATION: replace `Tenancy::actingAs($id, $callback)` in
    // SendingControls::viewing() with a bare `$callback()`. Every read then
    // fails closed instead — which is the second half of the point: the screen
    // cannot silently render the wrong tenant, because RLS refuses it.
    $other = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Second Diner',
    ]);

    Tenancy::forgetAll();

    Tenancy::actingAs($this->business->id, fn () => app(SendingGuard::class)
        ->pause(SendingPauseReason::Compliance, 'user:3', null, 'A note about the first diner.'));

    sendingScreenFor($this->admin, $other)
        ->assertSee('Second Diner')
        ->assertSee('Sending running')
        ->assertSee('This business has never been stopped from sending.')
        ->assertDontSee('A note about the first diner.')
        ->assertDontSee('Ledger Diner');

    // And the first business is still stopped — reading the second did not move
    // anything.
    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingGuard::class)->isPaused())->toBeTrue();
    });
});

test('an operator acting on one business cannot stop another one', function (): void {
    // The write half of isolation. `businessId` is `#[Locked]`, so the tenant a
    // write lands on is the one `lookUp()` audited — never one the payload named.
    $other = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Second Diner',
    ]);

    Tenancy::forgetAll();

    $component = sendingScreenFor($this->admin, $this->business);

    expect(fn (): mixed => $component->set('businessId', $other->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    Tenancy::actingAs($other->id, function (): void {
        expect(SendingPause::query()->count())->toBe(0);
    });
});

test('a rate with nothing behind it renders as not measured, never as nought per cent', function (): void {
    // ⛔ THE COUNTERS HAVE NO WRITER (2496–2499) AND THIS TEST DELIBERATELY DOES
    // NOT SEED THEM. `SendingRates::complaintRateBp()` answers `0` for an empty
    // window — correctly, because a threshold comparison needs a number — and
    // rendering that as "0.0%" beside a green tick tells an operator this
    // business has been measured and is excellent.
    //
    // ⚠️ 2498 RECORDS THAT THIS EXACT GAP SURVIVED TWO SLICES, *"because every
    // test seeds the counters by hand"*. Seeding here would have made the screen
    // look right and left the honest path untested.
    //
    // ⚠️ MUTATION: return `$basisPoints` unconditionally from
    // `RateReading::ceiling()`/`floor()` instead of nulling it when the
    // denominator is zero. This reddens on both halves — the dash and the label.
    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('Not measured yet')
        ->and($html)->toContain('An empty counter is not a zero rate.')
        ->and($html)->not->toContain('0.0%');

    // ⚠️ **THE "NOTHING IS COUNTED YET" ASSERTION THAT SAT HERE WAS REMOVED AT
    // THE MERGE OF `l2-containment-writers`, AND THE PANEL IT ASSERTED WITH
    // IT.** Both lanes were in flight together: this one built a screen that
    // told operators nothing counted these figures, and that one wired the
    // writers. `Architecture/MessagingTest` fired on the first run afterwards,
    // named the blade and said what to delete — which is exactly the job it was
    // written for.
    //
    // ⛔ **WHAT THIS TEST ASSERTS IS UNCHANGED AND STILL LOAD-BEARING**, which
    // is why it is edited rather than deleted. A rate with an empty denominator
    // must still render as a dash and "Not measured yet", and must still never
    // render as "0.0%" — and that is now the *ordinary* case rather than the
    // universal one. A business nobody has texted this window is not a business
    // with a nought per cent complaint rate, and the counters having writers
    // makes that distinction more important rather than less: from today some
    // businesses genuinely have a measured zero, and the screen has to tell an
    // operator which kind they are looking at.
});

test('a measured rate with no threshold set says nothing is watching it', function (): void {
    // The second of `RateReading`'s four states. A figure with no alert
    // threshold is real and unwatched, and a green tick against a threshold of
    // nought — which every rate trivially satisfies — would be the worst
    // possible rendering of it. 2409's rule is why the two new keys seed to 0
    // rather than to a plausible figure somebody typed.
    //
    // ⚠️ MUTATION: make `RateReading::hasThreshold()` return true for a
    // non-positive threshold. This reddens.
    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 10; $i++) {
            $health->recordSent(OutreachChannel::Sms);
            $health->recordDelivered(OutreachChannel::Sms);
        }
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('No alert set')
        ->and($html)->toContain('No alert threshold is set for this rate, so nothing is watching it.')
        // The delivery figure itself is real and is shown.
        ->and($html)->toContain('100.0%');
});

test('a complaint rate under the significance floor is not shown as a passing grade', function (): void {
    // `SendingRates::hasEnoughVolume()`'s rule, surfaced. One STOP out of a new
    // tenant's first two deliveries is a 5,000bp rate; showing that as an alert
    // teaches an operator to raise the threshold until nothing ever fires, which
    // is 511's failure inside a kill switch. Showing it as "within the
    // threshold" would be the opposite lie.
    //
    // ⚠️ MUTATION: delete the `! $this->isSignificant()` arm from
    // `RateReading::state()`. This reddens — the reading becomes `Alert`.
    app(DefaultsRegistry::class)->set('messaging.complaint_trip_min_delivered', 50, 'test');
    app(DefaultsRegistry::class)->set('messaging.complaint_trip_bp', 300, 'test');

    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 4; $i++) {
            $health->recordDelivered(OutreachChannel::Sms);
        }

        $health->recordComplaint(OutreachChannel::Sms);
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('Not enough sending yet')
        ->and($html)->toContain('too little to judge')
        // The arithmetic is still shown — hiding it would be its own dishonesty.
        ->and($html)->toContain('25.0%');

    // ⚠️ AND THE PILL ITSELF, WHICH THE THREE ASSERTIONS ABOVE DO NOT REACH —
    // FOUND BY MUTATION, NOT BY REVIEW. `stateLabel()` and `sentence()` each
    // carry their own `isSignificant()` arm, so deleting the one in `state()`
    // left this test green while the pill turned red: 25% is over the 3%
    // threshold, so the reading rendered as an ALERT captioned "Not enough
    // sending yet". Colour is never the sole indicator (`22`) and it is not
    // meaningless either — a red pill on a rate the trip cannot fire on is the
    // alert that teaches an operator to stop believing the instrument.
    //
    // ⚠️ MUTATION: delete `|| ! $this->isSignificant()` from
    // `RateReading::state()`. This reddens here and nowhere else.
    $label = strpos($html, 'Not enough sending yet');
    expect($label)->toBeInt();

    // The pill's own opening tag, which is what carries the colour token.
    $pill = substr($html, max(0, $label - 400), min(400, $label));

    // ⚠️ NO SECOND ARGUMENT. `Expectation::toContain()` is variadic
    // (`mixed ...$needles`), so a failure message passed here becomes a second
    // NEEDLE and the assertion fails against its own explanatory text —
    // `Architecture/MessagingTest` records the same trap biting it, and it bit
    // this line before it was rewritten this way.
    expect(str_contains($pill, 'text-ink-3'))
        ->toBeTrue('the insignificant reading is not rendered as unknown')
        ->and(str_contains($pill, 'text-alert'))
        ->toBeFalse('a rate the trip cannot fire on is rendered as an alert');
});

test('a brand new tenant with the delivery alert set is not red on their first message', function (): void {
    // ⛔ THE DEFECT THIS SLICE CLOSES, DRIVEN THROUGH THE SCREEN RATHER THAN
    // THROUGH THE CLASS (7560). `messaging.delivery_rate_alert_bp` was set in
    // the production registry on 2026-08-22 as the detector for the receipt
    // blind spot (7543), and the card it lit divided `delivered` by `sent` with
    // **no significance floor available on that factory at all** — so between a
    // carrier accepting a tenant's first message and the receipt landing, the
    // pill went RED and the figure read 0.0%. Every new tenant, every time. An
    // instrument that is red on every healthy new tenant is one an operator
    // stops believing, which is 511's failure inside the one screen a
    // containment is read from.
    //
    // ⚠️ THE COUNTER IS DRIVEN THROUGH `SendingHealth`, NOT SEEDED AS A ROW.
    // The file header's rule is that these tests do not hand-seed, and what is
    // being proven here is a *rendering* of a real counter state.
    //
    // ⚠️ MUTATION: in `SendingControls::readings()`, pass
    // `reportedOn: $rates->sent` to `RateReading::settled()` — the pre-slice
    // behaviour exactly. This reddens on the pill and on the figure.
    app(DefaultsRegistry::class)->set('messaging.delivery_rate_alert_bp', 9_000, 'test');

    Tenancy::actingAs($this->business->id, function (): void {
        app(SendingHealth::class)->recordSent(OutreachChannel::Sms);
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('Not measured yet')
        // The one message is named rather than hidden — an operator can see
        // exactly what the figure is waiting on.
        ->and($html)->toContain('1 more handed to a carrier')
        ->and($html)->not->toContain('Past the alert threshold')
        ->and($html)->not->toContain('Nothing is being reported back');

    // ⚠️ AND THE PILL, WHICH THE THREE ASSERTIONS ABOVE DO NOT REACH — the same
    // trap 4488 found on the Complaints card, where `state()`, `stateLabel()`
    // and `sentence()` each carry their own arm and a mutation in one left the
    // other two green. `text-alert` is the colour token; its absence anywhere
    // on this page is what proves nothing went red.
    expect(str_contains($html, 'text-alert'))
        ->toBeFalse('a tenant whose first message is still in flight is rendered as an alert');
});

test('a blackout is red on the screen, with the threshold the owner set', function (): void {
    // ⛔ THE HALF THAT MUST SURVIVE THE FIX (7543, 7562). 4,000 messages handed
    // to a carrier and not one outcome reported back is the receipt blind spot:
    // `SendingGuard::shouldTrip()` and `PlatformRateSample::trips()` both fall
    // through, the per-tenant complaint trip and 2102's platform halt are
    // silently disarmed, and every other figure on this page is green. **The
    // threshold was bought to make this red and it is still red.**
    //
    // ⛔ IT IS RED FOR A DIFFERENT REASON THAN BEFORE AND THAT IS THE POINT.
    // Before, the pill said "Past the alert threshold" because 0.0% is below
    // 90% — the same comparison that reddened a healthy campaign. Now it names
    // the actual condition, which is the sentence somebody has to act on at
    // 3am: the carrier has stopped answering, not this tenant's list.
    //
    // ⚠️ MUTATION: return `false` from `RateReading::reportingHasGoneSilent()`.
    // This reddens — the card falls back to "Not measured yet" and grey, which
    // is the state 7483 warned an operator scans past.
    app(DefaultsRegistry::class)->set('messaging.delivery_rate_alert_bp', 9_000, 'test');

    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 4_000; $i++) {
            $health->recordSent(OutreachChannel::Sms);
        }
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('Nothing is being reported back')
        ->and($html)->toContain('4,000 handed to a carrier')
        ->and($html)->toContain('measurement that cannot happen')
        // ⛔ AND NOT AS A THRESHOLD BREACH, WHICH WOULD SEND SOMEBODY TO LOOK AT
        // THE WRONG THING ENTIRELY.
        ->and($html)->not->toContain('Past the alert threshold');

    $label = strpos($html, 'Nothing is being reported back');
    expect($label)->toBeInt();

    // The pill's own opening tag, which is what carries the colour token. No
    // second argument to `toContain()` — it is variadic, and a message passed
    // there becomes a second needle.
    $pill = substr($html, max(0, $label - 400), min(400, $label));

    expect(str_contains($pill, 'text-alert'))
        ->toBeTrue('a total reporting blackout is rendered as an alert');
});

test('a campaign in flight is not a disaster, which no volume floor could have fixed', function (): void {
    // ⛔ THE WORSE HALF OF 7560, AND THE REASON THE PROPOSED REMEDY WAS REFUSED
    // (7561). A floor on `sent` moves the false alert from message one to
    // campaign one, because `sent` is monotone with the thing that drags the
    // rate down: the faster a burst clears the floor, the more un-adjudicated
    // traffic sits in the denominator. 200 handed over, 120 landed, 2 failed —
    // over `sent` that is 60.0% and RED at a 90% floor, on a tenant whose
    // delivery rate is 98.4%.
    //
    // ⚠️ MUTATION: pass `reportedOn: $rates->sent`. This reddens with 60.0% and
    // an alert pill.
    app(DefaultsRegistry::class)->set('messaging.delivery_rate_alert_bp', 9_000, 'test');

    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 200; $i++) {
            $health->recordSent(OutreachChannel::Sms);
        }

        for ($i = 0; $i < 120; $i++) {
            $health->recordDelivered(OutreachChannel::Sms);
        }

        $health->recordFailed(OutreachChannel::Sms);
        $health->recordFailed(OutreachChannel::Sms);
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    expect($html)->toContain('98.4%')
        ->and($html)->toContain('Within the alert threshold')
        // ⚠️ WHAT THE RATE LEAVES OUT, PRINTED BESIDE IT (7565). An adjudicated
        // rate is an optimistic bound — an `EXPIRED` verdict can take days where
        // this window is 24 hours — so the outstanding count travels with the
        // figure instead of being left for an operator to infer.
        ->and($html)->toContain('78 more handed to a carrier')
        ->and($html)->not->toContain('60.0%')
        ->and($html)->not->toContain('Past the alert threshold');
});

test('the opt-out card carries the same floor the complaint card does', function (): void {
    // ⛔ A HAZARD NOBODY HAD RECORDED, FOUND WHILE FIXING THE DELIVERY CARD
    // (7564). `messaging.opt_out_rate_alert_bp` is a live registry key that
    // seeds `0`, and the "Asked to stop" reading is a `ceiling()` whose
    // `significanceFloor` argument was simply **omitted** at the call site. So
    // the delivery card's defect was sitting in a second card, unfired only
    // because nobody had set that key — one Ops edit from a red pill on every
    // new tenant's first STOP.
    //
    // ⚠️ THE FLOOR IS `messaging.complaint_trip_min_delivered` AND IS NOT A NEW
    // FIGURE (2409, 7569). The opt-out card and the complaint card divide by the
    // identical denominator and ask the identical question — *how big a sample
    // means something about this tenant* — and there is exactly one figure in
    // this application answering it. A second key would be a policy nobody set,
    // and the manifest is not this lane's file besides.
    //
    // ⚠️ MUTATION: drop `significanceFloor` from the "Asked to stop" reading in
    // `SendingControls::readings()`. This reddens — the pill turns red and the
    // sentence loses "too little to judge".
    app(DefaultsRegistry::class)->set('messaging.opt_out_rate_alert_bp', 200, 'test');

    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        $health->recordSent(OutreachChannel::Sms);
        $health->recordDelivered(OutreachChannel::Sms);
        $health->recordOptOut(OutreachChannel::Sms);
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    // The arithmetic is still shown — hiding it would be its own dishonesty.
    expect($html)->toContain('100.0%')
        ->and($html)->toContain('too little to judge')
        ->and($html)->not->toContain('Past the alert threshold');

    // ⚠️ AND THE PILL, WHICH THE ASSERTIONS ABOVE DO NOT REACH — 4488's trap,
    // where `state()`, `stateLabel()` and `sentence()` each carry their own arm.
    expect(str_contains($html, 'text-alert'))
        ->toBeFalse('a rate on a sample of one is rendered as an alert');
});

test('an operator stops and starts sending for everyone, with both recorded', function (): void {
    // The dedicated control 2118 said did not exist and 2400 corrected. What the
    // generic registry editor could not do is say what the switch means, whether
    // the automatic half is armed, or why the platform was stopped — which is
    // the whole of this panel.
    //
    // ⛔ **THIS COMMENT READ "THE GENERIC REGISTRY EDITOR COULD ALWAYS WRITE
    // THIS KEY" AND THAT IS NO LONGER TRUE — CORRECTED 2026-08-20 (5901).** It
    // was a fair description of a text box that took the exact lowercase string
    // `true`; 5880 turned it into a one-press switch reading On/Off, at which
    // point the second door stopped being awkward and became the easier of the
    // two. It now refuses, and names this screen.
    //
    // ⚠️ MUTATION: delete the `$registry->set(...)` call from
    // SendingControls::haltPlatform(). This reddens.
    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->call('haltPlatform')
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', sendingToast('success', 'Sending stopped for everyone'));

    expect(app(DefaultsRegistry::class)->value('messaging.global_halt'))->toBeTrue();

    // Every tenant refuses, naming the switch that was actually thrown.
    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingGuard::class)->refusalFor(OutreachChannel::Sms))
            ->toBe(SendRefusalReason::GlobalHalt);
    });

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->call('releasePlatform')
        ->assertHasNoErrors();

    expect(app(DefaultsRegistry::class)->value('messaging.global_halt'))->toBeFalse();

    // ⚠️ `registry_changes` IS THE APPEND-ONLY RECORD HERE, BECAUSE `audit_log`
    // CANNOT BE: `AuditService::record()` opens with `Tenancy::idOrFail()` (419)
    // and a platform halt belongs to no tenant. Both directions, both attributed.
    $changes = RegistryChange::query()
        ->where('setting_key', 'messaging.global_halt')
        ->orderBy('id')
        ->get();

    expect($changes)->toHaveCount(2)
        ->and($changes[0]->value_after)->toBeTrue()
        ->and($changes[0]->actor)->toBe('user:'.$this->admin->id)
        ->and($changes[1]->value_after)->toBeFalse()
        ->and($changes[1]->actor)->toBe('user:'.$this->admin->id);

    expect(PlatformSetting::query()->whereKey('messaging.global_halt')->sole()->updated_by)
        ->toBe('user:'.$this->admin->id);
});

test('an automatic halt reads as stopped on the screen and is released by the same button', function (): void {
    // ⛔ **THE HALF THAT MAKES 3980'S SPLIT SAFE RATHER THAN A NEW DEFECT.** The
    // complaint-rate sweep throws `messaging.automatic_halt` now, so a screen
    // that asked only about `messaging.global_halt` would show "running for
    // everyone" while every send refused — and the release button would clear a
    // switch that was not the one stopping anything, leaving an automatic halt
    // with **no door in the application at all** (2478's shape, from the other
    // end). Both are asserted: the state is one answer, and the release clears
    // the key a machine wrote.
    //
    // ⚠️ MUTATION: drop `AUTOMATIC_HALT_KEY` from `haltedPlatformWide()`. This
    // reddens on the status line; dropping it from the `releasePlatform()` loop
    // reddens on the value below.
    $registry = app(DefaultsRegistry::class);
    $registry->set('messaging.automatic_halt', true, WatchPlatformComplaintRate::ACTOR);

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->assertSee('Stopped for everyone')
        // The words that tell an operator which of the two it was — the pill
        // cannot, because it is deliberately one answer for both.
        ->assertSee('The platform stopped itself');

    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingGuard::class)->refusalFor(OutreachChannel::Sms))
            ->toBe(SendRefusalReason::GlobalHalt);
    });

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->call('releasePlatform')
        ->assertHasNoErrors()
        ->assertDispatched('toaster:received', sendingToast('success', 'Sending started again for everyone'));

    expect($registry->value('messaging.automatic_halt'))->toBeFalse();

    // The release is attributed to the person, not to the actor that threw it.
    $change = RegistryChange::query()
        ->where('setting_key', 'messaging.automatic_halt')
        ->orderByDesc('id')
        ->firstOrFail();

    expect($change->value_after)->toBeFalse()
        ->and($change->actor)->toBe('user:'.$this->admin->id);
});

test('a release touches only the switch that was actually set', function (): void {
    // ⚠️ **THE CHANGE LOG IS WHAT SOMEBODY READS DURING AN INCIDENT**, and a
    // release that wrote `false` over a key already `false` would file a record
    // of an operator starting sending that was never stopped — the same lie the
    // double-stop test below refuses at the other end.
    $registry = app(DefaultsRegistry::class);
    $registry->set('messaging.automatic_halt', true, WatchPlatformComplaintRate::ACTOR);

    Livewire::actingAs($this->admin)->test(SendingControls::class)->call('releasePlatform');

    expect(RegistryChange::query()->where('setting_key', 'messaging.global_halt')->count())->toBe(0);
});

test('stopping everyone twice is refused rather than writing a second change', function (): void {
    // The change log is what somebody reads to find out how many times the
    // platform was actually stopped. A no-op that still writes a row makes that
    // count a lie.
    Livewire::actingAs($this->admin)->test(SendingControls::class)->call('haltPlatform');

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->call('haltPlatform')
        ->assertDispatched('toaster:received', sendingToast('error', 'already stopped'));

    expect(RegistryChange::query()->where('setting_key', 'messaging.global_halt')->count())->toBe(1);
});

test('the screen says the automatic platform stop is switched off when it is', function (): void {
    // 2409 and 2410, amended by 2684. ⚠️ THIS TEST USED TO SET NOTHING AND LEAN
    // ON THE SEEDS BEING ZERO, and 2684 armed them — so it asserted a fact about
    // `DefaultsManifest` while reading as a fact about the component. Disarming
    // is now explicit, which is what it always meant: either key at zero
    // disables the trip, and an operator who edits one back to zero must be told
    // so on the page. 2113 makes the trip a precondition of sending at all, and
    // this page is exactly where somebody would otherwise conclude the platform
    // is protected.
    //
    // ⚠️ MUTATION: make `automaticHaltSetting()` return `armed: true`
    // unconditionally. This reddens.
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_trip_bp', 0, 'test');
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_min_delivered', 0, 'test');

    $html = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($html)->toContain('Will not stop itself')
        ->and($html)->toContain('will')
        ->and($html)->toContain('stop by itself, whatever the complaint rate reaches');
});

test('the screen says the automatic platform stop is armed once both figures are set', function (): void {
    // The other direction, which is what makes the test above a claim rather
    // than a constant. Without it, a screen hardcoding the warning would pass.
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_trip_bp', 300, 'test');
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_min_delivered', 500, 'test');

    $html = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($html)->toContain('Stops itself')
        ->and($html)->toContain('3.0%')
        ->and($html)->not->toContain('Will not stop itself');
});

test('one figure set and the other zeroed still reads as switched off', function (): void {
    // `PlatformRateSample::trips()` requires both, and a screen that read only
    // the threshold would tell an operator the platform is protected when the
    // floor is what disables it. ⚠️ The floor is zeroed EXPLICITLY: since 2684
    // it seeds to 50, so "the other not set" is no longer a state this test can
    // reach by staying silent — and silence would have made this assertion pass
    // against a fully armed platform.
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_trip_bp', 300, 'test');
    app(DefaultsRegistry::class)->set('messaging.platform_complaint_min_delivered', 0, 'test');

    $html = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($html)->toContain('Will not stop itself');
});

test('the screen reads as armed with nothing configured, because the seeds arm it', function (): void {
    // ⛔ THE ONE TEST ON THIS SCREEN THAT SETS NOTHING, AND THAT IS ITS WHOLE
    // SUBJECT (2684). Every other assertion here writes the registry first, so
    // all of them would go on passing if the seeds were reverted to zero and the
    // platform quietly stopped stopping itself. This one reads what a fresh
    // install actually renders.
    //
    // ⚠️ MUTATION: set `messaging.platform_complaint_trip_bp` back to `0` in
    // `DefaultsManifest`. This reddens and nothing else on this screen does.
    $html = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($html)->toContain('Stops itself')
        ->and($html)->toContain('1.0%')
        ->and($html)->not->toContain('Will not stop itself');
});

test('an account-wide pause is shown beside the sending pause, because they are different switches', function (): void {
    // ⚠️ THE MOST IMPORTANT PANEL ON THE PAGE FOR ANYBODY DEBUGGING "WHY IS
    // NOTHING SENDING", and the reason it exists is 2450: a docblock claimed the
    // campaign runner read *"the tenant pause — L7's automatic complaint-rate
    // trip writes this"*, and it does not. `TenantPause` is a different table
    // with a different reader and no automatic writer; the trip writes a
    // `SendingPause` row. The suite was green throughout.
    //
    // An operator who releases a sending pause while Pause Everything is on sees
    // nothing sent afterwards and has every reason to conclude the release
    // failed — and the next thing they do is release it again.
    //
    // ⚠️ MUTATION: hardcode `'accountPaused' => false` in
    // SendingControls::render(). This reddens.
    Tenancy::actingAs($this->business->id, function (): void {
        app(TenantPause::class)->pause($this->business, 'support:9', 'Owner asked us to stop.');
    });

    sendingScreenFor($this->admin, $this->business)
        ->assertSee('Whole account paused')
        ->assertSee('A different switch, and not ours')
        ->assertSee('support:9')
        ->assertSee('never from this page');
});

test('a business with no account-wide pause is not told it has one', function (): void {
    // The other direction. Without it, a panel rendered unconditionally would
    // pass the test above and tell every operator that every business is paused.
    sendingScreenFor($this->admin, $this->business)
        ->assertDontSee('Whole account paused');
});

test('a resolved lookup is recorded in the looked-up business own log', function (): void {
    // `PhiTenants`' rule, and this screen shows more than that one does: the
    // incident series, the free-text notes operators wrote on it, and the rates.
    // A read that leaves no trace lets staff walk the business id space with
    // nothing anywhere saying they did.
    //
    // ⚠️ MUTATION: delete the `$audit->record('business.viewed_by_staff', …)`
    // call from SendingControls::lookUp(). This reddens.
    sendingScreenFor($this->admin, $this->business)->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->actor)->toBe('user:'.$this->admin->id)
            ->and($entry->entity_type)->toBe(Business::class)
            ->and($entry->entity_id)->toBe($this->business->id);
    });
});

test('a business number that resolves to nothing clears the one in view', function (): void {
    // Resolving a real business first is what makes the clearing observable —
    // asserting `businessId` is null after a miss is asserting the property's
    // initial value, which 411's shape in `PhiTenantsAdminTest` records. The
    // case that matters is an operator looking at business 12 who mistypes and
    // must not then stop 12.
    //
    // ⚠️ MUTATION: delete the two `$this->businessId = null;` lines from
    // SendingControls::lookUp(). This reddens on both halves.
    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', '999999')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', 'not a number')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null);
});

test('a tenant may not clear their own containment, and that is the policy rather than the route', function (): void {
    // ⚠️ 2478'S POLICY QUESTION, ANSWERED WHERE IT SURVIVES A SECOND DOOR.
    // "The owner cannot reach /admin" and "the owner may not perform this act"
    // are different facts, and only the second holds if somebody adds another
    // route later. 2101 is why it matters: the party generating the complaints
    // is precisely the party with a reason to lift the containment.
    //
    // Driven against the Gate directly — no screen, no route, no middleware — so
    // nothing upstream can be what refuses (398).
    //
    // ⚠️ MUTATION: make `SendingPausePolicy::release()` return true. This
    // reddens on every role but the platform's.
    $pause = Tenancy::actingAs($this->business->id, fn (): SendingPause => app(SendingGuard::class)
        ->pause(SendingPauseReason::ComplaintRate, SendingGuard::SYSTEM_ACTOR, 900));

    foreach ([UserRole::Owner, UserRole::Manager, UserRole::Staff, UserRole::Agency] as $role) {
        $tenantUser = User::factory()->create(['role' => $role]);

        expect(Gate::forUser($tenantUser)->allows('release', $pause))
            ->toBeFalse("role {$role->value} must not release a containment")
            ->and(Gate::forUser($tenantUser)->allows('create', SendingPause::class))
            ->toBeFalse("role {$role->value} must not stop a business sending")
            ->and(Gate::forUser($tenantUser)->allows('viewAny', SendingPause::class))
            ->toBeFalse("role {$role->value} must not read the incident series");
    }

    expect(Gate::forUser($this->admin)->allows('release', $pause))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('create', SendingPause::class))->toBeTrue();
});

test('nobody edits or deletes an incident, which is what makes it a record', function (): void {
    // 2119(a) expressed as an authority rather than as an absent form. The row
    // carries `observed_rate_bp`, a snapshot that cannot be recomputed — and
    // `resume()` deleting it is the exact defect that slice existed to fix, so a
    // delete ability here would be that defect re-offered as a feature.
    $pause = Tenancy::actingAs($this->business->id, fn (): SendingPause => app(SendingGuard::class)
        ->pause(SendingPauseReason::Operator, 'user:3'));

    expect(Gate::forUser($this->admin)->allows('update', $pause))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('delete', $pause))->toBeFalse();
});

test('the admin gate is checked before the form is validated', function (): void {
    // ⚠️ CALLED ON THE INSTANCE, WHICH IS THE ONLY WAY THIS CLAIM IS
    // FALSIFIABLE. Through the front door both orderings answer 403: under the
    // wrong one `validate()` throws first, Livewire catches the
    // ValidationException, re-renders — and `render()` authorizes too, so the
    // response is forbidden either way. A test driven through `->call()` would
    // be named for a claim it does not make (352, 397).
    //
    // ⚠️ MUTATION: move `$this->authorize(AdminAccess::GATE);` below
    // `$this->validate([...])` in pauseTenant() or releaseTenant(). Each reddens
    // on its own leg with a ValidationException.
    $component = sendingScreenFor($this->admin, $this->business)->instance();

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    expect(fn (): mixed => $component->pauseTenant(app(SendingGuard::class)))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): mixed => $component->releaseTenant(app(SendingGuard::class)))
        ->toThrow(AuthorizationException::class)
        // ⚠️ Both platform actions take the guard as well as the registry since
        // 3980 — two halt keys, one predicate, and it lives on `SendingGuard`.
        ->and(fn (): mixed => $component->haltPlatform(app(DefaultsRegistry::class), app(SendingGuard::class)))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): mixed => $component->releasePlatform(app(DefaultsRegistry::class), app(SendingGuard::class)))
        ->toThrow(AuthorizationException::class);
});

test('the component is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->assertForbidden();
});

test('the route is gated too, because a component test runs no middleware', function (): void {
    // ⚠️ DECISION 809, STATED RATHER THAN ASSUMED: `Livewire::test()` runs NO
    // middleware, so the test above proves the component refuses and says
    // nothing whatever about the route. An owner is refused by the real
    // predicate here — `canAdministerPlatform()` — not by a stub.
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))
        ->get(route('admin.sending-controls'))
        ->assertForbidden();
});

test('an unauthenticated visitor cannot reach the kill switches', function (): void {
    // ⚠️ ITS OWN TEST, AND THAT IS NOT TIDINESS. `$this->actingAs()` persists on
    // the TestCase for the rest of the method, so a guest GET written after an
    // authenticated one is still authenticated — this assertion sat under the
    // one above and answered 403 rather than a redirect, which reads as the
    // guest being *forbidden* rather than as the test being wrong.
    $this->get(route('admin.sending-controls'))->assertRedirect();
});

test('the screen actually renders over a real request', function (): void {
    // ⚠️ 570'S DEFECT: every `/admin` screen 500'd on a missing layout, and it
    // was invisible because admin tests use `Livewire::test()` (which renders no
    // layout) and the only real GETs asserted `assertForbidden()` and
    // `assertRedirect()` — both of which short-circuit in middleware. A console
    // has to render, so one real 200 is not optional.
    //
    // ⚠️ AND IT NEEDS A SECOND FACTOR, WHICH IS THE OTHER THING NO COMPONENT
    // TEST CAN SEE. `RequiresTwoFactor` is on the whole internal group, so an
    // internal account without one is redirected to enrolment — this test found
    // that by answering 302, and every `Livewire::test()` in this file sails
    // past it. `28` §9.1: a second factor is required of internal roles, and
    // this screen throws the widest switch in the application.
    $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

    $this->actingAs($admin)
        ->get(route('admin.sending-controls'))
        ->assertOk()
        ->assertSee('Stop and start sending')
        ->assertSee('Stop sending for everyone');
});

test('an internal account without a second factor cannot reach the kill switches', function (): void {
    // The other half, and the reason the test above needed a factor at all.
    // `28` §9.1's rule applies to this screen like every other internal one —
    // stated here so that nobody "fixes" the 302 by exempting the route.
    $this->actingAs($this->admin)
        ->get(route('admin.sending-controls'))
        ->assertRedirect(route('two-factor.setup'));
});

test('the nav offers the screen to platform staff and to nobody else', function (): void {
    // Filtering the nav is not authorization — the route above is — but a kill
    // switch nobody can find is most of the way to one that does not exist.
    expect(AdminNav::for($this->admin)->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.sending-controls'))
        ->toBeTrue();

    expect(AdminNav::for(User::factory()->create(['role' => UserRole::Owner]))->flatten()
        ->contains(fn (NavItem $item): bool => $item->route === 'admin.sending-controls'))
        ->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The trip math — T176 P9 (4369–4378)
|--------------------------------------------------------------------------
|
| ⚠️ THE TWO CONFIGURED FIGURES WERE ALREADY ON THIS SCREEN AND WHAT THEY DO
| TOGETHER WAS NOT. An operator could read "1.0% over 50 delivered" and had no
| way to see that ONE complaint in those 50 is 2.0% and already halts the
| platform. These tests are about that step, and about the two places it must
| not lie: a platform standing this request cannot measure, and a per-tenant
| standing it can.
|
*/

test('the platform panel shows the arithmetic and warns that the seeded pair does not hold', function (): void {
    // ⛔ THE SEEDS, NOT A WRITTEN REGISTRY ROW. Every other platform assertion
    // in this file sets the figures first, so all of them would pass against a
    // pair that had been changed underneath them. This one reads what a fresh
    // install renders — and what it renders today is a warning, because 100bp
    // over a floor of 50 trips on one complaint.
    //
    // ⚠️ MUTATION: change `floorThatSurvivesNoise()` back to
    // `floor(10000·k / t) + 1`. At 100bp that answers 201, 50 is still under it
    // and this test stays green — which is why `TripMathTest` drives the
    // derivation through `SendingRates` and this one does not try to.
    //
    // ⚠️ THE WARNING IS THE ATTENTION CARD AND NOT THE PILL, SINCE 4488. The
    // pill said "Stops on too little to judge" here until 2026-08-16 — a label
    // about the *pair of settings*, which is the same for every subject, so
    // every tenant and this panel wore the identical amber badge and it
    // distinguished nothing. The configuration warning has its own surface with
    // the arithmetic in it, and that surface is what is asserted.
    $html = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($html)->toContain('<h3 class="font-display text-sm font-semibold text-ink">How a stop is worked out for everyone</h3>')
        ->and($html)->toContain('These two figures fight each other')
        // The cost, named as a count rather than as a percentage: a person
        // reading "1%" does not picture one message.
        ->and($html)->toContain('one complaint already stops everyone')
        ->and($html)->toContain('202')
        ->and($html)->not->toContain('Stops on too little to judge');
});

test('the platform standing is the sweep reading and is never measured on the request', function (): void {
    // ⛔ THE HONEST ABSENCE FIRST. `sending_health_windows` is FORCE ROW LEVEL
    // SECURITY, so a request path summing it across tenants reads ZERO rather
    // than failing — a healthy-looking platform that is simply unreadable. With
    // no sweep reading stored, the panel says so.
    Cache::forget(PlatformComplaintRate::LAST_SAMPLE_CACHE_KEY);

    $before = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    // ⚠️ THE PILL **AND** THE SENTENCE, WHICH IS NEW SINCE 4488. This assertion
    // read "the sentence and not the pill" until 2026-08-16, because
    // `stateLabel()` reported the unsafe *setting* ahead of the absent
    // *measurement* — and it reported it for every subject on the screen, which
    // is how a badge stops carrying information (511). The pill now answers about
    // the subject, so the absence is stated in all three places an operator
    // looks: the label, the sentence, and the dash where a percentage would sit.
    expect($before)->toContain('Not measured here')
        ->and($before)->toContain('Nothing has been measured across all businesses yet')
        ->and($before)->toContain('—');

    // And then the sweep reports. ⚠️ THROUGH THE SERVICE THE COMMAND CALLS, not
    // by writing the cache key by hand — a test that wrote the entry itself
    // would keep passing if the command stopped reporting, which is the
    // writerless-control shape this codebase keeps finding.
    app(PlatformComplaintRate::class)->report(
        new PlatformRateSample(delivered: 4_000, complaints: 33, tenantCount: 9, windowHours: 24, sent: 4_000, failed: 0),
    );

    $after = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    // 33 in 4,000 is 82.5bp, which rounds to 83 — under the seeded 100.
    expect($after)->toContain('0.83%')
        ->and($after)->toContain('4,000 delivered so far')
        // ⚠️ THE BREADTH OF THE READING, WHICH WAS WRITTEN, RECONSTRUCTED AND
        // NEVER RENDERED (4499). "0.83%" and "0.83% across 9 businesses" are
        // different claims, and without the second an operator cannot tell a
        // platform-wide reading from a sweep that found one tenant.
        ->and($after)->toContain('across 9 businesses')
        ->and($after)->not->toContain('Nothing has been measured across all businesses yet');

    // ⚠️ AND THE SINGULAR, ON 4393'S RULE — the verb and the count have to
    // agree. "across 1 businesses" is the sentence an operator reads on the day
    // the sweep finds one tenant, which on a soft-launch platform is the most
    // likely day of all, and a line that reads as a typo is a line somebody
    // stops believing on a screen whose whole job is to be believed. The plural
    // above and this are the two halves of one ternary; asserting only one of
    // them leaves the other free to be anything.
    app(PlatformComplaintRate::class)->report(
        new PlatformRateSample(delivered: 4_000, complaints: 33, tenantCount: 1, windowHours: 24, sent: 4_000, failed: 0),
    );

    $alone = Livewire::actingAs($this->admin)->test(SendingControls::class)->html();

    expect($alone)->toContain('across 1 business')
        ->and($alone)->not->toContain('across 1 businesses');
});

test('the sweep itself is what leaves the reading, not the screen', function (): void {
    // 272's shape, refused in advance: a display fed by a store nothing writes
    // is a panel that says "not measured" for ever while the containment runs
    // perfectly. The command is driven, and the reading has to appear.
    Cache::forget(PlatformComplaintRate::LAST_SAMPLE_CACHE_KEY);

    $this->artisan('messaging:watch-platform-complaint-rate')->assertSuccessful();

    $reported = app(PlatformComplaintRate::class)->lastReported();

    expect($reported)->not->toBeNull()
        ->and($reported['sample']->windowHours)->toBe(SendingHealth::WINDOW_HOURS);
});

test('the per-tenant panel counts complaints to a stop at the volume actually delivered', function (): void {
    // ⚠️ THIS TEST SEEDS `sending_health_windows` BY HAND AND SAYS SO, because
    // this file's own header refuses that everywhere else. The subject here is
    // the arithmetic over a denominator, and there is no send path in a screen
    // test that produces one — what the header protects against is a *rate*
    // assertion that passes on hand-fed counters while the counters have no
    // real writer, and those writers exist (2520–2539, 3030–3040).
    $business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Ledger Diner',
    ]);

    Tenancy::actingAs($business->id, function (): void {
        DB::table('sending_health_windows')->insert([
            'business_id' => Tenancy::idOrFail(),
            'window_start' => now()->utc()->startOfHour(),
            'channel' => OutreachChannel::Sms->value,
            'sent' => 1_000,
            'delivered' => 1_000,
            'complaints' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $html = Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->set('lookup', (string) $business->id)
        ->call('lookUp')
        ->html();

    // 1,000 delivered at the seeded 3% — 30 complaints is exactly 300bp, and 12
    // are counted, so eighteen more stop this business.
    expect($html)->toContain('1,000 delivered so far and 12 complaints counted')
        ->and($html)->toContain('18 more complaints stop it')
        // The standing, from `SendingRates` and not recomputed on the screen.
        ->and($html)->toContain('1.20%')
        // ⚠️ THE HEADING, AND THAT IT IS NOT THE PLATFORM PANEL'S (4499). Both
        // panels carried the identical string inside a `<span>`, so a screen
        // reader announced two unheaded regions with the same name on one page
        // and neither could be told from the other — WCAG 2.4.6.
        ->and($html)->toContain('<h3 class="font-display text-sm font-semibold text-ink">How a stop is worked out for this business</h3>')
        // And the subject-level pill, which under the seeded pair used to read
        // "Stops on too little to judge" for every business alive (4488–4491).
        ->and($html)->toContain('Under the stopping point');
});

test('a business with nothing delivered is told how much more it takes, not given a zero', function (): void {
    // The other branch, and the one every account is in on day one. A panel
    // that printed "0 more complaints stop it" over an empty counter would read
    // as a business one message away from being stopped.
    $business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Beta Dental',
    ]);

    $html = Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->set('lookup', (string) $business->id)
        ->call('lookUp')
        ->html();

    // ⛔ THE FIGURE, AND THIS TEST ASSERTED THE SENTENCE AND NEVER THE FIGURE
    // UNTIL 2026-08-16 (4484–4487). `TripMath::measured()` was handed
    // `observedBp: 0` — which is what `SendingRates` answers on an empty
    // denominator, by its own rule — and `standingFigure()` printed a confident
    // **0.00%** under "Right now", three lines below a comment in the template
    // reading "a dash, never 0.00%". ⚠️ AND THE "Complaints" CARD A FEW HUNDRED
    // PIXELS ABOVE IT SHOWED A DASH FOR THE SAME BUSINESS AT THE SAME MOMENT,
    // because `RateReading::ceiling()` nulls its rate on an empty denominator:
    // two answers to one question on one screen, 3418's shape. The `not` is the
    // load-bearing half of this test.
    //
    // ⚠️ THE PILL IS NOW ASSERTED TOO, SINCE 4488. It used to say "Stops on too
    // little to judge" — a fact about the seeded pair, identical for every
    // business — and it now says what is true of this one.
    expect($html)->toContain('0 delivered so far, so 50 more before anything can stop')
        ->and($html)->toContain('Not enough sending to stop anything')
        ->and($html)->not->toContain('0.00%')
        ->and($html)->not->toContain('complaints counted')
        ->and($html)->not->toContain('more complaints stop it');
});

test('stopping is one press and starting again states the consequence first', function (): void {
    // ⛔ **THE ASYMMETRY 826 AND 1228 ARGUED FOR, PINNED SO A LATER SLICE CANNOT
    // HARMONISE IT AWAY** (5905). *Confirm the direction that is hard to notice
    // you took* — stopping announces itself, so it is one press with nothing in
    // the way at the moment somebody needs it most; starting again is the
    // direction that has to be weighed, and this screen weighs it by putting the
    // consequence **above** the button rather than behind a dialog.
    //
    // ⚠️ **BOTH ARE MECHANICALLY ONE PRESS, AND SAYING SO IS THE HONEST FORM OF
    // THIS CLAIM** (352's rule). There is no second click on the release; what
    // it has instead is a sentence naming what happens to every business, that
    // the automatic stop will fire again if the cause was not dealt with, and
    // that the operator's name is recorded. A test asserting "the release asks"
    // would be naming a mechanism this screen does not have.
    $stopped = Livewire::actingAs($this->admin)->test(SendingControls::class);

    $stopped->assertSee('Stop sending for everyone')
        ->assertDontSee('Every business starts sending again immediately');

    app(DefaultsRegistry::class)->set('messaging.global_halt', true, 'test');

    Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->assertSee('Start sending for everyone')
        ->assertSee('Every business starts sending again immediately')
        ->assertSee('Your name is recorded against this.');
});

test('the halt this screen owns cannot be moved from the generic settings editor', function (): void {
    // ⛔ **A SECOND DOOR WITH NO CEREMONY MAKES THE CAREFUL DOOR POINTLESS**
    // (5900). Asserted from this side as well as from the editor's, because this
    // is the screen whose reasoning is being protected and this is the test
    // somebody reads when they wonder why the switch is missing over there.
    //
    // ⚠️ MUTATION: delete the `OperatedElsewhere::has()` arm from
    // `PlatformSettings::toggle()` — this reddens on the halt.
    Livewire::actingAs($this->admin)
        ->test(PlatformSettings::class)
        ->call('toggle', SendingGuard::OPERATOR_HALT_KEY)
        ->call('toggle', SendingGuard::AUTOMATIC_HALT_KEY);

    expect(app(SendingGuard::class)->haltedPlatformWide())->toBeFalse();

    Livewire::actingAs($this->admin)->test(SendingControls::class)->call('haltPlatform');

    Livewire::actingAs($this->admin)
        ->test(PlatformSettings::class)
        ->call('toggle', SendingGuard::OPERATOR_HALT_KEY)
        ->call('resetToSeed', SendingGuard::OPERATOR_HALT_KEY);

    expect(app(SendingGuard::class)->haltedPlatformWide())->toBeTrue();

    // And the screen that owns it still can, in the direction that asks.
    Livewire::actingAs($this->admin)->test(SendingControls::class)->call('releasePlatform');

    expect(app(SendingGuard::class)->haltedPlatformWide())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The email arm — decisions 6660–6679, closing 6378(a)
|--------------------------------------------------------------------------
|
| 6360 gave all four email counters writers, so `SendingGuard::refusalFor()`
| called with `OutreachChannel::Email` reads a real rate and can open a real
| pause. 6375 recorded the consequence in terms: an operator has a per-tenant
| email complaint rate that can pause a business and NO SCREEN SHOWS IT.
|
| ⛔ A RATE WITH NO READER IS HOW 2496 SURVIVED TWO SLICES. These tests drive
| the real guard rather than writing a pause row by hand, because a screen that
| renders a hand-written row proves nothing about the containment that produced
| the real one.
|
*/

/**
 * Just the email panel's markup, cut out of the whole page.
 *
 * ⛔ **A PAGE-WIDE `not->toContain()` WOULD BE ANSWERED BY THE TEXT PANEL AND
 * WOULD PROVE THE OPPOSITE OF WHAT IT LOOKS LIKE.** Both panels render the same
 * card names, the same threshold and the same `TripMath` sentences, so
 * *"the email panel does not show an unsubscribe rate"* and *"the email panel
 * shows no figure"* are unanswerable against the full HTML — the SMS panel
 * supplies every string. Slicing between the two headings is what makes the
 * assertions about the email arm rather than about the page.
 *
 * ⚠️ **BOTH BOUNDARIES ARE ASSERTED RATHER THAN ASSUMED.** A heading reworded
 * on the screen would otherwise silently return the whole page or an empty
 * string, and an empty string satisfies every `not->toContain()` in this file —
 * 256's vacuous pass, arriving through a helper.
 */
function emailPanelOf(string $html): string
{
    $start = strpos($html, 'How their email is doing');
    $end = strpos($html, 'Every time they were stopped');

    expect($start)->toBeInt('the email panel heading is gone, so this helper slices nothing');
    expect($end)->toBeInt('the incident-history heading is gone, so the slice has no end');
    expect($end)->toBeGreaterThan($start);

    return substr($html, (int) $start, (int) $end - (int) $start);
}

/**
 * The email counters, moved the way the SES feedback path moves them.
 *
 * ⚠️ THE REAL SERVICE AND NOT A SEEDED ROW. `SendingHealth::increment()` is an
 * upsert on `(business_id, window_start, channel)`, and a hand-written insert
 * that got the channel or the bucket wrong would seed a window the rate never
 * reads — which is a green test over a screen showing a dash.
 */
function seedEmailHealth(int $businessId, int $delivered, int $complaints, int $sent = 0): void
{
    Tenancy::actingAs($businessId, function () use ($delivered, $complaints, $sent): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < $sent; $i++) {
            $health->recordSent(OutreachChannel::Email);
        }

        for ($i = 0; $i < $delivered; $i++) {
            $health->recordDelivered(OutreachChannel::Email);
        }

        for ($i = 0; $i < $complaints; $i++) {
            $health->recordComplaint(OutreachChannel::Email);
        }
    });
}

test('a business stopped by its email complaint rate is on the screen, with both numbers', function (): void {
    // ⛔ THE LOAD-BEARING ONE. Before this slice the trip could fire on email
    // and the screen said "Text messages only … Email is counted separately and
    // is not shown here" — so an operator arriving at a stopped business saw a
    // 5.0% snapshot, a dash under Complaints, and nothing that accounted for
    // either.
    //
    // ⚠️ THE PAUSE IS OPENED BY THE REAL GUARD, ON THE REAL CHANNEL. Nothing
    // here writes a `sending_pauses` row: `refusalFor(Email)` reads the same
    // counters the screen reads and opens the incident itself, which is the
    // only version of this test that says the screen and the containment agree.
    //
    // ⚠️ MUTATION: change `emailReadings()`/`emailTripMath()` in
    // `SendingControls::render()` to read `$rates['sms']`. Both the rate and the
    // two counts vanish and this reddens on all four assertions — verified
    // green first, then red, then restored.
    app(DefaultsRegistry::class)->set('messaging.complaint_trip_bp', 300, 'test');
    app(DefaultsRegistry::class)->set('messaging.complaint_trip_min_delivered', 50, 'test');

    seedEmailHealth($this->business->id, delivered: 60, complaints: 3);

    $tripped = Tenancy::actingAs(
        $this->business->id,
        fn (): ?SendRefusalReason => app(SendingGuard::class)->refusalFor(OutreachChannel::Email),
    );

    // The trip really fired, on email, before anything was rendered.
    expect($tripped)->toBe(SendRefusalReason::TenantPaused);

    $html = sendingScreenFor($this->admin, $this->business)->html();

    // The stop itself, and the snapshot the row carries.
    expect($html)->toContain('Sending stopped')
        ->and($html)->toContain('Complaint rate when it stopped:')
        // ⛔ THE NUMERATOR AND THE DENOMINATOR, NOT ONLY THE RATE. Three
        // complaints in sixty is 5%; three in three is also a percentage and is
        // noise. An operator shown "5.0%" alone makes the wrong call on one of
        // the two, which is why the counts are asserted separately from it.
        ->and($html)->toContain('60 delivered so far and 3 complaints counted')
        ->and($html)->toContain('5.0% of 60 delivered in the last 24 hours')
        ->and($html)->toContain('the stopping point is already reached')
        // And it is legible as an email figure rather than as a second text one.
        ->and($html)->toContain('How their email is doing');

    // ⛔ THE QUESTION AN OPERATOR ASKS SECOND. `sending_pauses` has no channel
    // column, so the row cannot say which channel earned the stop — and the
    // screen says so rather than letting the panel above it be assumed.
    //
    // ⚠️ MUTATION: delete the `@if ($livePause->reason === …ComplaintRate)`
    // block from the blade. This reddens.
    expect($html)->toContain('Which channel crossed the line:')
        ->and($html)->toContain('not recorded');

    // ⛔ AND THE STOP IS NOT EMAIL'S ALONE, WHICH IS THE THING A PER-CHANNEL
    // PANEL INVITES A READER TO GET WRONG. `refusalFor()` asks `isPaused()`
    // before it asks the channel, so the next TEXT MESSAGE is refused too.
    $sms = Tenancy::actingAs(
        $this->business->id,
        fn (): ?SendRefusalReason => app(SendingGuard::class)->refusalFor(OutreachChannel::Sms),
    );

    expect($sms)->toBe(SendRefusalReason::TenantPaused)
        ->and($html)->toContain('Either channel stops both');
});

test('a business with no email volume reads as no volume, never as a healthy zero', function (): void {
    // ⛔ THE DISTINCTION 2496 TURNED ON, ON THE NEW CHANNEL. `SendingRates`
    // answers `0` for a rate with no denominator — correctly, because a
    // threshold comparison needs a number — and rendering that as "0.0%" beside
    // a green tick tells an operator this business's email has been measured and
    // is spotless. Nothing has been measured at all.
    //
    // ⚠️ THE TEXT PANEL IS GIVEN REAL TRAFFIC AND THE EMAIL PANEL IS NOT, WHICH
    // IS WHAT MAKES THIS A TEST OF THE EMAIL ARM RATHER THAN OF AN EMPTY PAGE.
    // A component reading the SMS rates into both panels would show 100.0% here
    // and pass every assertion a page-wide "not measured" check could make.
    //
    // ⚠️ MUTATION: return `$basisPoints` unconditionally from
    // `RateReading::ceiling()`, or drop the `$reportedOn > 0` guard from
    // `RateReading::settled()`. This reddens. (`floor()` was this factory's name
    // until 2026-08-22 — 7563.)
    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 10; $i++) {
            $health->recordSent(OutreachChannel::Sms);
            $health->recordDelivered(OutreachChannel::Sms);
        }
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();

    $email = emailPanelOf($html);

    expect($email)->toContain('Not measured yet')
        // ⚠️ THE NOUN MOVED WITH THE DENOMINATOR ON 2026-08-22 (7560, 7563).
        // The email delivery card divides by what the transport has **reported
        // on** rather than by everything it accepted, because a message with no
        // verdict has not failed to arrive — it has not been judged. "Accepted
        // by the mail transport" is still this card's other noun and is what the
        // outstanding clause names; there is nothing outstanding here, because
        // nothing was sent.
        ->and($email)->toContain('Nothing has been reported on by the mail transport for this business')
        ->and($email)->toContain('Nothing has been delivered for this business')
        ->and($email)->not->toContain('0.0%')
        // ⛔ AND NOT THE TEXT PANEL'S FIGURE EITHER. 100.0% is what the SMS
        // delivery card reads with the traffic seeded above, so its absence
        // here is what proves the two panels are fed by two channels.
        ->and($email)->not->toContain('100.0%');

    // The positive control: the same page, the same moment, a channel that DOES
    // have volume. Without this the assertions above pass against a screen that
    // renders nothing at all.
    expect($html)->toContain('100.0%');
});

test('there is no email unsubscribe rate, because nothing counts one', function (): void {
    // ⛔ `SendingHealth::recordOptOut()` HAS ONE CALLER IN `app/` AND IT NAMES
    // `OutreachChannel::Sms` AS A LITERAL (6662). With `delivered` growing for
    // email and `opted_out` permanently zero, an "Asked to stop" card would
    // render a confident 0.0% against real volume — `RateReading` would rightly
    // call it MEASURED — which is 2496's defect rebuilt as a rendering, in the
    // best-looking tile on the screen written to contain it.
    //
    // ⚠️ `assertDontSee` PINS NOTHING ON ITS OWN (6340–6352), SO THE STRING IS
    // PINNED POSITIVELY IN THE SAME TEST: "Asked to stop" must still be on the
    // page, in the TEXT panel, where it has a writer. Renaming that card
    // reddens this test rather than quietly unhooking it.
    //
    // ⚠️ MUTATION: add a third `RateReading::ceiling(name: 'Asked to stop', …)`
    // to `SendingControls::emailReadings()`. This reddens on the slice.
    seedEmailHealth($this->business->id, delivered: 40, complaints: 0, sent: 40);

    Tenancy::actingAs($this->business->id, function (): void {
        $health = app(SendingHealth::class);

        for ($i = 0; $i < 5; $i++) {
            $health->recordSent(OutreachChannel::Sms);
            $health->recordDelivered(OutreachChannel::Sms);
            $health->recordOptOut(OutreachChannel::Sms);
        }
    });

    $html = sendingScreenFor($this->admin, $this->business)->html();
    $email = emailPanelOf($html);

    // The card exists where it has a writer …
    expect($html)->toContain('Asked to stop')
        // … and does not exist where it has none.
        ->and($email)->not->toContain('Asked to stop')
        // The absence is stated rather than left as a hole an operator has to
        // notice — outcome language, and it says what is still true of the
        // unsubscribes themselves.
        ->and($email)->toContain('There is no unsubscribe rate for email.')
        // And the email volume really is measured, so this is an absent card
        // beside real figures rather than an empty panel.
        ->and($email)->toContain('40 delivered');
});

test('one business email standing is never visible under another business', function (): void {
    // Cross-tenant isolation on the figures this slice adds. The stop and the
    // rate belong to one tenant; this screen is what reads them across the
    // boundary.
    //
    // ⚠️ WHICH LAYER THIS PROVES: the APPLICATION layer, and only because the
    // fixture re-establishes the first tenant by hand. `Business::provision()`
    // leaves the tenant it created in context, so the seeding below is wrapped
    // in its own `Tenancy::actingAs()` rather than trusting whatever `$other`
    // left behind.
    //
    // ⛔ AND THE MUTATION DOES NOT PROVE WHAT IT LOOKS LIKE IT PROVES — I RAN IT
    // AND THE ANSWER WAS NOT THE ONE I HAD WRITTEN DOWN (398, 6291, 6667).
    // Replacing `Tenancy::actingAs($id, $callback)` in
    // `SendingControls::viewing()` with a bare `$callback()` does redden this
    // test — with `TenantNotResolved` out of `Tenancy::idOrFail()`, raised by
    // `BelongsToTenant`'s GLOBAL SCOPE on `SendingHealthWindow::query()`, before
    // a single row is read. **The application layer refuses first and row-level
    // security is never reached**, which is the opposite of 6291's usual
    // ordering and is worth knowing here: `SendingHealth::rates()` goes through
    // Eloquent, where `SendingHealth::increment()` goes through `DB::table()`
    // and is the call RLS genuinely backstops.
    //
    // ⚠️ SO WHAT THIS TEST ESTABLISHES IS NARROWER THAN "ISOLATION": the screen
    // cannot render another tenant's email standing, and the layer that stops it
    // on this mutation is the global scope. The RLS half of the pair is proven
    // by `Architecture/TenancyTest`'s policy lints, not here — a claim of two
    // layers made from one red test is 314–316's shape.
    $other = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Second Diner',
    ]);

    Tenancy::forgetAll();

    seedEmailHealth($this->business->id, delivered: 200, complaints: 7, sent: 210);

    $html = sendingScreenFor($this->admin, $other)->html();
    $email = emailPanelOf($html);

    expect($html)->toContain('Second Diner')
        // The first diner's figures, none of which may appear here.
        ->and($email)->not->toContain('200 delivered')
        ->and($email)->not->toContain('7 complaints counted')
        ->and($email)->not->toContain('0.4%')
        // The second diner has no email traffic, so the honest answer is an
        // absence — which is also what a leak would have overwritten.
        ->and($email)->toContain('Not measured yet');

    // And the first business's own standing is untouched by having read the
    // second — the aggregate is a read, but a wrong tenant context on the write
    // side would be visible here.
    Tenancy::actingAs($this->business->id, function (): void {
        expect(app(SendingHealth::class)->rates(OutreachChannel::Email)->delivered)->toBe(200);
    });
});

test('an internal role that is not a platform administrator cannot read the email standing', function (): void {
    // ⚠️ BOTH LEVELS, BECAUSE `Livewire::test()` RUNS NO MIDDLEWARE (809). The
    // component half below says the component refuses; it says nothing whatever
    // about the route, and the route half says nothing about a second door onto
    // the component. Both are asserted against the REAL predicate —
    // `canAdministerPlatform()` is `super_admin` alone — rather than a stubbed
    // gate, so a support role that can legitimately reach other admin screens is
    // the one being refused here.
    //
    // ⚠️ THE FIGURES ARE SEEDED FIRST, so this is a refusal with something real
    // behind it rather than a refusal to render an empty page.
    seedEmailHealth($this->business->id, delivered: 90, complaints: 5, sent: 95);

    $support = User::factory()->withSecondFactor()->create(['role' => UserRole::SupportLead]);

    // The component.
    Livewire::actingAs($support)
        ->test(SendingControls::class)
        ->assertForbidden();

    // The route, over a real request, with a second factor already enrolled so
    // that a 403 is the gate's answer and not the two-factor redirect's.
    $this->actingAs($support)
        ->get(route('admin.sending-controls'))
        ->assertForbidden()
        ->assertDontSee('How their email is doing');
});

test('the email panel is not on the page until a business is in view', function (): void {
    // ⚠️ THE SECTION SITS INSIDE `@if ($business)` AND NOTHING ELSE ASSERTED
    // THAT. `$emailReadings` is `[]` and `$emailTripMath` is null with no
    // business looked up, so the grid and the trip-math block would both hide
    // themselves — but the heading and the "Either channel stops both" card
    // would not, and an attention card about a business nobody has named is a
    // warning with no subject. Found by reading the boundary rather than by a
    // failure, and pinned so that moving the section out of the conditional is
    // a red build rather than a screen an operator has to interpret.
    $html = Livewire::actingAs($this->admin)
        ->test(SendingControls::class)
        ->html();

    expect($html)->toContain('Stop sending for everyone')
        ->and($html)->not->toContain('How their email is doing')
        ->and($html)->not->toContain('Either channel stops both')
        // The text panel is behind the same conditional, so its absence is the
        // positive control: this is a screen with no business, not a screen
        // where the email section alone went missing.
        ->and($html)->not->toContain('How their text messages are doing');
});

test('the stop names its channel as unrecorded only where a rate caused it', function (): void {
    // ⛔ BOTH ARMS OF THE `@if`, WHICH PRESENCE ALONE DOES NOT DRIVE. An
    // operator pause carries no rate and therefore no channel to have failed to
    // record — `observed_rate_bp` is null and nothing measured anything — so
    // "which channel crossed the line" is a question about that incident that
    // does not arise. Rendering it anyway would invite an operator to go hunting
    // through two panels for an arithmetic cause that never existed.
    //
    // ⚠️ MUTATION: change the guard to `@if (true)`. This reddens on the
    // operator arm. Deleting the block reddens on the automatic arm, in the
    // load-bearing test above.
    sendingScreenFor($this->admin, $this->business)
        ->set('pauseReason', SendingPauseReason::Compliance->value)
        ->set('pauseNote', 'Carrier escalation naming this list.')
        ->call('pauseTenant')
        ->assertHasNoErrors();

    $operatorPause = sendingScreenFor($this->admin, $this->business)->html();

    expect($operatorPause)->toContain('Why sending is stopped')
        ->and($operatorPause)->not->toContain('Which channel crossed the line:');

    // The other arm, on the same business, with the operator's incident
    // released first so the automatic trip opens a second one (2472).
    Tenancy::actingAs($this->business->id, function (): void {
        app(SendingGuard::class)->resume('user:1', 'Released to re-test.');
    });

    app(DefaultsRegistry::class)->set('messaging.complaint_trip_bp', 300, 'test');
    app(DefaultsRegistry::class)->set('messaging.complaint_trip_min_delivered', 50, 'test');

    seedEmailHealth($this->business->id, delivered: 60, complaints: 3);

    Tenancy::actingAs(
        $this->business->id,
        fn (): ?SendRefusalReason => app(SendingGuard::class)->refusalFor(OutreachChannel::Email),
    );

    expect(sendingScreenFor($this->admin, $this->business)->html())
        ->toContain('Which channel crossed the line:');
});
