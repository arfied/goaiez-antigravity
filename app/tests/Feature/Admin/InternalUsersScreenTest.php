<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\InternalUsers;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InternalUsersScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    }

    public function test_get_internal_users_renders_shell_and_email(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.internal-users'))
            ->assertOk()
            ->assertSee('Internal Platform Console')
            ->assertSee($this->admin->email);
    }

    public function test_owner_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $this->get(route('admin.internal-users'))
            ->assertForbidden();
    }

    public function test_internal_users_component_renders_email(): void
    {
        Livewire::actingAs($this->admin)
            ->test(InternalUsers::class)
            ->assertSee($this->admin->email);
    }
}
