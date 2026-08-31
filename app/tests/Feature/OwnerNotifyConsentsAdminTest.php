<?php

declare(strict_types=1);

use App\Livewire\Admin\OwnerNotifyConsents;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\User;
use App\Services\Consent\OwnerConsentService;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The owner-channel-consent admin screen — wave 39 lane A, decision 10660
|--------------------------------------------------------------------------
|
| The read path for `owner_notification_consents`, which had a writer since
| wave 38 and no way at all to get an answer out of it —
| `TermsAcceptancesAdminTest`'s shape, one table over.
|
| ⛔ THIS HEADER SAID ITS READER WAS "A PERSON ANSWERING A CARRIER'S QUESTION:
| THIS NUMBER NEVER AGREED TO BE TEXTED" AND THE SCREEN IS KEYED ON A BUSINESS
| NUMBER (10882). Its reader is a person who already knows which business.
| `NumberLookupAdminTest` covers the index by number.
|
| ⛔ IT SHOWS AND DOES NOTHING ELSE. There is no action here beyond the
| lookup — the record is append-only, and an owner corrects their own number
| from their own account settings screen, not from here.
|
*/

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);

    $this->admin = User::factory()->create(['name' => 'Platform Staff']);

    Tenancy::forgetAll();

    $this->business = Business::provision([
        'owner_user_id' => User::factory()->create()->id,
        'name' => 'Bright Smile Dental',
    ]);

    Tenancy::forgetAll();
});

/**
 * Record one owner-channel consent, through the only writer there is.
 */
function consentForOwnerAdminScreen(Business $business, string $mobile = '+15551234567'): void
{
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

    Tenancy::forgetAll();
}

/*
|--------------------------------------------------------------------------
| The answer somebody came here for
|--------------------------------------------------------------------------
*/

test('the screen names the current number and whether it is live or stopped', function (): void {
    consentForOwnerAdminScreen($this->business);

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('+15551234567')
        ->assertSee('Live.');
});

test('a stopped number says so rather than reading as sendable', function (): void {
    consentForOwnerAdminScreen($this->business);

    Tenancy::actingAs($this->business->id, function (): void {
        app(OwnerConsentService::class)->stop((int) $this->business->id, 'user:1');
    });

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('Stopped')
        ->assertDontSee('Live.');
});

test('a business with no owner-notify number says so instead of leaving a blank', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('No owner-notify number on file');
});

test('the proof is on the screen — the page, the hashed address, the agent and the wording', function (): void {
    consentForOwnerAdminScreen($this->business);

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('https://example.test/account/settings')
        ->assertSee('Mozilla/5.0 (Macintosh)')
        ->assertSee('checked_by_user')
        ->assertSee('We will text this number about your own account');
});

test('a raw address never reaches the screen, because the record cannot hold one', function (): void {
    consentForOwnerAdminScreen($this->business);

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertDontSee(str_repeat('b', 64));
});

test('a second capture is a second row, and both name their own number', function (): void {
    // ⚠️ THE WHOLE DEFECT 10660 CLOSES: before the evidence row named its own
    // number, two captures for one business were indistinguishable.
    consentForOwnerAdminScreen($this->business, '+15551234567');
    consentForOwnerAdminScreen($this->business, '+15559876543');

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSeeInOrder(['+15559876543', '+15551234567']);
});

test('a row from before the number was recorded says so instead of guessing', function (): void {
    consentForOwnerAdminScreen($this->business);

    // ⚠️ INSIDE `Tenancy::actingAs()`, NOT AGAINST A FORGOTTEN TENANT —
    // `owner_notification_consents` is FORCE RLS with a real `business_id`
    // predicate, so an update with no tenant established matches zero rows
    // and reports success (`CLAUDE.md`'s own backfill warning, caught here
    // by the test that would otherwise have proven nothing).
    Tenancy::actingAs((int) $this->business->id, function (): void {
        DB::table('owner_notification_consents')
            ->where('business_id', $this->business->id)
            ->update(['e164' => null]);
    });

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('cannot be')
        ->assertSee('recovered from this row');
});

test('one tenant\'s owner-channel consent never appears on another tenant\'s page', function (): void {
    $other = Business::provision(['owner_user_id' => User::factory()->create()->id, 'name' => 'Sunrise Cafe']);

    consentForOwnerAdminScreen($this->business, '+15551234567');
    consentForOwnerAdminScreen($other, '+15559998888');

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('+15551234567')
        ->assertDontSee('+15559998888');
});

test('a document with no consent recorded says so rather than leaving a blank', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSee('This business has no owner-channel consent recorded');
});

/*
|--------------------------------------------------------------------------
| The lookup, the trace it leaves, and the lock
|--------------------------------------------------------------------------
*/

test('the business in view cannot be set from the client', function (): void {
    consentForOwnerAdminScreen($this->business);

    expect(fn (): mixed => Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('businessId', $this->business->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a resolved lookup is recorded in the looked-up tenant\'s own log', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertHasNoErrors();

    Tenancy::actingAs($this->business->id, function (): void {
        $entry = AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->sole();

        expect($entry->actor)->toBe('user:'.$this->admin->id)
            ->and($entry->entity_type)->toBe(Business::class)
            ->and($entry->entity_id)->toBe($this->business->id);
    });
});

test('a lookup that resolves to nothing records nothing and clears the one in view', function (): void {
    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->assertSet('businessId', $this->business->id)
        ->set('lookup', '999999')
        ->call('lookUp')
        ->assertOk()
        ->assertSet('businessId', null);

    Tenancy::actingAs($this->business->id, function (): void {
        expect(AuditLogEntry::query()->where('action', 'business.viewed_by_staff')->count())->toBe(1);
    });
});

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
*/

test('the screen is behind the admin gate', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    Livewire::actingAs($this->admin)
        ->test(OwnerNotifyConsents::class)
        ->assertForbidden();
});

test('the route is behind the admin gate too, because hiding a nav item is not authorization', function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => false);

    $this->actingAs($this->admin)
        ->get(route('admin.owner-notify-consents'))
        ->assertForbidden();
});

test('an admin reaches the screen from the console nav', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.owner-notify-consents'))
        ->assertOk()
        ->assertSee('Owner-channel consent');
});
