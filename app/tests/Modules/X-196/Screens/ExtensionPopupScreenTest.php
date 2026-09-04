<?php

declare(strict_types=1);

namespace Tests\Modules\X196\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X196\Ui\ExtensionPopup;
use Livewire\Livewire;
use Tests\TestCase;

class ExtensionPopupScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-196.extension-popup.admin'))->assertOk();

        Livewire::test(ExtensionPopup::class)->assertOk();
    }
}
