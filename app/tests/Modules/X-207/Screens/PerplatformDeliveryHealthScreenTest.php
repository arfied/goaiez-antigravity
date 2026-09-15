<?php

declare(strict_types=1);

namespace Tests\Modules\X207\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use App\Modules\X207\Ui\PerplatformDeliveryHealth;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PerplatformDeliveryHealthScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-207.perplatform-delivery-health'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No devices registered.');

        Tenancy::setUser($owner->id);
        $token = DeviceToken::create(['business_id' => $biz->id, 'platform' => 'web', 'device_token' => 'tok_distinctive_4494', 'status' => 'active']);
        PushDelivery::create(['business_id' => $biz->id, 'device_token_id' => $token->id, 'payload' => [], 'sanitized' => true, 'status' => 'delivered']);
        Tenancy::forget();

        $this->get(route('x-207.perplatform-delivery-health'))
            ->assertOk()
            ->assertSee('web: 1 devices')
            ->assertSee('delivered: 1')
            ->assertDontSee('No devices registered.');

        Livewire::test(PerplatformDeliveryHealth::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-207.perplatform-delivery-health.admin'))->assertOk();

        Livewire::test(PerplatformDeliveryHealth::class)->assertOk();
    }
}
