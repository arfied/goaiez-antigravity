<?php

declare(strict_types=1);

namespace Tests\Modules\X196\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X196\Models\ExtensionInjection;
use App\Modules\X196\Models\ExtensionSession;
use App\Modules\X196\Ui\ExtensionPopup;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ExtensionPopupScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-196.extension-popup'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No active sessions');

        Tenancy::setUser($owner->id);
        $session = ExtensionSession::create([
            'business_id' => $biz->id,
            'session_token' => 'Distinctive session 4615',
            'is_active' => true,
        ]);
        ExtensionInjection::create([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'source_url' => 'https://distinctive-4615.example/prospect',
            'attestation_id' => 'Distinctive attest 4615',
            'prospect_payload' => ['name' => 'Distinctive prospect 4615'],
        ]);
        Tenancy::forget();

        $this->get(route('x-196.extension-popup'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('https://distinctive-4615.example/prospect')
            ->assertSee('Distinctive attest 4615')
            ->assertDontSee('No active sessions');

        Livewire::test(ExtensionPopup::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-196.extension-popup.admin'))->assertOk();

        Livewire::test(ExtensionPopup::class)->assertOk();
    }
}
