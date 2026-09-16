<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\AlertClaim;
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
            'title' => 'Distinctive alert 4507',
            'body' => 'Test body',
            'alert_class' => 'urgent',
            'status' => 'claimed',
        ]);

        AlertClaim::create([
            'alert_id' => $alert->id,
            'claimed_by_user_id' => $owner->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        AlertClaim::create([
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
}
