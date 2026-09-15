<?php

declare(strict_types=1);

namespace Tests\Modules\X104\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X104\Models\PluginInstall;
use App\Modules\X104\Ui\PluginSettingsPage;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PluginSettingsPageScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-104.plugin-settings-page'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No plugin sites connected yet.');

        Tenancy::setUser($owner->id);
        PluginInstall::create([
            'business_id' => $biz->id,
            'site_url' => 'https://distinctive-4484.example',
            'api_key' => 'key_distinctive_4484',
            'is_active' => true,
            'theme_files_modified_count' => 0,
            'pillars_active' => ['chat'],
            'injected_assets' => null,
        ]);
        Tenancy::forget();

        $this->get(route('x-104.plugin-settings-page'))
            ->assertOk()
            ->assertSee('https://distinctive-4484.example')
            ->assertSee('active')
            ->assertDontSee('No plugin sites connected yet.');

        Livewire::test(PluginSettingsPage::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-104.plugin-settings-page.admin'))->assertOk();

        Livewire::test(PluginSettingsPage::class)->assertOk();
    }
}
