<?php

namespace Tests\Modules\X104\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PluginSettingsPageScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-104.plugin-settings-page'))->assertOk();

        Livewire::test(\App\Modules\X104\Ui\PluginSettingsPage::class)->assertOk();
    }
}
