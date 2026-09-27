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
            ->assertSee('Recorded as delivered before real sending existed — never sent: 1')
            ->assertDontSee('No devices registered.');

        Livewire::test(PerplatformDeliveryHealth::class)->assertOk();
    }

    public function test_control_registers_device(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PerplatformDeliveryHealth::class, ['businessId' => $biz->id])
            ->set('deviceToken', 'test-device-token')
            ->set('platform', 'ios')
            ->call('submit')
            ->assertSet('success', 'Registered the device token. Only browser alerts are sent today — this device type is recorded as not sent.')
            ->assertSet('deviceToken', '');

        $this->assertDatabaseHas((new DeviceToken)->getTable(), [
            'business_id' => $biz->id,
            'device_token' => 'test-device-token',
            'platform' => 'ios',
            'status' => 'active',
        ]);

        $this->get(route('x-207.perplatform-delivery-health'))
            ->assertSee('ios: 1 devices')
            ->assertDontSee('No devices registered.');

        Livewire::test(PerplatformDeliveryHealth::class, ['businessId' => $biz->id])
            ->set('deviceToken', '')
            ->call('submit')
            ->assertSet('error', 'Device token is required.');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-207.perplatform-delivery-health.admin'))->assertOk();

        Livewire::test(PerplatformDeliveryHealth::class)->assertOk();
    }

    public function test_screen_maps_statuses_correctly(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $token1 = DeviceToken::create(['business_id' => $biz->id, 'platform' => 'web', 'device_token' => 'tok_1', 'status' => 'active']);
        PushDelivery::create(['business_id' => $biz->id, 'device_token_id' => $token1->id, 'payload' => [], 'sanitized' => true, 'status' => 'sent']);

        $token2 = DeviceToken::create(['business_id' => $biz->id, 'platform' => 'ios', 'device_token' => 'tok_2', 'status' => 'active']);
        PushDelivery::create(['business_id' => $biz->id, 'device_token_id' => $token2->id, 'payload' => [], 'sanitized' => true, 'status' => 'not_sent_no_transport']);
        Tenancy::forget();

        $this->get(route('x-207.perplatform-delivery-health'))
            ->assertOk()
            ->assertSee('Sent to the browser: 1')
            ->assertSee('Not sent — this device type has no delivery method yet: 1')
            ->assertDontSee('delivered: 1');
    }
}
