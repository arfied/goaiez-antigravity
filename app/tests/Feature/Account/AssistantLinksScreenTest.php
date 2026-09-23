<?php

declare(strict_types=1);

use App\Enums\TenantLinkKind;
use App\Enums\UserRole;
use App\Livewire\Account\AssistantLinks;
use App\Models\User;
use App\Services\Links\TenantLinks;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('shows the assistant links console', function () {
    $this->get(route('account.assistant-links'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console');
});

it('refuses no-tenant user on get and mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.assistant-links'))->assertForbidden();

    Livewire::actingAs($staff)->test(AssistantLinks::class)->assertForbidden();
});

it('refuses to allow Staff to save booking', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set((int) $this->biz->id);

    Livewire::actingAs($staff)->test(AssistantLinks::class)
        ->set('bookingUrl', 'https://example.com/book')
        ->call('saveBooking')
        ->assertForbidden();
});

it('loads pre-existing booking and payment links on mount', function () {
    $links = app(TenantLinks::class);
    $links->setBooking('https://example.com/book');
    $links->setPayment('https://example.com/pay', 8550, 'Out of hours fee');

    Livewire::test(AssistantLinks::class)
        ->assertSet('bookingUrl', 'https://example.com/book')
        ->assertSet('paymentUrl', 'https://example.com/pay')
        ->assertSet('fee', '85.50')
        ->assertSet('feeCovers', 'Out of hours fee');
});

it('saves a booking link and removes it', function () {
    Livewire::test(AssistantLinks::class)
        ->set('bookingUrl', 'https://example.com/book')
        ->call('saveBooking')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenant_links', [
        'kind' => TenantLinkKind::Booking,
        'destination' => 'https://example.com/book',
    ]);

    Livewire::test(AssistantLinks::class)
        ->call('removeBooking')
        ->assertHasNoErrors()
        ->assertSet('bookingUrl', '');

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Booking,
    ]);
});

it('fails to save empty booking link', function () {
    Livewire::test(AssistantLinks::class)
        ->set('bookingUrl', '')
        ->call('saveBooking')
        ->assertHasErrors(['bookingUrl' => 'Paste the web address where customers book with you.']);
});

it('fails to save invalid booking link', function () {
    $component = Livewire::test(AssistantLinks::class)
        ->set('bookingUrl', 'not-a-url')
        ->call('saveBooking')
        ->assertHasErrors(['bookingUrl']);

    expect($component->errors()->get('bookingUrl')[0])
        ->toBe('That does not look like a web address. It should start with https:// and name a website.');
});

it('saves a payment link with a fee and removes it', function () {
    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', '85.50')
        ->set('feeCovers', 'Travel')
        ->call('savePayment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenant_links', [
        'kind' => TenantLinkKind::Payment,
        'destination' => 'https://example.com/pay',
        'fee_cents' => 8550,
        'fee_covers' => 'Travel',
    ]);

    Livewire::test(AssistantLinks::class)
        ->call('removePayment')
        ->assertHasNoErrors()
        ->assertSet('paymentUrl', '')
        ->assertSet('fee', '')
        ->assertSet('feeCovers', '');

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Payment,
    ]);
});

it('saves a payment link without a fee', function () {
    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', '')
        ->set('feeCovers', '')
        ->call('savePayment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenant_links', [
        'kind' => TenantLinkKind::Payment,
        'fee_cents' => null,
    ]);
});

it('fails to save a payment link with feeCovers but no fee', function () {
    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', '')
        ->set('feeCovers', 'Travel')
        ->call('savePayment')
        ->assertHasErrors(['fee' => 'Set the fee as well, or clear the line about what it covers.']);

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Payment,
    ]);
});

it('fails to save invalid fee on payment link', function () {
    // Regex fails
    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', 'abc')
        ->call('savePayment')
        ->assertHasErrors(['fee' => 'Write the fee as a plain amount, like 85 or 85.50. Leave it blank if you do not charge to come out.']);

    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', '85.505')
        ->call('savePayment')
        ->assertHasErrors(['fee' => 'Write the fee as a plain amount, like 85 or 85.50. Leave it blank if you do not charge to come out.']);

    // Negative fee cannot pass regex
    Livewire::test(AssistantLinks::class)
        ->set('paymentUrl', 'https://example.com/pay')
        ->set('fee', '-10')
        ->call('savePayment')
        ->assertHasErrors(['fee' => 'Write the fee as a plain amount, like 85 or 85.50. Leave it blank if you do not charge to come out.']);
});

it('adds a document and removes it', function () {
    Livewire::test(AssistantLinks::class)
        ->set('documentName', 'Price sheet')
        ->set('documentUrl', 'https://example.com/prices.pdf')
        ->call('addDocument')
        ->assertHasNoErrors()
        ->assertSet('documentName', '')
        ->assertSet('documentUrl', '');

    $this->assertDatabaseHas('tenant_links', [
        'kind' => TenantLinkKind::Document,
        'label' => 'Price sheet',
        'slug' => 'price-sheet',
        'destination' => 'https://example.com/prices.pdf',
    ]);

    Livewire::test(AssistantLinks::class)
        ->call('removeDocument', 'price-sheet')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Document,
        'slug' => 'price-sheet',
    ]);
});

it('fails to add document with empty name that slugs to nothing', function () {
    Livewire::test(AssistantLinks::class)
        ->set('documentName', '???')
        ->set('documentUrl', 'https://example.com/prices.pdf')
        ->call('addDocument')
        ->assertHasErrors(['documentUrl']);

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Document,
        'destination' => 'https://example.com/prices.pdf',
    ]);
});

it('updates destination on duplicate document name', function () {
    Livewire::test(AssistantLinks::class)
        ->set('documentName', 'Price sheet')
        ->set('documentUrl', 'https://example.com/prices.pdf')
        ->call('addDocument')
        ->assertHasNoErrors();

    Livewire::test(AssistantLinks::class)
        ->set('documentName', 'Price sheet')
        ->set('documentUrl', 'https://example.com/other.pdf')
        ->call('addDocument')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tenant_links', [
        'kind' => TenantLinkKind::Document,
        'slug' => 'price-sheet',
        'destination' => 'https://example.com/other.pdf',
    ]);

    $this->assertDatabaseMissing('tenant_links', [
        'kind' => TenantLinkKind::Document,
        'slug' => 'price-sheet',
        'destination' => 'https://example.com/prices.pdf',
    ]);
});

it('ignores removing unknown document slug', function () {
    Livewire::test(AssistantLinks::class)
        ->call('removeDocument', 'unknown-slug')
        ->assertHasNoErrors();
});
