<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\AlertClaim;
use App\Modules\X153\Models\ReplyCode;
use App\Modules\X153\Ui\AlertRosterScreen;
use App\Modules\X153\Ui\ClaimexpiryRate;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ClaimexpiryRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.claimexpiry-rate'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No claims yet.');

        Tenancy::setUser($owner->id);

        $alert = Alert::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive alert 4507',
            'body' => 'Test body',
            'alert_class' => 'urgent',
            'status' => 'claimed',
        ]);

        AlertClaim::create([
            'business_id' => $biz->id,
            'alert_id' => $alert->id,
            'claimed_by_user_id' => $owner->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        AlertClaim::create([
            'business_id' => $biz->id,
            'alert_id' => $alert->id,
            'claimed_by_user_id' => $owner->id,
            'status' => 'expired',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        Tenancy::forget();

        $this->get(route('x-153.claimexpiry-rate'))
            ->assertOk()
            ->assertSee('1 active')
            ->assertSee('1 expired')
            ->assertSee('50% expiry rate')
            ->assertSee('Alert #'.$alert->id)
            ->assertDontSee('No claims yet.');

        Livewire::test(ClaimexpiryRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-153.claimexpiry-rate.admin'))->assertOk();

        Livewire::test(ClaimexpiryRate::class)->assertOk();
    }

    public function test_the_rate_is_zero_with_no_claims(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.claimexpiry-rate'))
            ->assertOk()
            ->assertSee('0% expiry rate');
    }

    public function test_pair_broadcast_and_claim_control(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Control 1: Broadcast
        Livewire::test(AlertRosterScreen::class)
            ->set('title', 'Emergency Update')
            ->set('body', 'Please review the latest policy.')
            ->call('broadcastAlert')
            ->assertSet('title', '')
            ->assertSee('Broadcasted alert with reply code ');

        $this->assertDatabaseHas((new Alert)->getTable(), [
            'business_id' => $biz->id,
            'title' => 'Emergency Update',
            'status' => 'pending',
        ]);

        $replyCode = ReplyCode::where('business_id', $biz->id)->firstOrFail();

        // Control 2: Claim Failure (Invalid code)
        Livewire::test(ClaimexpiryRate::class)
            ->set('code', 'invalid_code_123')
            ->call('claimAlert')
            ->assertSet('error', 'Reply code invalid_code_123 does not exist')
            ->assertSee('Reply code invalid_code_123 does not exist');

        // Control 2: Claim Success
        Livewire::test(ClaimexpiryRate::class)
            ->set('code', $replyCode->code)
            ->call('claimAlert')
            ->assertSet('code', '')
            ->assertSet('error', null)
            ->assertSee('Claimed alert '.$replyCode->alert_id);

        $this->assertDatabaseHas((new AlertClaim)->getTable(), [
            'business_id' => $biz->id,
            'alert_id' => $replyCode->alert_id,
            'claimed_by_user_id' => $owner->id,
        ]);

        // Fan-out assertions
        $this->get(route('x-153.alert-roster-screen'))
            ->assertOk()
            ->assertSee('Emergency Update');

        $this->get(route('x-153.claimexpiry-rate'))
            ->assertOk()
            ->assertDontSee('No claims yet.');
    }

    public function test_can_take_over_alert(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $second = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(AlertRosterScreen::class)
            ->set('title', 'Emergency Update')
            ->set('body', 'Please review the latest policy.')
            ->call('broadcastAlert');

        $replyCode = ReplyCode::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(ClaimexpiryRate::class)
            ->set('code', $replyCode->code)
            ->call('claimAlert');

        $this->actingAs($second);
        Tenancy::setUser($second->id);

        Livewire::test(ClaimexpiryRate::class)
            ->set('overrideAlertId', $replyCode->alert_id)
            ->call('takeOverAlert')
            ->assertSet('error', null)
            ->assertSee('Alert #'.$replyCode->alert_id.' is now claimed by you.');

        $this->assertDatabaseHas((new AlertClaim)->getTable(), [
            'business_id' => $biz->id,
            'alert_id' => $replyCode->alert_id,
            'claimed_by_user_id' => $second->id,
        ]);

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-153.claimexpiry-rate'))
            ->assertOk()
            ->assertSee('Alert #'.$replyCode->alert_id.' claimed by user #'.$second->id);
    }

    public function test_take_over_refuses_no_claim_for_alert(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(ClaimexpiryRate::class)
            ->set('overrideAlertId', 9999)
            ->call('takeOverAlert')
            ->assertSet('error', 'No claim found for this alert.');

        $this->assertDatabaseMissing((new AlertClaim)->getTable(), [
            'business_id' => $biz->id,
            'alert_id' => 9999,
        ]);
    }

    public function test_take_over_refuses_already_yours(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(AlertRosterScreen::class)
            ->set('title', 'Emergency Update')
            ->set('body', 'Please review the latest policy.')
            ->call('broadcastAlert');

        $replyCode = ReplyCode::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(ClaimexpiryRate::class)
            ->set('code', $replyCode->code)
            ->call('claimAlert');

        Livewire::test(ClaimexpiryRate::class)
            ->set('overrideAlertId', $replyCode->alert_id)
            ->call('takeOverAlert')
            ->assertSet('error', 'You already hold the claim for this alert.');

        $this->assertDatabaseHas((new AlertClaim)->getTable(), [
            'business_id' => $biz->id,
            'alert_id' => $replyCode->alert_id,
            'claimed_by_user_id' => $owner->id,
        ]);
    }

    public function test_take_over_refuses_nothing_selected(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(ClaimexpiryRate::class)
            ->set('overrideAlertId', 0)
            ->call('takeOverAlert')
            ->assertSet('error', 'Please select an alert to take over.');
    }
}
