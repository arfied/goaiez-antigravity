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
}
