<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CustomersScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Mail::fake();
        Notification::fake();
    }

    public function test_get_and_layout(): void
    {
        $this->get(route('account.customers'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state(): void
    {
        $this->get(route('account.customers'))
            ->assertOk()
            ->assertSee('No customers yet', false);
    }

    public function test_lists_a_person_of_this_tenant_but_not_another(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = TestCase::provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        Customer::factory()->create([
            'business_id' => $bizB->id,

            'name' => 'Other Tenant Person 7720',
        ]);

        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Customer::factory()->create([
            'business_id' => $this->biz->id,

            'name' => 'Distinctive Person 7719',
        ]);

        $this->get(route('account.customers'))
            ->assertOk()
            ->assertSee('Distinctive Person 7719', false)
            ->assertDontSee('Other Tenant Person 7720', false);
    }
}
