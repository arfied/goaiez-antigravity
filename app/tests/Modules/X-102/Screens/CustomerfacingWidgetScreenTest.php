<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Ui\CustomerfacingWidget;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingWidgetScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.customerfacing-widget'))->assertOk();

        Livewire::test(CustomerfacingWidget::class)->assertOk();
    }
}
