<?php

declare(strict_types=1);

namespace Tests\Modules\X104\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X104\Ui\PluginSettingsPage;
use Livewire\Livewire;
use Tests\TestCase;

class PluginSettingsPageScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-104.plugin-settings-page'))->assertOk();

        Livewire::test(PluginSettingsPage::class)->assertOk();
    }
}
