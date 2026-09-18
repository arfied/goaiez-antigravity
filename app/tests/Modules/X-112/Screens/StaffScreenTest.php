<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Ui\Staff;
use Livewire\Livewire;
use Tests\TestCase;

class StaffScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-112.staff'))
            ->assertOk()
            ->assertSee('Staff')
            ->assertSee('No agency staff enrolled.');

        \App\Support\Tenancy::setUser($owner->id);
        $agency = \App\Modules\X112\Models\Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        \App\Modules\X112\Models\StaffRole::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'role' => 'account_manager',
            'is_active' => true,
        ]);
        \App\Support\Tenancy::forget();

        $this->get(route('x-112.staff'))
            ->assertOk()
            ->assertSee('User #'.$owner->id)
            ->assertSee('account_manager')
            ->assertSee('Active')
            ->assertDontSee('No agency staff enrolled.');

        Livewire::test(Staff::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-112.staff.admin'))->assertOk();

        Livewire::test(Staff::class)->assertOk();
    }
}
