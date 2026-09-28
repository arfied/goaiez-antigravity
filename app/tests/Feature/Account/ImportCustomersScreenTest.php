<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\ImportCustomers;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ImportCustomersScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected User $owner;

    protected $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);
    }

    public function test_get_import_customers_renders_layout(): void
    {
        $this->get(route('account.customers.import'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_import_customers_renders_empty_state_and_upload_instructions(): void
    {
        Livewire::test(ImportCustomers::class)
            ->assertSee('Bring your customers in')
            ->assertSee('Upload the customer list you already have and we will start asking them');
    }

    public function test_staff_user_gets_measured_status(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        Tenancy::setUser($staff->id);

        $this->get(route('account.customers.import'))
            ->assertForbidden();
    }
}
