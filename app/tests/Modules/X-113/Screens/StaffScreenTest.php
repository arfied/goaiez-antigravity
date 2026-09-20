<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X113\Ui\Staff;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StaffScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.staff'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nobody on the crew yet.');

        Tenancy::setUser($owner->id);
        $role = Role::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive role 4642',
        ]);
        StaffUser::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive Crew 4642',
            'email' => 'crew4642@example.test',
            'role_id' => $role->id,
        ]);
        Tenancy::forget();

        $this->get(route('x-113.staff'))
            ->assertSee('Distinctive Crew 4642')
            ->assertSee('crew4642@example.test')
            ->assertSee('Active')
            ->assertDontSee('Nobody on the crew yet.');

        Livewire::test(Staff::class)->assertOk();
    }

    public function test_invite_staff_member(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(Staff::class)
            ->set('name', 'New Crew')
            ->set('email', 'newcrew@example.test')
            ->call('invite')
            ->assertSet('error', null)
            ->assertSet('success', 'Invited New Crew. This feeds the staff list; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new StaffUser)->getTable(), [
            'business_id' => $biz->id,
            'name' => 'New Crew',
            'email' => 'newcrew@example.test',
        ]);

        $this->get(route('x-113.staff'))
            ->assertSee('New Crew')
            ->assertSee('newcrew@example.test')
            ->assertDontSee('Nobody on the crew yet.');
    }

    public function test_invite_refusals(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $table = (new StaffUser)->getTable();

        // 1. empty email
        Livewire::test(Staff::class)
            ->set('name', 'Crew 1')
            ->set('email', '')
            ->call('invite')
            ->assertSet('error', 'Email is required.');
        $this->assertDatabaseMissing($table, ['name' => 'Crew 1']);

        // 2. empty name
        Livewire::test(Staff::class)
            ->set('name', '')
            ->set('email', 'crew2@example.test')
            ->call('invite')
            ->assertSet('error', 'Name is required.');
        $this->assertDatabaseMissing($table, ['email' => 'crew2@example.test']);

        // 3. invalid email
        Livewire::test(Staff::class)
            ->set('name', 'Crew 3')
            ->set('email', 'not-an-email')
            ->call('invite')
            ->assertSet('error', 'That is not a valid email address.');
        $this->assertDatabaseMissing($table, ['name' => 'Crew 3']);

        // 4. already on crew
        StaffUser::create([
            'business_id' => $biz->id,
            'name' => 'Existing',
            'email' => 'existing@example.test',
        ]);

        Livewire::test(Staff::class)
            ->set('name', 'Duplicate')
            ->set('email', 'existing@example.test')
            ->call('invite')
            ->assertSet('error', 'This email is already on the crew.');
        $this->assertDatabaseMissing($table, ['name' => 'Duplicate']);
    }
}
