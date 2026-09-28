<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class BusinessMembershipTenancyTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_accepted_unrevoked_membership_resolves_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
            'name' => 'Membership Test Business',
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
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Membership Test Business');
    }

    public function test_pending_membership_does_not_resolve_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);

        Tenancy::set((int) $business->id);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'Manager',
            'invited_at' => now(),
        ]);
        Tenancy::forget();

        $this->actingAs($staff)
            ->get(route('account.settings'))
            ->assertForbidden();
    }

    public function test_revoked_membership_does_not_resolve_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);

        Tenancy::set((int) $business->id);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'Manager',
            'invited_at' => now(),
            'accepted_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);
        Tenancy::forget();

        $this->actingAs($staff)
            ->get(route('account.settings'))
            ->assertForbidden();
    }

    public function test_second_membership_throws_unique_constraint(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $businessTwo = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);

        Tenancy::set((int) $business->id);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'Manager',
            'invited_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Tenancy::set((int) $businessTwo->id);
        BusinessMembership::create([
            'business_id' => $businessTwo->id,
            'user_id' => $staff->id,
            'role' => 'Manager',
            'invited_at' => now(),
        ]);
    }

    public function test_isolation_member_lookup_at_work(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['owner_user_id' => $owner->id]);

        $staff1 = User::factory()->create(['role' => UserRole::Staff]);
        $staff2 = User::factory()->create(['role' => UserRole::Staff]);

        Tenancy::set((int) $business->id);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff1->id,
            'role' => 'Manager',
            'invited_at' => now(),
        ]);
        BusinessMembership::create([
            'business_id' => $business->id,
            'user_id' => $staff2->id,
            'role' => 'Manager',
            'invited_at' => now(),
        ]);
        Tenancy::forget();

        Tenancy::setUser($staff1->id);
        $this->assertSame(1, BusinessMembership::withoutGlobalScopes()->count());

        Tenancy::setUser($staff2->id);
        $this->assertSame(1, BusinessMembership::withoutGlobalScopes()->count());
    }

    public function test_owner_path_unchanged(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
            'name' => 'Owner Biz',
        ]);

        $this->actingAs($owner)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Owner Biz');
    }
}
