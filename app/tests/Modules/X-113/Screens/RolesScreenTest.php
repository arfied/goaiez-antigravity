<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Ui\Roles;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RolesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.roles'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No roles yet.');

        Tenancy::setUser($owner->id);
        Role::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive role 4642',
            'description' => 'what this role may do',
        ]);
        Tenancy::forget();

        $this->get(route('x-113.roles'))
            ->assertSee('Distinctive role 4642')
            ->assertDontSee('No roles yet.');

        Livewire::test(Roles::class)->assertOk();
    }

    public function test_can_create_role(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $this->get(route('x-113.roles'))
            ->assertOk()
            ->assertSee('No roles yet.');

        Livewire::test(Roles::class)
            ->set('name', 'New Manager Role')
            ->set('description', 'Can manage things')
            ->call('createRole')
            ->assertSet('success', 'Created role New Manager Role. This feeds the roles list; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new Role)->getTable(), [
            'business_id' => $biz->id,
            'name' => 'New Manager Role',
            'description' => 'Can manage things',
        ]);

        $this->get(route('x-113.roles'))
            ->assertOk()
            ->assertSee('New Manager Role')
            ->assertDontSee('No roles yet.');

        // Duplicate refusal
        Livewire::test(Roles::class)
            ->set('name', 'New Manager Role')
            ->call('createRole')
            ->assertSet('error', "A role named 'New Manager Role' already exists in this account.");

        $this->assertEquals(1, Role::where('business_id', $biz->id)->where('name', 'New Manager Role')->count());

        // Empty name refused
        Livewire::test(Roles::class)
            ->set('name', '   ')
            ->call('createRole')
            ->assertSet('error', 'Name cannot be empty.');

        $this->assertDatabaseMissing((new Role)->getTable(), [
            'business_id' => $biz->id,
            'name' => '',
        ]);
        $this->assertDatabaseMissing((new Role)->getTable(), [
            'business_id' => $biz->id,
            'name' => '   ',
        ]);
    }
}
