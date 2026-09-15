<?php

declare(strict_types=1);

namespace Tests\Modules\X207\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Ui\RetirementReasons;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RetirementReasonsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-207.retirement-reasons'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No retired devices yet.');

        Tenancy::set((int) $biz->id);
        DeviceToken::create([
            'business_id' => $biz->id,
            'platform' => 'ios',
            'device_token' => 'tok-distinctive-4474',
            'status' => 'retired',
            'retirement_reason' => 'distinctive_reason_4474'
        ]);
        Tenancy::forget();

        $this->get(route('x-207.retirement-reasons'))
            ->assertOk()
            ->assertSee('distinctive_reason_4474')
            ->assertSee('ios')
            ->assertDontSee('No retired devices yet.');

        Livewire::actingAs($owner)->test(RetirementReasons::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-207.retirement-reasons.admin'))->assertOk();

        Livewire::test(RetirementReasons::class)->assertOk();
    }
}
