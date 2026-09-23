<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\BuildTenantExportJob;
use App\Livewire\Account\CustomerProfile;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\User;
use App\Services\Crm\NeverContact;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
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

it('shows the customer profile', function () {
    $customer = Customer::factory()->create(['name' => 'John Doe Profile']);

    $this->get(route('account.customers.show', $customer))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertSee('John Doe Profile');
});

it('refuses another tenants customer id with 404', function () {
    $otherBiz = TestCase::provisionTenant();
    Tenancy::set((int) $otherBiz->id);
    $otherCustomer = Customer::factory()->create();
    Tenancy::set((int) $this->biz->id);

    $this->get(route('account.customers.show', $otherCustomer))->assertNotFound();
});

it('refuses no-tenant user with 403 on get and test', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $customer = Customer::factory()->create();

    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.customers.show', $customer))->assertForbidden();

    Livewire::actingAs($staff)->test(CustomerProfile::class, ['customer' => $customer->id])->assertForbidden();
});

it('saves details', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->set('contactName', 'Jane Doe')
        ->set('contactTags', 'vip, new')
        ->set('contactRegion', 'NY')
        ->call('saveDetails');

    $customer->refresh();
    expect($customer->name)->toBe('Jane Doe')
        ->and($customer->tags)->toBe(['vip', 'new'])
        ->and($customer->region_code)->toBe('NY');

    $this->get(route('account.customers.show', $customer))
        ->assertSee('Jane Doe');
});

it('adds a note', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->set('note', 'This is a test note')
        ->call('addNote');

    $this->assertDatabaseHas('crm_notes', [
        'customer_id' => $customer->id,
        'body' => 'This is a test note',
    ]);
});

it('reminds with a task', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->set('reminderTitle', 'Call them back')
        ->set('reminderWhen', 'today')
        ->call('remind');

    $this->assertDatabaseHas('crm_tasks', [
        'customer_id' => $customer->id,
        'title' => 'Call them back',
    ]);
});

it('archives and restores', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('archive');

    $customer->refresh();
    expect($customer->archived_at)->not->toBeNull();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('restore');

    $customer->refresh();
    expect($customer->archived_at)->toBeNull();
});

it('deletes and undeletes', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('confirmDelete')
        ->assertSet('confirmingDelete', true)
        ->call('delete');

    $customer->refresh();
    expect($customer->deleted_at)->not->toBeNull();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('undelete');

    $customer->refresh();
    expect($customer->deleted_at)->toBeNull();
});

it('cancels delete', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('confirmDelete')
        ->assertSet('confirmingDelete', true)
        ->call('cancelDelete')
        ->assertSet('confirmingDelete', false);

    $customer->refresh();
    expect($customer->deleted_at)->toBeNull();
});

it('marks and clears never contact', function () {
    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('markNeverContact')
        ->assertSet('confirmingNeverContact', false);

    $this->assertDatabaseHas('suppression_list', [
        'business_id' => $this->biz->id,
        'reason_class' => 'never_contact',
    ]);

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('clearNeverContact');

    $neverContact = app(NeverContact::class);
    expect($neverContact->state($customer)->on)->toBeFalse();
});

it('merges and undoes merge', function () {
    $customer = Customer::factory()->create();
    $candidate = Customer::factory()->create([
        'email' => strtoupper($customer->email), // The duplicate detector requires them to have matching email or phone, but unique constraint prevents identical string
    ]);

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('startMerge', $candidate->id)
        ->assertSet('mergeCandidateId', $candidate->id)
        ->assertSet('chooseName', 'survivor')
        ->assertSet('chooseTags', 'survivor')
        ->call('merge');

    $this->assertDatabaseHas('customer_merges', [
        'survivor_id' => $customer->id,
        'merged_id' => $candidate->id,
    ]);

    $merge = CustomerMerge::where('survivor_id', $customer->id)->first();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('undoMerge', $merge->id);

    $merge->refresh();
    expect($merge->undone_at)->not->toBeNull();
});

it('cancels merge', function () {
    $customer = Customer::factory()->create();
    $candidate = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('startMerge', $candidate->id)
        ->call('cancelMerge')
        ->assertSet('mergeCandidateId', null);
});

it('exports contact', function () {
    Bus::fake();

    $customer = Customer::factory()->create();

    Livewire::test(CustomerProfile::class, ['customer' => $customer->id])
        ->call('exportContact');

    $this->assertDatabaseHas('tenant_exports', [
        'business_id' => $this->biz->id,
        'customer_id' => $customer->id,
    ]);

    Bus::assertDispatched(BuildTenantExportJob::class);
});
