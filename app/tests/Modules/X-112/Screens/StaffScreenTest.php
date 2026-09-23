<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\StaffRole;
use App\Modules\X112\Ui\Staff;
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

        $this->get(route('x-112.staff'))
            ->assertOk()
            ->assertSee('Agency Staff Management')
            ->assertSee('No agency staff enrolled.');

        Tenancy::setUser($owner->id);
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        StaffRole::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'role' => 'account_manager',
            'is_active' => true,
        ]);
        Tenancy::forget();

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

    public function test_staff_invitation(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. staff empty state visible on a GET before anything is created
        $this->get(route('x-112.staff'))
            ->assertOk()
            ->assertSee('No agency staff enrolled.');

        $this->get(route('x-112.roles'))
            ->assertOk()
            ->assertSee('No roles configured.');

        Tenancy::setUser($owner->id);

        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        
        $newUser = User::factory()->create();

        // 2. control creating one
        Livewire::test(Staff::class)
            ->set('agencyId', $agency->id)
            ->set('userId', $newUser->id)
            ->set('role', 'account_manager')
            ->call('inviteStaff')
            ->assertSet('success', function ($value) use ($newUser, $agency) {
                return str_contains((string) $value, "Invited user {$newUser->id} as account_manager under agency {$agency->id}");
            })
            ->assertSet('error', null);

        $this->assertDatabaseHas((new StaffRole)->getTable(), [
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $newUser->id,
            'role' => 'account_manager',
            'is_active' => true,
        ]);

        Tenancy::forget();

        // 3. GET afterwards showing staff and losing empty state
        $this->get(route('x-112.staff'))
            ->assertOk()
            ->assertSee('User #'.$newUser->id)
            ->assertDontSee('No agency staff enrolled.');

        // 4. GET fan-out roles screen
        $this->get(route('x-112.roles'))
            ->assertOk()
            ->assertSee('Role: account_manager')
            ->assertDontSee('No roles configured.');

        Tenancy::setUser($owner->id);

        // 5. second invite updates row instead of duplicating
        Livewire::test(Staff::class)
            ->set('agencyId', $agency->id)
            ->set('userId', $newUser->id)
            ->set('role', 'admin')
            ->call('inviteStaff');

        $this->assertEquals(1, StaffRole::where('business_id', $biz->id)->where('user_id', $newUser->id)->count());
        $this->assertDatabaseHas((new StaffRole)->getTable(), [
            'user_id' => $newUser->id,
            'role' => 'admin',
        ]);

        // 6. refusals
        Livewire::test(Staff::class)
            ->set('agencyId', 0)
            ->set('userId', $newUser->id)
            ->set('role', 'account_manager')
            ->call('inviteStaff')
            ->assertSet('error', 'Agency ID is required.');

        Livewire::test(Staff::class)
            ->set('agencyId', $agency->id)
            ->set('userId', 0)
            ->set('role', 'account_manager')
            ->call('inviteStaff')
            ->assertSet('error', 'User ID is required.');

        Tenancy::forget();
    }
}
