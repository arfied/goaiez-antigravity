<?php

declare(strict_types=1);

namespace Tests\Modules\X161\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X161\Models\DemoTenant;
use App\Modules\X161\Ui\DemoLedgerDaily;
use Livewire\Livewire;
use Tests\TestCase;

class DemoLedgerDailyScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-161.demo-ledger-daily.admin'))->assertOk();

        Livewire::test(DemoLedgerDaily::class)->assertOk();
    }

    public function test_can_provision_demo(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $livewire = Livewire::test(DemoLedgerDaily::class)
            ->set('prospectDomain', 'example.com')
            ->call('provisionDemo');

        $tenant = DemoTenant::where('business_id', $biz->id)->latest('id')->first();

        $livewire->assertSet('success', 'Provisioned demo for '.$tenant->demo_slug.'. This is a mock/sandbox demo — no real tenant, no real money.');

        $this->assertDatabaseHas('demo_ledger', [
            'business_id' => $biz->id,
            'entry_type' => 'credit',
            'amount_cents' => 5000,
        ]);

        $this->get(route('x-161.demo-ledger-daily.admin'))
            ->assertSee('Initial Sandbox Demo Credit Allocation');
    }

    public function test_refuses_empty_domain(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(DemoLedgerDaily::class)
            ->set('prospectDomain', '')
            ->call('provisionDemo')
            ->assertSet('error', 'Please provide a prospect domain.');

        $this->assertDatabaseMissing('demo_ledger', [
            'business_id' => $biz->id,
        ]);
    }

    public function test_can_send_sandbox_message(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(DemoLedgerDaily::class)
            ->set('prospectDomain', 'example.com')
            ->call('provisionDemo');

        $tenant = DemoTenant::where('business_id', $biz->id)->latest('id')->first();

        Livewire::test(DemoLedgerDaily::class)
            ->set('demoTenantId', $tenant->id)
            ->set('message', 'Hello Sandbox')
            ->call('sendTestMessage')
            ->assertSet('success', 'Recorded a mock debit. Carrier reached: false.');

        $this->assertDatabaseHas('demo_ledger', [
            'business_id' => $biz->id,
            'demo_tenant_id' => $tenant->id,
            'entry_type' => 'debit',
            'amount_cents' => 2,
        ]);

        $this->get(route('x-161.demo-ledger-daily.admin'))
            ->assertOk()
            ->assertSee('Outbound sandbox test message token cost');
    }

    public function test_refuses_no_demo_selected(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(DemoLedgerDaily::class)
            ->set('demoTenantId', 0)
            ->set('message', 'Hello Sandbox')
            ->call('sendTestMessage')
            ->assertSet('error', 'Please select a demo tenant and provide a message.');

        $this->assertDatabaseMissing('demo_ledger', [
            'business_id' => $biz->id,
            'entry_type' => 'debit',
        ]);
    }

    public function test_refuses_empty_message(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(DemoLedgerDaily::class)
            ->set('demoTenantId', 1)
            ->set('message', '')
            ->call('sendTestMessage')
            ->assertSet('error', 'Please select a demo tenant and provide a message.');

        $this->assertDatabaseMissing('demo_ledger', [
            'business_id' => $biz->id,
            'entry_type' => 'debit',
        ]);
    }
}
