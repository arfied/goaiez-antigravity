<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\WizardStep;
use App\Models\BusinessMembership;
use App\Models\TenantExport;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class StaffReachTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_staff_goes_home_from_setup(): void
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
            ->get('/setup')
            ->assertRedirect(route('account.home'));

        Tenancy::set($business->id);
        Tenancy::setUser($owner->id);
        WizardProgress::where('business_id', $business->id)->update(['current_step' => WizardStep::FindBusiness]);
        Tenancy::forget();

        $this->actingAs($owner)
            ->get('/setup')
            ->assertRedirect(route('setup.find-business'));
    }

    public static function gatedUris(): array
    {
        return [
            ['/account'],
            ['/account/settings'],
            ['/account/activity'],
            ['/account/all-screens'],
            ['/account/assistant-answers'],
            ['/account/assistant-links'],
            ['/account/connections'],
            ['/account/credit'],
            ['/account/customers/import'],
            ['/account/facts'],
            ['/account/knowledge'],
            ['/account/locations'],
            ['/account/maps-key'],
            ['/account/plan'],
            ['/account/site-changes'],
            ['/account/support'],
            ['/account/texting'],
            ['/account/tracking'],
            ['/account/visibility'],
            ['/account/website'],
            ['/account/win-back'],
            ['/advanced'],
            ['/billing'],
            ['/setup/welcome'],
        ];
    }

    #[DataProvider('gatedUris')]
    public function test_staff_cannot_reach_owner_screens(string $uri): void
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
            ->get($uri)
            ->assertForbidden();
    }

    public function test_staff_cannot_export(): void
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
            ->post('/account/exports')
            ->assertForbidden();

        Tenancy::set($business->id);
        $this->assertSame(0, TenantExport::count());
        Tenancy::forget();
    }

    public static function workScreens(): array
    {
        return [
            ['/home'],
            ['/account/inbox'],
            ['/account/messages'],
            ['/account/customers'],
            ['/account/calls'],
            ['/account/follow-ups'],
            ['/account/replies'],
            ['/account/facebook-reviews'],
        ];
    }

    #[DataProvider('workScreens')]
    public function test_staff_reaches_work_screens(string $uri): void
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
            ->get($uri)
            ->assertOk();
    }

    public function test_owner_reaches_owner_screens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/account/settings')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/account/maps-key')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/account/texting')
            ->assertOk();
    }
}
