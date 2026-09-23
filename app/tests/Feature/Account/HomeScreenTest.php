<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class HomeScreenTest extends TestCase
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
        $this->get(route('account.home'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_tenant_business_name_renders(): void
    {
        $this->biz->update(['name' => 'Distinctive Business 7719']);

        $this->get(route('account.home'))
            ->assertOk()
            ->assertSee('Distinctive Business 7719', false);
    }

    public function test_staff_user_gets_403(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        Tenancy::setUser($staff->id);

        $this->get(route('account.home'))
            ->assertStatus(403);
    }
}
