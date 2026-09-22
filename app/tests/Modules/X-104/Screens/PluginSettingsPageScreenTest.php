<?php

declare(strict_types=1);

namespace Tests\Modules\X104\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X104\Models\PluginInstall;
use App\Modules\X104\Ui\PluginSettingsPage;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_control_activates_plugin_and_updates_screens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // 1. empty state
        $this->get(route('x-104.plugin-settings-page'))
            ->assertSee('No plugin sites connected yet.');

        $this->get(route('x-104.install-count'))
            ->assertSee('Active plugin sites: 0');

        // 2. drive control
        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://example.com')
            ->set('apiKey', 'my_api_key')
            ->call('activate')
            ->assertSet('success', 'Activated plugin for site https://example.com.')
            ->assertSet('siteUrl', '')
            ->assertSet('apiKey', '');

        // 3. assert row exists
        $this->assertDatabaseHas((new PluginInstall)->getTable(), [
            'business_id' => $biz->id,
            'site_url' => 'https://example.com',
            'api_key' => 'my_api_key',
        ]);

        // 4. GET control's screen and assert new value is visible
        $this->get(route('x-104.plugin-settings-page'))
            ->assertSee('https://example.com')
            ->assertDontSee('No plugin sites connected yet.');

        // 5. GET one of the other screens it feeds and assert it is no longer empty
        $this->get(route('x-104.install-count'))
            ->assertSee('Active plugin sites: 1')
            ->assertDontSee('Active plugin sites: 0');
    }

    public function test_control_refuses_invalid_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', '')
            ->set('apiKey', 'my_api_key')
            ->call('activate')
            ->assertSet('error', 'Site URL is required.');

        $this->assertDatabaseMissing((new PluginInstall)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }

    public function test_can_deactivate_a_plugin_site(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://shop.example.com')
            ->set('apiKey', 'k-4471')
            ->call('activate');

        Livewire::test(PluginSettingsPage::class)
            ->call('deactivate', 'https://shop.example.com')
            ->assertSet('deactivateSuccess', 'Plugin deactivated for https://shop.example.com. Its injected assets have been removed; switching it back on needs the site URL and the API key again.');

        $this->assertDatabaseHas((new PluginInstall)->getTable(), [
            'business_id' => $biz->id,
            'site_url' => 'https://shop.example.com',
            'is_active' => false,
        ]);
    }

    public function test_deactivating_empties_the_injected_assets(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://shop2.example.com')
            ->set('apiKey', 'k-4471')
            ->call('activate');

        // Let's set some injected assets before deactivate to ensure it changes
        $install = PluginInstall::where('business_id', $biz->id)->where('site_url', 'https://shop2.example.com')->firstOrFail();
        $install->update(['injected_assets' => ['some_asset.js']]);

        Livewire::test(PluginSettingsPage::class)
            ->call('deactivate', 'https://shop2.example.com');

        $install->refresh();
        $this->assertSame([], $install->injected_assets);
    }

    public function test_the_list_shows_the_site_as_inactive(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://shop3.example.com')
            ->set('apiKey', 'k-4471')
            ->call('activate');

        Livewire::test(PluginSettingsPage::class)
            ->call('deactivate', 'https://shop3.example.com');

        Tenancy::forget();
        $this->actingAs($owner);

        $this->get(route('x-104.plugin-settings-page'))
            ->assertOk()
            ->assertSee('https://shop3.example.com — inactive')
            ->assertDontSee('https://shop3.example.com — active');
    }

    public function test_deactivating_another_tenants_site_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::set($bizB->id);
        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://shop4.example.com')
            ->set('apiKey', 'k-4471')
            ->call('activate');

        Tenancy::set($bizA->id);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(PluginSettingsPage::class)
            ->call('deactivate', 'https://shop4.example.com');
    }
}
