<?php

declare(strict_types=1);

namespace Tests\Modules\X104\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X104\Models\PluginInstall;
use App\Modules\X104\Ui\InstallCount;
use App\Modules\X104\Ui\PluginSettingsPage;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class InstallCountScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-104.install-count'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Active plugin sites: 0');

        Tenancy::setUser($owner->id);
        PluginInstall::create([
            'business_id' => $biz->id,
            'site_url' => 'https://distinctive-4479.example',
            'api_key' => 'key_distinctive_4479',
            'is_active' => true,
            'theme_files_modified_count' => 0,
            'pillars_active' => ['chat'],
            'injected_assets' => null,
        ]);
        Tenancy::forget();

        $this->get(route('x-104.install-count'))
            ->assertOk()
            ->assertSee('Active plugin sites: 1')
            ->assertDontSee('Active plugin sites: 0');

        Livewire::test(InstallCount::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-104.install-count.admin'))->assertOk();

        Livewire::test(InstallCount::class)->assertOk();
    }

    public function test_install_count_drops_when_a_site_is_deactivated(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set($biz->id);
        Livewire::test(PluginSettingsPage::class)
            ->set('siteUrl', 'https://shop5.example.com')
            ->set('apiKey', 'k-4471')
            ->call('activate');
        Tenancy::forget();

        $this->get(route('x-104.install-count'))
            ->assertOk()
            ->assertSee('Active plugin sites: 1');

        Tenancy::set($biz->id);
        Livewire::test(PluginSettingsPage::class)
            ->call('deactivate', 'https://shop5.example.com');
        Tenancy::forget();

        $this->get(route('x-104.install-count'))
            ->assertOk()
            ->assertSee('Active plugin sites: 0')
            ->assertDontSee('Active plugin sites: 1');
    }
}
