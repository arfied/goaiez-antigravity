<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Jobs\DeliverPlatformMail;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X113\Ui\Staff;
use App\Support\Tenancy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class TeamInviteTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_owner_invites(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Invited User')
            ->set('email', 'inv-8391@example.test')
            ->call('invite')
            ->assertSet('error', null)
            ->assertSet('success', 'Invited Invited User. We emailed inv-8391@example.test a link to set their password; they can sign in once they do.');

        $this->assertDatabaseHas('users', [
            'email' => 'inv-8391@example.test',
            'role' => UserRole::Staff,
        ]);

        $user = User::where('email', 'inv-8391@example.test')->firstOrFail();

        $this->assertDatabaseHas((new BusinessMembership)->getTable(), [
            'business_id' => $biz->id,
            'user_id' => $user->id,
            'role' => 'staff',
            'accepted_at' => null,
            'revoked_at' => null,
        ]);

        Bus::assertDispatched(DeliverPlatformMail::class, 1);
    }

    public function test_invite_owns_other_business_refused(): void
    {
        Bus::fake();

        $otherOwner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owns-other@example.test']);
        $this->provisionTenant(['owner_user_id' => $otherOwner->id]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Other Owner')
            ->set('email', 'owns-other@example.test')
            ->call('invite')
            ->assertSet('error', 'That person already belongs to a business, so they cannot join yours.');

        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_invite_has_membership_elsewhere_refused(): void
    {
        Bus::fake();

        $otherBiz = $this->provisionTenant();
        $otherUser = User::factory()->create(['role' => UserRole::Staff, 'email' => 'has-membership@example.test']);
        Tenancy::set($otherBiz->id);
        BusinessMembership::create([
            'business_id' => $otherBiz->id,
            'user_id' => $otherUser->id,
            'role' => 'staff',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
        Tenancy::forgetAll();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Has Membership')
            ->set('email', 'has-membership@example.test')
            ->call('invite')
            ->assertSet('error', 'That person already belongs to a business, so they cannot join yours.');

        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_manager_and_staff_forbidden(): void
    {
        Bus::fake();

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->provisionTenant(['owner_user_id' => $manager->id]); // just for setup
        $this->actingAs($manager);

        Livewire::test(Staff::class)
            ->set('name', 'Some Name')
            ->set('email', 'manager-try@example.test')
            ->call('invite')
            ->assertForbidden();

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);

        Livewire::test(Staff::class)
            ->set('name', 'Some Name')
            ->set('email', 'staff-try@example.test')
            ->call('invite')
            ->assertForbidden();
            
        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_acceptance(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Invited User')
            ->set('email', 'inv-8392@example.test')
            ->call('invite');

        $user = User::where('email', 'inv-8392@example.test')->firstOrFail();
        $token = Password::broker()->createToken($user);

        Password::broker()->reset(
            [
                'email' => $user->email,
                'token' => $token,
                'password' => 'N3w-pass-8392!',
                'password_confirmation' => 'N3w-pass-8392!',
            ],
            function ($u, $p) {
                $u->forceFill(['password' => Hash::make($p)])->save();
                event(new PasswordReset($u));
            }
        );

        $this->assertNotNull($user->fresh()->email_verified_at);

        Tenancy::set($biz->id);
        $membership = BusinessMembership::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertNotNull($membership->accepted_at);

        Tenancy::forgetAll();
        $this->actingAs($user);
        $this->get('/account')->assertOk();
    }

    public function test_revoked_membership_not_reactivated(): void
    {
        Bus::fake();

        $biz = $this->provisionTenant();
        $user = User::factory()->create(['role' => UserRole::Staff, 'email_verified_at' => null]);
        Tenancy::set($biz->id);
        BusinessMembership::create([
            'business_id' => $biz->id,
            'user_id' => $user->id,
            'role' => 'staff',
            'invited_at' => now(),
            'revoked_at' => now(),
        ]);
        Tenancy::forgetAll();

        $token = Password::broker()->createToken($user);
        Password::broker()->reset(
            [
                'email' => $user->email,
                'token' => $token,
                'password' => 'N3w-pass-8392!',
                'password_confirmation' => 'N3w-pass-8392!',
            ],
            function ($u, $p) {
                $u->forceFill(['password' => Hash::make($p)])->save();
                event(new PasswordReset($u));
            }
        );

        Tenancy::set($biz->id);
        $membership = BusinessMembership::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertNull($membership->accepted_at);
        // user email should not be verified since they didn't really accept an active invite
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
