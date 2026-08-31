<?php

declare(strict_types=1);

use App\Enums\ComplianceList;
use App\Enums\LiftSource;
use App\Enums\MessagingLane;
use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use App\Livewire\Admin\NumberLookup;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Consent\ConsentService;
use App\Services\Consent\NumberDossier;
use App\Services\Consent\OwnerConsentService;
use App\Services\Consent\SuppressionRegistry;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The staff number lookup — wave 40 lane C, decision 10880
|--------------------------------------------------------------------------
|
| A carrier forwards "+1555… says they never agreed to be texted by you." Every
| screen in this console was keyed on a BUSINESS number, so the one question a
| carrier actually asks could only be answered in `tinker` —
| `OwnerNotifyConsents` calls itself "the answer to a carrier's 'this number
| never agreed to be texted'" and resolves its input with `(int) trim(...)`.
|
| ⛔ IT SHOWS AND IT DOES NOTHING ELSE. There is no stop, start, lift or
| correction anywhere on it: releasing somebody from a suppression is a named
| authority with an audit row, and it does not belong one click from a text box
| whose subject is a stranger's phone number.
|
| ⚠️ THE TYPED NUMBER IS NEVER STORED. What IS recorded is the ordinary
| `business.viewed_by_staff` entry in each resolved tenant's own audit log.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);

    $this->admin = User::factory()->create(['name' => 'Platform Staff']);

    Tenancy::forgetAll();
});

/**
 * A provisioned tenant whose account holder has consented at a number.
 *
 * ⚠️ Deliberately NOT a bare `tenantWith…` name — a duplicate global function
 * name kills the whole run with zero bytes of output (decision 808).
 */
function tenantWhoseOwnerConsentedForNumberLookup(string $name, string $mobile): Business
{
    $business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => $name,
    ]);

    Tenancy::actingAs((int) $business->id, fn (): mixed => app(OwnerConsentService::class)->capture(
        $mobile,
        [
            'url' => 'https://example.test/account/settings',
            'ip_hash' => str_repeat('b', 64),
            'user_agent' => 'Mozilla/5.0 (Macintosh)',
        ],
        'user:1',
        consentChecked: true,
    ));

    // ⚠️ `Business::provision()` LEAVES THE TENANT IT CREATED IN CONTEXT, and
    // every read on this screen is tenant-less by design.
    Tenancy::forgetAll();

    return $business;
}

/*
|--------------------------------------------------------------------------
| The question a carrier actually asks
|--------------------------------------------------------------------------
*/

test('a phone number finds the business that registered it — the lookup that did not exist', function (): void {
    $business = tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSet('e164', '+15125559999')
        ->assertSee('Bright Smile Dental')
        ->assertSee('business '.$business->id);
});

test('the number is found however the carrier wrote it', function (string $typed): void {
    tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', $typed)
        ->call('lookUp')
        ->assertSet('e164', '+15125559999')
        ->assertSee('Bright Smile Dental');
})->with(['512-555-9999', '(512) 555 9999', '15125559999', '  +1 512 555 9999  ']);

test('one mobile registering two businesses renders both, because the answer is a list', function (): void {
    // ⚠️ THE CASE `businessesFor()` EXISTS FOR: `businesses.owner_user_id`
    // carries no uniqueness constraint, so one person owning two businesses and
    // reusing one mobile is a real shape. A screen rendering "the" business
    // would be wrong on exactly this case.
    tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');
    tenantWhoseOwnerConsentedForNumberLookup('Sunrise Cafe', '+15125559999');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSee('Bright Smile Dental')
        ->assertSee('Sunrise Cafe');
});

test('a stopped owner number says so rather than reading as sendable', function (): void {
    $business = tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Tenancy::actingAs((int) $business->id, function () use ($business): void {
        app(OwnerConsentService::class)->stop((int) $business->id, 'user:1');
    });

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSee('Stopped')
        ->assertSee('We do not text this number about this account.');
});

test('a number no business registered says so instead of leaving a blank', function (): void {
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertSee('Not an account holder', escape: false)
        ->assertSee('If a carrier is asking about an owner-channel', escape: false);
});

test('text that is not a phone number is answerable rather than fatal, and is not echoed back', function (): void {
    // ⛔ NO PERSONAL DATA IN A TOAST (104), and the typed text is whatever
    // somebody pasted out of a carrier email.
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', 'jane.doe@example.test')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('e164', null)
        ->assertDontSee('jane.doe@example.test');
});

/*
|--------------------------------------------------------------------------
| What is on record against the number
|--------------------------------------------------------------------------
*/

test('a carrier STOP is shown in words, with when it was recorded', function (): void {
    app(ConsentService::class)->suppressFromCarrier(
        '+15125559999',
        OutreachChannel::Sms,
        SuppressionReason::Stop,
        'carrier:test',
    );

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSee('Refused')
        ->assertSee('They asked us to stop.');
});

test('a release is shown beside the refusal it cleared, so the refusal cannot be read alone', function (): void {
    app(ConsentService::class)->suppressFromCarrier('+15125559999', OutreachChannel::Sms, SuppressionReason::Stop, 'carrier:test');
    app(ConsentService::class)->liftFromCarrier('+15125559999', OutreachChannel::Sms, LiftSource::CarrierStart, 'carrier:test');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSee('They asked us to stop.')
        ->assertSee('They asked us to start again.');
});

test('a number with nothing against it says nothing is against it, in words', function (): void {
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertSee('No platform-wide refusal has ever been recorded', escape: false);
});

test('an empty register is named, so "not listed" cannot be read as "we checked"', function (): void {
    // ⛔ 1600's DISTINCTION, ON THE PAGE. The shipped state of this platform is
    // "no register loaded"; without this the operator sends a carrier the
    // sentence "we checked and they are not listed" about a check nobody ran.
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertSee('Some registers have never been imported')
        ->assertSee('the federal Do Not Call registry');
});

test('a register listing is described by what it blocks, not by its column value', function (): void {
    app(SuppressionRegistry::class)->load(
        ComplianceList::Litigator,
        OutreachChannel::Sms,
        ['+15125559999'],
        'test-extract',
    );

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertSee('the list of people who sue over messages')
        ->assertSee('nothing may be sent to it', escape: false)
        ->assertDontSee('litigator');
});

/*
|--------------------------------------------------------------------------
| The other number in a carrier complaint — ours
|--------------------------------------------------------------------------
*/

test('one of our own sending numbers is named as ours rather than as nothing on record', function (): void {
    $business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Bright Smile Dental',
    ]);

    Tenancy::actingAs((int) $business->id, function () use ($business): void {
        PhoneNumber::query()->create([
            'business_id' => $business->id,
            'e164' => '+15125551111',
            'role' => NumberRole::Primary,
            'state' => NumberState::Active,
            'lane' => MessagingLane::Platform,
        ]);
    });

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125551111')
        ->call('lookUp')
        ->assertSee('In service. Messages are sent from it.')
        ->assertSee('Registered to Bright Smile Dental')
        ->assertDontSee('This is not one of our numbers.');
});

test('a number that is not ours says so plainly', function (): void {
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertSee('This is not one of our numbers.');
});

/*
|--------------------------------------------------------------------------
| The boundary, stated rather than discovered
|--------------------------------------------------------------------------
*/

test('the page says what it cannot answer, whether or not a number is in view', function (): void {
    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->assertSee('What this page cannot tell you')
        ->assertSee("Whether this number belongs to any\n                business's customer.", escape: false)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertSee('What this page cannot tell you');
});

/*
|--------------------------------------------------------------------------
| The trace it leaves, the lock, and the gate
|--------------------------------------------------------------------------
*/

test('a lookup that names a tenant is recorded in that tenant\'s own log', function (): void {
    $business = tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertHasNoErrors();

    Tenancy::actingAs((int) $business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->actor)->toBe('user:'.$this->admin->id)
            ->and($entry->entity_type)->toBe(Business::class);
    });
});

test('a lookup that names no tenant is recorded nowhere, and the number is not stored', function (): void {
    // ⚠️ ARGUED RATHER THAN OVERLOOKED. `AuditService::record()` needs a tenant
    // because `audit_log` is tenant-owned with RLS on `business_id`; forcing
    // one would file a stranger's phone number under an arbitrary business.
    // `SuppressionRegistry`'s docblock reaches the same conclusion for a
    // federal register import.
    $business = tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125550000')
        ->call('lookUp')
        ->assertOk();

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(0);
    });
});

test('one lookup naming a business twice audits it once', function (): void {
    // A number can be an account holder's mobile AND a row in the sending
    // inventory for the same business; the audit is the union, deduplicated.
    $business = tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    Tenancy::actingAs((int) $business->id, function () use ($business): void {
        PhoneNumber::query()->create([
            'business_id' => $business->id,
            'e164' => '+15125559999',
            'role' => NumberRole::Primary,
            'state' => NumberState::Active,
            'lane' => MessagingLane::Platform,
        ]);
    });

    Tenancy::forgetAll();

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999')
        ->call('lookUp')
        ->assertHasNoErrors();

    Tenancy::actingAs((int) $business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(1);
    });
});

test('the number in view cannot be set from the client, which is what makes the audit unskippable', function (): void {
    tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    expect(fn (): mixed => Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('e164', '+15125559999'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->assertForbidden();
});

test('the route is behind the admin gate too, because hiding a nav item is not authorization', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin)
        ->get(route('admin.number-lookup'))
        ->assertForbidden();
});

test('a reader who is refused mid-session cannot look a number up by calling the action', function (): void {
    // ⚠️ THE OUTER-GUARD SHAPE (398): the route gate and `mount()` both passed
    // when this component was mounted, so the only thing standing between a
    // revoked reader and a tenant's name is `lookUp()`'s own re-authorization.
    tenantWhoseOwnerConsentedForNumberLookup('Bright Smile Dental', '+15125559999');

    // ⚠️ THE NUMBER IS TYPED BEFORE THE GATE CLOSES, DELIBERATELY. `set()` is
    // itself an update request, so flipping the gate first makes `set()` the
    // refused call and the assertion below would be proving the wrong guard.
    $component = Livewire::actingAs($this->admin)
        ->test(NumberLookup::class)
        ->set('lookup', '+15125559999');

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $component->call('lookUp')->assertForbidden();
});

test('lookUp re-asks the gate itself, which a test driven through the component cannot prove', function (): void {
    // ⛔ **THE OUTER-GUARD SHAPE (398), MEASURED RATHER THAN ASSUMED.** The test
    // above drives `lookUp()` through the Livewire harness and it stays GREEN
    // with `lookUp()`'s own `authorize()` deleted — because the re-render that
    // follows the call reaches `render()`, which authorizes too, and the 403
    // that reaches `assertForbidden()` is the OUTER guard's. Proven by
    // mutation: authorize removed from `lookUp()`, that test passed, 1
    // assertion.
    //
    // So the inner guard is driven directly, with no render after it. Three
    // guards is not belt and braces here: `mount()` covers the first paint,
    // `render()` covers every subsequent one, and this one covers the action —
    // and an action that reads across tenants and writes to their audit logs
    // must refuse on its own authority.
    $this->actingAs($this->admin);

    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $component = new NumberLookup;
    $component->lookup = '+15125559999';

    expect(fn (): mixed => $component->lookUp(
        app(AuditService::class),
        app(NumberDossier::class),
        app(OwnerConsentService::class),
    ))->toThrow(AuthorizationException::class);
});

test('an admin reaches the screen from the console nav', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.number-lookup'))
        ->assertOk()
        ->assertSee('What we know about a number');
});
