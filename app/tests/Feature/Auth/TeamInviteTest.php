<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Jobs\DeliverPlatformMail;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Modules\X113\Ui\Staff;
use App\Support\Tenancy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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

    public function test_owner_revokes_access(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Invited User')
            ->set('email', 'inv-rev@example.test')
            ->call('invite');

        $user = User::where('email', 'inv-rev@example.test')->firstOrFail();
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
        $membership = BusinessMembership::where('user_id', $user->id)->firstOrFail();

        DB::table('sessions')->insert([
            'id' => 'fake-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'fake',
            'last_activity' => time(),
        ]);

        Livewire::test(Staff::class)
            ->call('revokeAccess', $membership->id)
            ->assertSet('accessSuccess', 'Invited User can no longer sign in to your business.');

        $this->assertNotNull($membership->fresh()->revoked_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        Tenancy::forgetAll();
        $this->actingAs($user);
        $this->get('/account')->assertForbidden();
    }

    public function test_resend_invite(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'Pending User')
            ->set('email', 'pending@example.test')
            ->call('invite');

        $user = User::where('email', 'pending@example.test')->firstOrFail();

        Tenancy::set($biz->id);
        $membership = BusinessMembership::where('user_id', $user->id)->firstOrFail();

        Bus::fake();

        Livewire::test(Staff::class)
            ->call('resendInvite', $membership->id)
            ->assertSet('accessSuccess', 'Sent pending@example.test a new link.');

        Bus::assertDispatched(DeliverPlatformMail::class, 1);

        $membership->update(['accepted_at' => now()]);

        Bus::fake();

        Livewire::test(Staff::class)
            ->call('resendInvite', $membership->id)
            ->assertSet('accessError', 'That invite is no longer pending.');

        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_revoke_already_revoked(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Staff::class)
            ->set('name', 'To Revoke')
            ->set('email', 'torevoke@example.test')
            ->call('invite');

        $user = User::where('email', 'torevoke@example.test')->firstOrFail();
        Tenancy::set($biz->id);
        $membership = BusinessMembership::where('user_id', $user->id)->firstOrFail();
        $membership->update(['revoked_at' => now()]);

        Livewire::test(Staff::class)
            ->call('revokeAccess', $membership->id)
            ->assertSet('accessError', 'That access is already removed.');
    }

    public function test_manager_revoke_or_resend_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->provisionTenant(['owner_user_id' => $owner->id]);

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager);

        Livewire::test(Staff::class)
            ->call('revokeAccess', 1)
            ->assertForbidden();

        Livewire::test(Staff::class)
            ->call('resendInvite', 1)
            ->assertForbidden();
    }

    public function test_cross_tenant_membership_id_refused(): void
    {
        $owner1 = User::factory()->create(['role' => UserRole::Owner]);
        $biz1 = $this->provisionTenant(['owner_user_id' => $owner1->id]);

        $owner2 = User::factory()->create(['role' => UserRole::Owner]);
        $biz2 = $this->provisionTenant(['owner_user_id' => $owner2->id]);

        Tenancy::set((int) $biz1->id);
        $staff1 = User::factory()->create(['role' => UserRole::Staff]);
        $membership1 = BusinessMembership::create([
            'business_id' => $biz1->id,
            'user_id' => $staff1->id,
            'role' => 'staff',
            'invited_at' => now(),
        ]);

        $this->actingAs($owner2);
        Tenancy::set((int) $biz2->id);

        try {
            Livewire::test(Staff::class)
                ->call('revokeAccess', $membership1->id);
            $this->fail('Should have thrown ModelNotFoundException');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Passed
        }

        $revokedAt = \Illuminate\Support\Facades\DB::table('business_memberships')
            ->where('id', $membership1->id)
            ->value('revoked_at');
        
        $this->assertNull($revokedAt);
    }

    public function test_screen_shows_sign_in_access(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Staff, 'name' => 'Visible Staff', 'email' => 'vis@example.test']);
        Tenancy::set((int) $biz->id);
        BusinessMembership::create([
            'business_id' => $biz->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
        Tenancy::forgetAll();

        $this->actingAs($owner)
            ->get(route('x-113.staff'))
            ->assertOk()
            ->assertSee('Sign-in access')
            ->assertSee('Visible Staff')
            ->assertSee('vis@example.test')
            ->assertSee('Active');
    }
}
