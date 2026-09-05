<?php

declare(strict_types=1);

namespace Tests\Modules\X196;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X196\Models\ExtensionInjection;
use App\Modules\X196\Models\ExtensionSession;
use App\Modules\X196\Ui\ExtensionPopup;
use Livewire\Livewire;
use Tests\TestCase;

class X196ScreensTest extends TestCase
{
    public function test_extension_popup_renders_empty_state_and_is_tenant_scoped(): void
    {
        $otherBiz = TestCase::provisionTenant();
        $otherSession = ExtensionSession::forceCreate([
            'business_id' => $otherBiz->id,
            'is_active' => true,
            'session_token' => 'test-token-1',
        ]);
        ExtensionInjection::forceCreate([
            'business_id' => $otherBiz->id,
            'session_id' => $otherSession->id,
            'source_url' => 'https://example.com',
            'attestation_id' => 'OTHER-TENANT-SENTINEL',
            'prospect_payload' => json_encode(['name' => 'test']),
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ExtensionPopup::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('No active sessions')
            ->assertDontSee('OTHER-TENANT-SENTINEL');

        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        $this->actingAs($admin)->get(route('x-196.extension-popup.admin'))
            ->assertOk()
            ->assertSee('No active sessions');
    }

    public function test_extension_popup_renders_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        \App\Support\Tenancy::set($biz->id);

        $session = ExtensionSession::forceCreate([
            'business_id' => $biz->id,
            'is_active' => true,
            'session_token' => 'test-token-2',
        ]);
        ExtensionInjection::forceCreate([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'source_url' => 'https://example.com/INJECTED-URL',
            'attestation_id' => 'ATTESTATION-999',
            'prospect_payload' => json_encode(['name' => 'test']),
        ]);

        Livewire::test(ExtensionPopup::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Session #'.$session->id)
            ->assertSee('INJECTED-URL')
            ->assertSee('ATTESTATION-999');
    }
}
