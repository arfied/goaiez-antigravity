<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Account\OwnerNav;
use App\Support\Account\OwnerNavItem;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class StaffNavTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_staff_does_not_see_owner_links(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
        ]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);

        Tenancy::set((int) $business->id);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'Manager',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
        Tenancy::forget();

        $this->actingAs($staff)
            ->get('/account/inbox')
            ->assertOk()
            ->assertDontSee('href="'.route('account.plan').'"', false)
            ->assertDontSee('href="'.route('account.settings').'"', false)
            ->assertDontSee('href="'.route('account.places-key').'"', false)
            ->assertSee('href="'.route('account.customers').'"', false);
    }

    public function test_owner_sees_owner_links(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/account/inbox')
            ->assertOk()
            ->assertSee('href="'.route('account.plan').'"', false)
            ->assertSee('href="'.route('account.settings').'"', false);
    }

    public function test_nav_no_user_is_unfiltered(): void
    {
        $allMore = array_filter(OwnerNav::all(), fn ($item) => $item->group === OwnerNavItem::GROUP_MORE);

        $this->assertCount(
            count($allMore),
            OwnerNav::more()
        );
    }
}
