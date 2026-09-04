<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Ui\GroundcheckScreen;
use Livewire\Livewire;
use Tests\TestCase;

class GroundcheckScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.groundcheck-screen'))->assertOk();

        Livewire::test(GroundcheckScreen::class)->assertOk();
    }
}
